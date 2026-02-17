<?php

declare(strict_types=1);

namespace App\Contract;

interface RateEntityInterface
{
    public function getRate(bool $invert = false): string;

    public function getDate(): \DateTimeImmutable;
}
