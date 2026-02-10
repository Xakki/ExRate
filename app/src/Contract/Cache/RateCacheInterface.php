<?php

declare(strict_types=1);

namespace App\Contract\Cache;

use App\DTO\RateResponse;
use App\Enum\ProviderEnum;

interface RateCacheInterface
{
    public function get(\DateTimeImmutable $date, ProviderEnum $providerEnum, string $baseCurrency, string $currency): ?RateResponse;

    public function set(\DateTimeImmutable $date, ProviderEnum $providerEnum, string $baseCurrency, string $currency, RateResponse $rateResponse): void;

    public function clear(): void;
}
