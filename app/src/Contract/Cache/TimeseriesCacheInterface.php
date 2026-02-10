<?php

declare(strict_types=1);

namespace App\Contract\Cache;

use App\DTO\TimeseriesResponse;
use App\Enum\ProviderEnum;

interface TimeseriesCacheInterface
{
    public function get(\DateTimeImmutable $start, \DateTimeImmutable $end, ProviderEnum $providerEnum, string $baseCurrency, string $currency): ?TimeseriesResponse;

    public function set(\DateTimeImmutable $start, \DateTimeImmutable $end, ProviderEnum $providerEnum, string $baseCurrency, string $currency, TimeseriesResponse $response): void;
}
