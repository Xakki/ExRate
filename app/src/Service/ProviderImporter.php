<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\Cache\RateLimitCacheInterface;
use App\Contract\Cache\SkipDayCacheInterface;
use App\DTO\GetRatesResult;
use App\Enum\FetchStatusEnum;
use App\Enum\ProviderEnum;
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
        private ProviderManager $exchangeRateProvider,
    ) {
    }

    /**
     * @return array{FetchStatusEnum, \DateTimeImmutable}
     *
     * @throws \App\Exception\DisabledProviderException
     */
    public function fetchAndSaveRates(ProviderEnum $providerEnum, \DateTimeImmutable $date): array
    {
        $date = $date->setTime(12, 0);
        $provider = $this->providerRegistry->get($providerEnum);
        $correctedDate = $this->exchangeRateProvider->getCorrectedDay($providerEnum, $date);
        $context = [
            'provider' => $providerEnum->value,
            'date' => $date->format('Y-m-d'),
            'correctedDate' => $correctedDate->format('Y-m-d'),
        ];

        if ($this->repository->existRates($provider->getId(), $provider->getBaseCurrency(), $correctedDate)) {
            $this->logger->debug('Already exist rate', $context);

            return [FetchStatusEnum::ALREADY_EXIST, $correctedDate];
        }

        $ratesResult = $provider->getRates($correctedDate);
        $this->rateLimitCache->increment($providerEnum, $provider->getRequestLimitPeriod());

        if (!count($ratesResult->rates)) {
            $this->logger->info('No more data', $context);

            return [FetchStatusEnum::NO_MORE, $correctedDate];
        }

        $this->saveCorrectDays($date, $ratesResult->date, $providerEnum);

        $this->saveRates($ratesResult);

        return [FetchStatusEnum::SUCCESS, $correctedDate];
    }

    protected function saveCorrectDays(\DateTimeImmutable $expectDate, \DateTimeImmutable $actualDate, ProviderEnum $providerEnum): void
    {
        if ($actualDate->format('Y-m-d') == $expectDate->format('Y-m-d')) {
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
}
