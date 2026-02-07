<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\CacheManagerInterface;
use App\Contract\RateSourceInterface;
use App\DTO\RateResponse;
use App\Entity\ExchangeRate;
use App\Enum\RateSource;
use App\Exception\RateNotFoundException;
use App\Message\FetchRateMessage;
use App\Repository\ExchangeRateRepository;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class ExchangeRateProvider
{
    private const string CACHE_KEY_RATE = 'rate_%s_%s_%s_%s';
    private const string CACHE_KEY_CORRECTED_DAY = 'corrected_day_%s_%s';
    private const int CACHE_TTL_RATE = 85400;

    public function __construct(
        private RateSourceRegistry $rateSources,
        private ExchangeRateRepository $repository,
        private CacheManagerInterface $cache,
        private MessageBusInterface $bus,
        private int $currencyPrecision,
    ) {
    }

    public function getRate(\DateTimeImmutable $date, string $currency, string $baseCurrency, RateSource $rateSource = RateSource::CBR): RateResponse
    {
        $date = $this->getCorrectedDay($rateSource, $date);

        $cacheKeyRate = sprintf(self::CACHE_KEY_RATE, $date->format('Y-m-d'), $currency, $baseCurrency, $rateSource->value);
        if ($cacheResponse = $this->cache->get($cacheKeyRate)) {
            return $cacheResponse;
        }

        $source = $this->rateSources->get($rateSource);
        $sourceBaseCurrency = $source->getBaseCurrency();

        if ($sourceBaseCurrency !== $baseCurrency) {
            $response = $this->calculateCrossRate($source, $date, $currency, $baseCurrency);
        } else {
            $response = $this->fetchDirectRate($source, $date, $currency);
        }

        if (!$response->isFallback) {
            $this->cache->set($cacheKeyRate, $response, self::CACHE_TTL_RATE);
        }

        return $response;
    }

    public function getCorrectedDay(RateSource $rateSource, \DateTimeImmutable $date): \DateTimeImmutable
    {
        $correctedDayKey = sprintf(self::CACHE_KEY_CORRECTED_DAY, $rateSource->value, $date->format('Y-m-d'));
        $correctedDay = $this->cache->get($correctedDayKey);
        if ($correctedDay) {
            return new \DateTimeImmutable($correctedDay);
        }

        return $date;
    }

    private function calculateCrossRate(RateSourceInterface $source, \DateTimeImmutable $date, string $currency, string $baseCurrency): RateResponse
    {
        $sourceBaseCurrency = $source->getBaseCurrency();

        $targetToSourceBase = $this->getRate($date, $currency, $sourceBaseCurrency, $source->getEnum());
        $baseToSourceBase = $this->getRate($date, $baseCurrency, $sourceBaseCurrency, $source->getEnum());

        // Rate = Target / Base
        $rate = bcdiv($targetToSourceBase->rate, $baseToSourceBase->rate, $this->currencyPrecision);

        // Calculate previous rate for diff
        $targetPrev = $this->getPreviousValue($targetToSourceBase);
        $basePrev = $this->getPreviousValue($baseToSourceBase);

        $isFallback = $targetToSourceBase->isFallback || $baseToSourceBase->isFallback;
        $diff = null;
        $dateDiff = null;
        if (null !== $targetPrev && null !== $basePrev && 0 !== bccomp($basePrev, '0', $this->currencyPrecision)) {
            $prevRate = bcdiv($targetPrev, $basePrev, $this->currencyPrecision);
            $diff = bcsub($rate, $prevRate, $this->currencyPrecision);
            // Use the dateDiff from the target rate as the reference date for the diff
            $dateDiff = $targetToSourceBase->dateDiff;
        } else {
            $isFallback = true;
        }

        return new RateResponse(
            rate: $rate,
            diff: $diff,
            date: $targetToSourceBase->date,
            dateDiff: $dateDiff,
            isFallback: $isFallback,
        );
    }

    private function getPreviousValue(RateResponse $response): ?string
    {
        if (null === $response->diff) {
            return null;
        }

        return bcsub($response->rate, $response->diff, $this->currencyPrecision);
    }

    private function fetchDirectRate(RateSourceInterface $source, \DateTimeImmutable $date, string $currency): RateResponse
    {
        $rateEntity = $this->repository->findOneByDateAndCurrency($date, $currency, $source->getBaseCurrency(), $source->getId());

        if (!$rateEntity) {
            $this->bus->dispatch(new FetchRateMessage(
                $date,
                $source->getEnum(),
            ));
            $this->bus->dispatch(new FetchRateMessage(
                $date->modify('-1 day'),
                $source->getEnum(),
            ));
            throw new RateNotFoundException($currency, $source->getBaseCurrency());
        }

        return $this->createResponse($source, $rateEntity);
    }

    private function createResponse(RateSourceInterface $source, ExchangeRate $rateEntity): RateResponse
    {
        $isFallback = false;
        $diff = null;
        $dateDiff = null;
        $previousDate = $rateEntity->getDate()->modify('-1 day');
        $previousDate = $this->getCorrectedDay($source->getEnum(), $previousDate);

        $previousRate = $this->repository->findOneByDateAndCurrency($previousDate, $rateEntity->getCurrency(), $rateEntity->getBaseCurrency(), $source->getId());

        if ($previousRate) {
            $diff = bcsub($rateEntity->getRate(), $previousRate->getRate(), $this->currencyPrecision);
            $dateDiff = $previousRate->getDate()->format('Y-m-d');
        } else {
            $isFallback = true;
            $this->bus->dispatch(new FetchRateMessage(
                $previousDate,
                $source->getEnum(),
            ));
        }

        return new RateResponse(
            rate: $rateEntity->getRate(),
            diff: $diff,
            date: $rateEntity->getDate()->format('Y-m-d'),
            dateDiff: $dateDiff,
            isFallback: $isFallback,
        );
    }
}
