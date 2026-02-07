<?php

declare(strict_types=1);

namespace App\Service\RateSource\Dto;

readonly class GetRatesResult
{
    public function __construct(
        public \DateTimeImmutable $date,
        /**
         * @var array<string, string>
         */
        public array $rates,
    ) {
    }
}
