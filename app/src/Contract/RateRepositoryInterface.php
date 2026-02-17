<?php

namespace App\Contract;

interface RateRepositoryInterface
{
    /**
     * @return RateEntityInterface[]
     */
    public function findTwoLastRates(ProviderRateInterface $provider, string $currency, string $baseCurrency, \DateTimeImmutable $maxDate, ?\DateTimeImmutable $minDate = null): array;

    public function findOneByDateRange(ProviderRateInterface $provider, string $baseCurrency, \DateTimeImmutable $minDate, \DateTimeImmutable $maxDate): ?RateEntityInterface;

    /**
     * @return RateEntityInterface[]
     */
    public function findRatesByPeriod(ProviderRateInterface $provider, string $currency, string $baseCurrency, \DateTimeImmutable $start, \DateTimeImmutable $end): array;

    public function getMinDate(ProviderRateInterface $provider): ?\DateTimeImmutable;

    public function getMaxDate(ProviderRateInterface $provider): ?\DateTimeImmutable;

    /**
     * @param array<string, RateDataInterface> $rates
     */
    public function saveRatesBatch(ProviderRateInterface $provider, string $baseCurrency, \DateTimeImmutable $date, array $rates): void;
}
