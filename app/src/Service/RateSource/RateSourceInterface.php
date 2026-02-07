<?php

namespace App\Service\RateSource;

use App\Enum\RateSource;

interface RateSourceInterface
{
    public function getId(): int;

    public function getEnum(): RateSource;

    public function getBaseCurrency(): string;

    /**
     * @return array<string, string>
     */
    public function getRates(\DateTimeImmutable $date): array;
}
