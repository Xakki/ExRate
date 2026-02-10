<?php

declare(strict_types=1);

namespace App\Cache;

use App\Contract\Cache\RateCacheInterface;
use App\DTO\RateResponse;
use App\Enum\ProviderEnum;
use Psr\Cache\CacheItemPoolInterface;

final readonly class RateCache implements RateCacheInterface
{
    private const string CACHE_KEY = 'rate_%s_%s_%s_%s';

    public function __construct(private CacheItemPoolInterface $cacheItemPool)
    {
    }

    public function get(\DateTimeImmutable $date, ProviderEnum $providerEnum, string $baseCurrency, string $currency): ?RateResponse
    {
        $key = sprintf(self::CACHE_KEY, $providerEnum->value, $date->format('Y-m-d'), $baseCurrency, $currency);

        $item = $this->cacheItemPool->getItem($key);

        if (!$item->isHit()) {
            return null;
        }

        return $item->get();
    }

    public function set(\DateTimeImmutable $date, ProviderEnum $providerEnum, string $baseCurrency, string $currency, RateResponse $rateResponse): void
    {
        $key = sprintf(self::CACHE_KEY, $providerEnum->value, $date->format('Y-m-d'), $baseCurrency, $currency);

        $item = $this->cacheItemPool->getItem($key);
        $item->set($rateResponse);
        // $item->expiresAfter(0);

        $this->cacheItemPool->save($item);
    }

    public function clear(): void
    {
        $this->cacheItemPool->clear();
    }
}
