<?php

declare(strict_types=1);

namespace App\Cache;

use App\Contract\Cache\RateCacheInterface;
use App\Enum\ProviderEnum;
use App\Response\RateResponse;
use App\Util\Date;
use Psr\Cache\CacheItemPoolInterface;

final readonly class RateCache implements RateCacheInterface
{
    private const string CACHE_KEY = 'rate_%s_%s_%s_%s';
    private const int FRESH_DAYS = 5;
    private const int FRESH_TTL_SECONDS = 86400;

    public function __construct(private CacheItemPoolInterface $cacheItemPool)
    {
    }

    public function get(\DateTimeImmutable $date, ProviderEnum $providerEnum, string $baseCurrency, string $currency): ?RateResponse
    {
        $key = sprintf(self::CACHE_KEY, $providerEnum->value, $date->format(Date::FORMAT), $baseCurrency, $currency);

        $item = $this->cacheItemPool->getItem($key);

        if (!$item->isHit()) {
            return null;
        }

        return $item->get();
    }

    public function set(\DateTimeImmutable $date, ProviderEnum $providerEnum, string $baseCurrency, string $currency, RateResponse $rateResponse): void
    {
        $key = sprintf(self::CACHE_KEY, $providerEnum->value, $date->format(Date::FORMAT), $baseCurrency, $currency);

        $item = $this->cacheItemPool->getItem($key);
        $item->set($rateResponse);

        // Свежие даты: TTL сутки (провайдер ещё может дозалить данные)
        // Старше FRESH_DAYS: бессрочно (исторические курсы не меняются)
        if (Date::getDayDiff($date) <= self::FRESH_DAYS) {
            $item->expiresAfter(self::FRESH_TTL_SECONDS);
        }

        $this->cacheItemPool->save($item);
    }

    public function clear(): void
    {
        $this->cacheItemPool->clear();
    }
}
