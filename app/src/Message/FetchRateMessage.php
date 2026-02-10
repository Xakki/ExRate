<?php

declare(strict_types=1);

namespace App\Message;

use App\Contract\Cache\RateLimitCacheInterface;
use App\Contract\ProviderInterface;
use App\Enum\ProviderEnum;
use Symfony\Component\Messenger\Stamp\DeduplicateStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

class FetchRateMessage
{
    public function __construct(
        public \DateTimeImmutable $date,
        public ProviderEnum $provider = ProviderEnum::CBR,
        public int $loadPrevious = 0,
        public int $noRate = 0,
        public int $retryCount = 0,
    ) {
    }

    public function getUniqueId(): string
    {
        return sprintf(
            'fetch-rate-%s-%s',
            $this->date->format('Y-m-d'),
            $this->provider->value
        );
    }

    /**
     * @return StampInterface[]
     */
    public function getStamps(int $delaySecond = 0, ?RateLimitCacheInterface $cache = null, ?ProviderInterface $provider = null): array
    {
        if ($cache && $provider && $provider->getRequestLimit() > 0) {
            $limit = $provider->getRequestLimit();
            $period = $provider->getRequestLimitPeriod();
            $currentCount = $cache->getCount($this->provider, $period);

            if ($currentCount >= ($limit - 10)) {
                // Если мы близки к лимиту, добавляем задержку.
                // Например, распределяем оставшиеся запросы или просто ждем период.
                // Для простоты: если лимит почти исчерпан, откладываем на 1/10 периода.
                $delaySecond += (int) ($period / 10);
            }
        }

        $stamps = [
            new DeduplicateStamp($this->getUniqueId(), 0),
        ];
        if ($delaySecond) {
            $stamps[] = new DelayStamp($delaySecond * 1000);
        }

        return $stamps;
    }
}
