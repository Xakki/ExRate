<?php

declare(strict_types=1);

namespace App\DTO;

use App\Contract\ProviderRateInterface;
use App\Contract\RateDataInterface;

readonly class GetRatesResult
{
    public function __construct(
        public readonly ProviderRateInterface $provider,
        public readonly string $baseCurrency,
        public readonly \DateTimeImmutable $date,
        /**
         * @var array<string, RateDataInterface>
         */
        public readonly array $rates,
    ) {
    }
}
