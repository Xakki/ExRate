<?php

declare(strict_types=1);

namespace App\DTO;

use App\Contract\RateDataInterface;

readonly class RateExtendData implements RateDataInterface
{
    public function __construct(
        public readonly string $open,
        public readonly string $low,
        public readonly string $high,
        public readonly string $close,
        public readonly string $volume,
    ) {
    }
}
