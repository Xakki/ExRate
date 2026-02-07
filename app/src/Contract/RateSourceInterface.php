<?php

declare(strict_types=1);

namespace App\Contract;

use App\Enum\RateSource;
use App\Service\RateSource\Dto\GetRatesResult;

interface RateSourceInterface
{
    public static function getServiceName(): string;

    public function getId(): int;

    public function getEnum(): RateSource;

    public function getBaseCurrency(): string;

    public function getRates(\DateTimeImmutable $date): GetRatesResult;
}
