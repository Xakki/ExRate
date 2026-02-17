<?php

declare(strict_types=1);

namespace App\Cache;

use App\Contract\Cache\RateLimitCacheInterface;
use App\Enum\ProviderEnum;
use Psr\Cache\CacheItemPoolInterface;

final readonly class RateLimitCache implements RateLimitCacheInterface
{
    private const string CACHE_KEY = 'rate_limit_%s_%d';

    public function __construct(
        private CacheItemPoolInterface $cacheItemPool,
    ) {
    }

    private function getKey(ProviderEnum $providerEnum, int $period): string
    {
        if ($period <= 0) {
            return sprintf(self::CACHE_KEY, $providerEnum->value, 0);
        }

        $window = (int) (time() / $period);

        return sprintf(self::CACHE_KEY, $providerEnum->value, $window);
    }

    public function getCount(ProviderEnum $providerEnum, int $period = 86400): int
    {
        $key = $this->getKey($providerEnum, $period);
        $item = $this->cacheItemPool->getItem($key);

        if (!$item->isHit()) {
            return 0;
        }

        return (int) $item->get();
    }

    public function increment(ProviderEnum $providerEnum, int $period): int
    {
        if ($period <= 0) {
            return 0;
        }

        $key = $this->getKey($providerEnum, $period);
        $item = $this->cacheItemPool->getItem($key);

        $count = $item->isHit() ? (int) $item->get() : 0;
        ++$count;

        $item->set($count);
        $item->expiresAfter($period * 2); // Храним чуть дольше периода для надежности
        $this->cacheItemPool->save($item);

        return $count;
    }

    public function clear(ProviderEnum $providerEnum): void
    {
        // В данном проекте используется префиксный кеш,
        // но для простоты мы просто не будем очищать старые окна, они сами умрут по TTL
    }

    public function block(ProviderEnum $providerEnum, int $seconds): void
    {
        $key = sprintf('rate_limit_block_%s', $providerEnum->value);
        $item = $this->cacheItemPool->getItem($key);
        $item->set(time() + $seconds);
        $item->expiresAfter($seconds);
        $this->cacheItemPool->save($item);
    }

    public function getBlockedUntil(ProviderEnum $providerEnum): ?int
    {
        $key = sprintf('rate_limit_block_%s', $providerEnum->value);
        $item = $this->cacheItemPool->getItem($key);

        if (!$item->isHit()) {
            return null;
        }

        return (int) $item->get();
    }
}
