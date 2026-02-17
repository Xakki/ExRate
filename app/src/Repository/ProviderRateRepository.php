<?php

declare(strict_types=1);

namespace App\Repository;

use App\Contract\ProviderRateExtendInterface;
use App\Contract\ProviderRateInterface;
use App\Contract\RateEntityInterface;
use App\Contract\RateRepositoryInterface;

final readonly class ProviderRateRepository implements RateRepositoryInterface
{
    public function __construct(
        private RateRepository $rateRepository,
        private RateExtendRepository $rateExtendRepository,
    ) {
    }

    public function findTwoLastRates(ProviderRateInterface $provider, string $currency, string $baseCurrency, \DateTimeImmutable $maxDate, ?\DateTimeImmutable $minDate = null): array
    {
        return $this->getRepository($provider)->findTwoLastRates(
            provider: $provider,
            currency: $currency,
            baseCurrency: $baseCurrency,
            maxDate: $maxDate,
            minDate: $minDate,
        );
    }

    public function findOneByDateRange(ProviderRateInterface $provider, string $baseCurrency, \DateTimeImmutable $minDate, \DateTimeImmutable $maxDate): ?RateEntityInterface
    {
        return $this->getRepository($provider)->findOneByDateRange(
            provider: $provider,
            baseCurrency: $baseCurrency,
            minDate: $minDate,
            maxDate: $maxDate,
        );
    }

    public function findRatesByPeriod(ProviderRateInterface $provider, string $currency, string $baseCurrency, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->getRepository($provider)->findRatesByPeriod(
            provider: $provider,
            currency: $currency,
            baseCurrency: $baseCurrency,
            start: $start,
            end: $end,
        );
    }

    public function getMinDate(ProviderRateInterface $provider): ?\DateTimeImmutable
    {
        return $this->getRepository($provider)->getMinDate($provider);
    }

    public function getMaxDate(ProviderRateInterface $provider): ?\DateTimeImmutable
    {
        return $this->getRepository($provider)->getMaxDate($provider);
    }

    public function saveRatesBatch(ProviderRateInterface $provider, string $baseCurrency, \DateTimeImmutable $date, array $rates): void
    {
        $this->getRepository($provider)->saveRatesBatch(
            provider: $provider,
            baseCurrency: $baseCurrency,
            date: $date,
            rates: $rates,
        );
    }

    private function getRepository(ProviderRateInterface $provider): RateRepositoryInterface
    {
        if ($provider instanceof ProviderRateExtendInterface) {
            return $this->rateExtendRepository;
        }

        return $this->rateRepository;
    }
}
