<?php

declare(strict_types=1);

namespace App\DTO;

readonly class GetRatesResult
{
    public function __construct(
        public int $providerId,
        public string $baseCurrency,
        public \DateTimeImmutable $date,
        /**
         * @var array<string, string>
         */
        public array $rates,
    ) {
    }
}
