<?php

declare(strict_types=1);

namespace App\Cache;

use App\Contract\Cache\SkipDayCacheInterface;
use App\Enum\ProviderEnum;
use Psr\Cache\CacheItemPoolInterface;

final readonly class SkipDayCache implements SkipDayCacheInterface
{
    private const string CACHE_KEY = 'skip_%s_%s';

    public function __construct(
        private CacheItemPoolInterface $cacheItemPool,
    ) {
    }

    public function get(ProviderEnum $providerEnum, \DateTimeImmutable $date): ?\DateTimeImmutable
    {
        $key = sprintf(self::CACHE_KEY, $providerEnum->value, $date->format('Y-m-d'));
        $item = $this->cacheItemPool->getItem($key);

        if (!$item->isHit()) {
            return null;
        }

        $value = $item->get();
        if (is_string($value)) {
            return new \DateTimeImmutable($value);
        }

        return null;
    }

    public function set(ProviderEnum $providerEnum, \DateTimeImmutable $date, \DateTimeImmutable $correctedDate): void
    {
        $key = sprintf(self::CACHE_KEY, $providerEnum->value, $date->format('Y-m-d'));
        $item = $this->cacheItemPool->getItem($key);
        $item->set($correctedDate->format('Y-m-d'));
        $this->cacheItemPool->save($item);
    }

    public function clear(): void
    {
        $this->cacheItemPool->clear();
    }
}
