<?php

declare(strict_types=1);

namespace App\Contract\Cache;

use App\Enum\ProviderEnum;

interface RateLimitCacheInterface
{
    public function getCount(ProviderEnum $providerEnum, int $period): int;

    public function increment(ProviderEnum $providerEnum, int $period): int;

    public function clear(ProviderEnum $providerEnum): void;
}
