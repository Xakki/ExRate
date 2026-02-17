<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\Cache\RateLimitCacheInterface;
use App\Contract\ProviderRateInterface;
use App\Contract\RateRepositoryInterface;
use App\DTO\GetRatesResult;
use App\Enum\FetchStatusEnum;
use App\Exception\LimitException;
use App\Exception\NotAvailableMethod;
use App\Exception\RetryByDateException;
use App\Util\Date;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

readonly class ProviderImporter
{
    public function __construct(
        private RateRepositoryInterface $repository,
        private RateLimitCacheInterface $rateLimitCache,
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{FetchStatusEnum, \DateTimeImmutable}
     *
     * @throws \App\Exception\DisabledProviderException
     */
    public function fetchAndSaveRates(ProviderRateInterface $provider, \DateTimeImmutable $date): array
    {
        $date = $date->setTime(12, 0);
        $providerEnum = $provider->getEnum();
        $blockedUntil = $this->rateLimitCache->getBlockedUntil($providerEnum);
        if ($blockedUntil && $blockedUntil > time()) {
            throw new LimitException($blockedUntil - time());
        }

        $context = [
            'provider' => $providerEnum->value,
            'date' => $date->format(Date::FORMAT),
        ];

        if ($provider->getHistoryDaysLag() && Date::getDayDiff($date) < $provider->getHistoryDaysLag()) {
            throw new RetryByDateException(new \DateTimeImmutable()->modify('-'.$provider->getHistoryDaysLag().' day'));
        }

        $rateForDate = $this->repository->findOneByDateRange($provider, $provider->getBaseCurrency(), $date, $date);
        if ($rateForDate) {
            $minDate = $this->repository->getMinDate($provider);
            $this->logger->info('Already exist rate for '.$rateForDate->getDate()->format(Date::FORMAT).
                '. Min date is '.$minDate->format(Date::FORMAT), $context);

            return [FetchStatusEnum::ALREADY_EXIST, $minDate];
        }

        try {
            try {
                $days = $provider->getPeriodDays() ?: 60;
                $startDate = $date->modify('-'.$days.' day');
                $results = $provider->getRatesByRangeDate($startDate, $date);
                if (count($results) > 0) {
                    $this->checkCurrencyConsistency($provider, $results[0]);
                    foreach ($results as $result) {
                        $this->saveRates($result);
                    }
                }
                $this->rateLimitCache->increment($providerEnum, $provider->getRequestLimitPeriod());

                return [FetchStatusEnum::SUCCESS, $startDate];
            } catch (NotAvailableMethod) {
                $ratesResult = $provider->getRatesByDate($date);
            }
        } catch (LimitException $e) {
            $this->rateLimitCache->block($providerEnum, $e->secondsToReset);

            throw $e;
        }

        $this->rateLimitCache->increment($providerEnum, $provider->getRequestLimitPeriod());

        if (!count($ratesResult->rates)) {
            $this->logger->info('No data for '.$date->format(Date::FORMAT), $context);

            return [FetchStatusEnum::EMPTY, $ratesResult->date];
        } elseif ($ratesResult->date->format(Date::FORMAT) !== $date->format(Date::FORMAT)) {
            $this->logger->info('No data for '.$date->format(Date::FORMAT), $context);
        }

        $this->checkCurrencyConsistency($provider, $ratesResult);

        $this->saveRates($ratesResult);

        return [FetchStatusEnum::SUCCESS, $ratesResult->date];
    }

    private function saveRates(GetRatesResult $result): void
    {
        $this->repository->saveRatesBatch(
            $result->provider,
            $result->baseCurrency,
            $result->date->setTime(0, 0),
            $result->rates,
        );
        $this->logger->info('+ Save rates : '.count($result->rates), ['provider' => $result->provider, 'baseCurrency' => $result->baseCurrency, 'date' => $result->date]);
        $this->entityManager->clear();
    }

    private function checkCurrencyConsistency(ProviderRateInterface $provider, GetRatesResult $ratesResult): void
    {
        $fetchedCurrencies = array_keys($ratesResult->rates);
        $availableCurrencies = $provider->getAvailableCurrencies();

        $missingInFetched = array_diff($availableCurrencies, $fetchedCurrencies);
        $extraInFetched = array_diff($fetchedCurrencies, $availableCurrencies);

        if (count($missingInFetched) > 0 || count($extraInFetched) > 0) {
            $this->logger->info('Currency mismatch for provider {provider}', [
                'provider' => $provider->getEnum()->value,
                'missing_in_fetched' => array_values($missingInFetched),
                'extra_in_fetched' => array_values($extraInFetched),
                'date' => $ratesResult->date->format(Date::FORMAT),
            ]);
        }
    }
}
