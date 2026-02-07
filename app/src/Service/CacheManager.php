<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\CacheManagerInterface;
use Psr\Cache\CacheItemPoolInterface;

final readonly class CacheManager implements CacheManagerInterface
{
    public function __construct(private CacheItemPoolInterface $cache)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $item = $this->cache->getItem($key);

        if (!$item->isHit()) {
            return $default;
        }

        return $item->get();
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $item = $this->cache->getItem($key);
        $item->set($value);

        if (null !== $ttl) {
            $item->expiresAfter($ttl);
        }

        return $this->cache->save($item);
    }

    public function has(string $key): bool
    {
        return $this->cache->hasItem($key);
    }
}
