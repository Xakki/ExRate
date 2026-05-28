<?php

declare(strict_types=1);

namespace App\Cache;

use App\Contract\Cache\RateLimitCacheInterface;
use App\Enum\ProviderEnum;

final readonly class RateLimitCache implements RateLimitCacheInterface
{
    private const string COUNT_KEY = 'rate_limit_%s_%d';
    private const string BLOCK_KEY = 'rate_limit_block_%s';

    public function __construct(
        private \Redis $redis,
    ) {
    }

    private function getCountKey(ProviderEnum $providerEnum, int $period): string
    {
        $window = $period > 0 ? (int) (time() / $period) : 0;

        return sprintf(self::COUNT_KEY, $providerEnum->value, $window);
    }

    public function getCount(ProviderEnum $providerEnum, int $period = 86400): int
    {
        $value = $this->redis->get($this->getCountKey($providerEnum, $period));

        return false === $value ? 0 : (int) $value;
    }

    public function increment(ProviderEnum $providerEnum, int $period): int
    {
        if ($period <= 0) {
            return 0;
        }

        $key = $this->getCountKey($providerEnum, $period);
        $count = (int) $this->redis->incr($key);
        if (1 === $count) {
            // TTL чуть больше периода для надёжности после смены окна
            $this->redis->expire($key, $period * 2);
        }

        return $count;
    }

    public function clear(ProviderEnum $providerEnum): void
    {
        $iter = null;
        $pattern = sprintf('rate_limit_%s_*', $providerEnum->value);
        while ($keys = $this->redis->scan($iter, $pattern, 100)) {
            $this->redis->del($keys);
        }

        $this->redis->del(sprintf(self::BLOCK_KEY, $providerEnum->value));
    }

    public function block(ProviderEnum $providerEnum, int $seconds): void
    {
        $key = sprintf(self::BLOCK_KEY, $providerEnum->value);
        $this->redis->setex($key, $seconds, (string) (time() + $seconds));
    }

    public function getBlockedUntil(ProviderEnum $providerEnum): ?int
    {
        $key = sprintf(self::BLOCK_KEY, $providerEnum->value);
        $value = $this->redis->get($key);

        return false === $value ? null : (int) $value;
    }
}
