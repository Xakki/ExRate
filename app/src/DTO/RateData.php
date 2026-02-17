<?php

declare(strict_types=1);

namespace App\DTO;

use App\Contract\RateDataInterface;

readonly class RateData implements RateDataInterface
{
    public function __construct(
        public readonly string $close,
    ) {
    }
}
