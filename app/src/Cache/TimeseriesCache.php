<?php

declare(strict_types=1);

namespace App\Cache;

use App\Contract\Cache\TimeseriesCacheInterface;
use App\DTO\TimeseriesResponse;
use App\Enum\ProviderEnum;
use Psr\Cache\CacheItemPoolInterface;

final readonly class TimeseriesCache implements TimeseriesCacheInterface
{
    private const string CACHE_KEY = 'ts_%s_%s_%s_%s_%s';

    public function __construct(private CacheItemPoolInterface $cacheItemPool)
    {
    }

    public function get(\DateTimeImmutable $start, \DateTimeImmutable $end, ProviderEnum $providerEnum, string $baseCurrency, string $currency): ?TimeseriesResponse
    {
        $key = $this->generateKey($start, $end, $providerEnum, $baseCurrency, $currency);
        $item = $this->cacheItemPool->getItem($key);

        if (!$item->isHit()) {
            return null;
        }

        return $item->get();
    }

    public function set(\DateTimeImmutable $start, \DateTimeImmutable $end, ProviderEnum $providerEnum, string $baseCurrency, string $currency, TimeseriesResponse $response): void
    {
        $key = $this->generateKey($start, $end, $providerEnum, $baseCurrency, $currency);
        $item = $this->cacheItemPool->getItem($key);
        $item->set($response);
        $item->expiresAfter(86400); // 24 hours

        $this->cacheItemPool->save($item);
    }

    private function generateKey(\DateTimeImmutable $start, \DateTimeImmutable $end, ProviderEnum $providerEnum, string $baseCurrency, string $currency): string
    {
        return sprintf(
            self::CACHE_KEY,
            $providerEnum->value,
            $baseCurrency,
            $currency,
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
        );
    }
}
