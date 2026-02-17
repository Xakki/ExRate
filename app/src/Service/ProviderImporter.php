<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\Cache\RateLimitCacheInterface;
use App\Contract\Cache\SkipDayCacheInterface;
use App\Contract\ProviderInterface;
use App\DTO\GetRatesResult;
use App\Enum\FetchStatusEnum;
use App\Enum\ProviderEnum;
use App\Exception\LimitException;
use App\Exception\NoDataAvailableException;
use App\Repository\ExchangeRateRepository;
use Psr\Log\LoggerInterface;

readonly class ProviderImporter
{
    public function __construct(
        private ProviderRegistry $providerRegistry,
        private ExchangeRateRepository $repository,
        private SkipDayCacheInterface $correctedDayCache,
        private RateLimitCacheInterface $rateLimitCache,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{FetchStatusEnum, \DateTimeImmutable}
     *
     * @throws \App\Exception\DisabledProviderException
     */
    public function fetchAndSaveRates(ProviderEnum $providerEnum, \DateTimeImmutable $date): array
    {
        $blockedUntil = $this->rateLimitCache->getBlockedUntil($providerEnum);
        if ($blockedUntil && $blockedUntil > time()) {
            throw new LimitException($blockedUntil - time());
        }

        $date = $date->setTime(12, 0);
        $provider = $this->providerRegistry->get($providerEnum);

        $context = [
            'provider' => $providerEnum->value,
            'date' => $date->format('Y-m-d'),
        ];

        if ($provider->getDaysLag()) {
            $availableDate = new \DateTimeImmutable()->modify('-'.$provider->getDaysLag().' day');
            if ($availableDate->format('Y-m-d') < $date->format('Y-m-d')) {
                throw new NoDataAvailableException('Last rate available: '.$availableDate->format('Y-m-d'));
            }
        }
        $last = $this->repository->findOneByDateRange($provider->getId(), $provider->getBaseCurrency(), $date->modify('-10 day'), $date);
        if ($last) {
            $this->logger->debug('Already exist rate', $context);

            return [FetchStatusEnum::ALREADY_EXIST, $last->getDate()];
        }

        try {
            $ratesResult = $provider->getRatesByDate($date);
        } catch (LimitException $e) {
            $this->rateLimitCache->block($providerEnum, $e->secondsToReset);

            throw $e;
        }

        $this->rateLimitCache->increment($providerEnum, $provider->getRequestLimitPeriod());

        if (!count($ratesResult->rates)) {
            $this->logger->info('No data for '.$date->format('Y-m-d'), $context);

            return [FetchStatusEnum::EMPTY, $ratesResult->date];
        } elseif ($ratesResult->date->format('Y-m-d') !== $date->format('Y-m-d')) {
            $this->logger->info('No data for '.$date->format('Y-m-d'), $context);
        }

        $this->checkCurrencyConsistency($provider, $ratesResult);

        $this->saveCorrectDays($date, $ratesResult->date, $providerEnum);

        $this->saveRates($ratesResult);

        return [FetchStatusEnum::SUCCESS, $ratesResult->date];
    }

    protected function saveCorrectDays(\DateTimeImmutable $expectDate, \DateTimeImmutable $actualDate, ProviderEnum $providerEnum): void
    {
        if ($actualDate->format('Y-m-d') === $expectDate->format('Y-m-d')) {
            return;
        }

        $period = new \DatePeriod($actualDate, new \DateInterval('P1D'), $expectDate);

        foreach ($period as $day) {
            $targetDay = $day->modify('+1 day');
            $this->logger->debug('Add correct day:'.$targetDay->format('Y-m-d').' => '.$actualDate->format('Y-m-d'));
            $this->correctedDayCache->set($providerEnum, $targetDay, $actualDate);
        }
    }

    private function saveRates(GetRatesResult $result): void
    {
        $this->repository->saveRatesBatch(
            $result->providerId,
            $result->baseCurrency,
            $result->date->setTime(0, 0),
            $result->rates
        );
    }

    private function checkCurrencyConsistency(ProviderInterface $provider, GetRatesResult $ratesResult): void
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
                'date' => $ratesResult->date->format('Y-m-d'),
            ]);
        }
    }
}
