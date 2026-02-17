<?php

declare(strict_types=1);

namespace App\Contract\Cache;

use App\Enum\FrequencyEnum;
use App\Enum\ProviderEnum;
use App\Response\TimeseriesResponse;

interface TimeseriesCacheInterface
{
    public function get(\DateTimeImmutable $start, \DateTimeImmutable $end, ProviderEnum $providerEnum, string $baseCurrency, string $currency, FrequencyEnum $group = FrequencyEnum::Daily): ?TimeseriesResponse;

    public function set(\DateTimeImmutable $start, \DateTimeImmutable $end, ProviderEnum $providerEnum, string $baseCurrency, string $currency, TimeseriesResponse $response, FrequencyEnum $group = FrequencyEnum::Daily): void;
}
