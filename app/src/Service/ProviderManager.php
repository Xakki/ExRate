<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\Cache\RateCacheInterface;
use App\Contract\Cache\SkipDayCacheInterface;
use App\Contract\Cache\TimeseriesCacheInterface;
use App\Contract\ProviderInterface;
use App\DTO\RateResponse;
use App\DTO\TimeseriesResponse;
use App\Entity\ExchangeRate;
use App\Enum\ProviderEnum;
use App\Exception\RateNotFoundException;
use App\Repository\ExchangeRateRepository;
use App\Util\BcMath;

readonly class ProviderManager
{
    public function __construct(
        private ProviderRegistry $providerRegistry,
        private ExchangeRateRepository $repository,
        private RateCacheInterface $rateCache,
        private SkipDayCacheInterface $correctedDayCache,
        private TimeseriesCacheInterface $timeseriesCache,
        private int $currencyPrecision,
    ) {
    }

    public function getTimeseries(\DateTimeImmutable $start, \DateTimeImmutable $end, string $currency, string $baseCurrency, ProviderEnum $providerEnum = ProviderEnum::CBR): TimeseriesResponse
    {
        if ($cached = $this->timeseriesCache->get($start, $end, $providerEnum, $baseCurrency, $currency)) {
            return $cached;
        }

        $provider = $this->providerRegistry->get($providerEnum);
        $providerBaseCurrency = $provider->getBaseCurrency();

        $rates = [];
        if ($providerBaseCurrency !== $baseCurrency) {
            // Cross rate logic
            $targetRates = $this->getRatesMap($provider->getId(), $currency, $providerBaseCurrency, $start, $end);
            $baseRates = $this->getRatesMap($provider->getId(), $baseCurrency, $providerBaseCurrency, $start, $end);

            foreach ($targetRates as $date => $targetRate) {
                if (isset($baseRates[$date]) && 0 !== BcMath::comp($baseRates[$date], '0', $this->currencyPrecision)) {
                    $rates[$date] = BcMath::div($targetRate, $baseRates[$date], $this->currencyPrecision);
                }
            }
        } else {
            $rates = $this->getRatesMap($provider->getId(), $currency, $baseCurrency, $start, $end);
        }

        $response = new TimeseriesResponse(
            baseCurrency: $baseCurrency,
            currency: $currency,
            startDate: $start->format('Y-m-d'),
            endDate: $end->format('Y-m-d'),
            rates: $rates
        );

        $this->timeseriesCache->set($start, $end, $providerEnum, $baseCurrency, $currency, $response);

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private function getRatesMap(int $providerId, string $currency, string $baseCurrency, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $entities = $this->repository->findRatesByPeriod($providerId, $currency, $baseCurrency, $start, $end);
        $map = [];
        foreach ($entities as $entity) {
            $map[$entity->getDate()->format('Y-m-d')] = $entity->getRate();
        }

        return $map;
    }

    public function getRate(\DateTimeImmutable $date, string $currency, string $baseCurrency, ProviderEnum $providerEnum = ProviderEnum::CBR): RateResponse
    {
        $date = $this->getCorrectedDay($providerEnum, $date);

        if ($cacheResponse = $this->rateCache->get($date, $providerEnum, $baseCurrency, $currency)) {
            return $cacheResponse;
        }

        $provider = $this->providerRegistry->get($providerEnum);
        $providerBaseCurrency = $provider->getBaseCurrency();

        if ($providerBaseCurrency !== $baseCurrency) {
            $response = $this->calculateCrossRate($provider, $date, $currency, $baseCurrency);
        } else {
            $response = $this->fetchDirectRate($provider, $date, $currency);
        }

        if (null !== $response->diff) {
            $this->rateCache->set($date, $providerEnum, $baseCurrency, $currency, $response);
        }

        return $response;
    }

    public function getCorrectedDay(ProviderEnum $providerEnum, \DateTimeImmutable $date): \DateTimeImmutable
    {
        $correctedDay = $this->correctedDayCache->get($providerEnum, $date);
        if ($correctedDay) {
            return $correctedDay;
        }

        return $date;
    }

    private function calculateCrossRate(ProviderInterface $provider, \DateTimeImmutable $date, string $currency, string $baseCurrency): RateResponse
    {
        $providerBaseCurrency = $provider->getBaseCurrency();

        $targetTo = $this->getRate($date, $currency, $providerBaseCurrency, $provider->getEnum());
        $baseTo = $this->getRate($date, $baseCurrency, $providerBaseCurrency, $provider->getEnum());

        // Rate = Target / Base
        $rate = BcMath::div($targetTo->rate, $baseTo->rate, $this->currencyPrecision);

        // Calculate previous rate for diff
        $targetPrev = $this->getPreviousValue($targetTo);
        $basePrev = $this->getPreviousValue($baseTo);

        $diff = null;
        $dateDiff = null;
        if (null !== $targetPrev && null !== $basePrev) {
            $targetPrevNumeric = $this->requireNumericString($targetPrev, 'target previous rate');
            $basePrevNumeric = $this->requireNumericString($basePrev, 'base previous rate');

            if (0 !== BcMath::comp($basePrevNumeric, '0', $this->currencyPrecision)) {
                $prevRate = BcMath::div($targetPrevNumeric, $basePrevNumeric, $this->currencyPrecision);
                $diff = BcMath::sub($rate, $prevRate, $this->currencyPrecision);
                // Use the dateDiff from the target rate as the reference date for the diff
                $dateDiff = $targetTo->dateDiff;
            }
        }

        return new RateResponse(
            rate: $rate,
            diff: $diff,
            date: $targetTo->date,
            dateDiff: $dateDiff,
        );
    }

    private function getPreviousValue(RateResponse $response): ?string
    {
        if (null === $response->diff) {
            return null;
        }

        $rate = $this->requireNumericString($response->rate, 'rate');
        $diff = $this->requireNumericString($response->diff, 'diff');

        return BcMath::sub($rate, $diff, $this->currencyPrecision);
    }

    private function fetchDirectRate(ProviderInterface $provider, \DateTimeImmutable $date, string $currency): RateResponse
    {
        $rateEntity = $this->repository->findOneByDateAndCurrency($provider->getId(), $currency, $provider->getBaseCurrency(), $date);

        if (!$rateEntity) {
            throw new RateNotFoundException($currency, $provider->getBaseCurrency());
        }

        return $this->createResponse($provider, $rateEntity);
    }

    private function createResponse(ProviderInterface $provider, ExchangeRate $rateEntity): RateResponse
    {
        $diff = null;
        $dateDiff = null;
        $previousDate = $rateEntity->getDate()->modify('-1 day');
        $previousDate = $this->getCorrectedDay($provider->getEnum(), $previousDate);

        $previousRate = $this->repository->findOneByDateAndCurrency($provider->getId(), $rateEntity->getCurrency(), $rateEntity->getBaseCurrency(), $previousDate);

        if ($previousRate) {
            $rate = $this->requireNumericString($rateEntity->getRate(), 'current rate');
            $previous = $this->requireNumericString($previousRate->getRate(), 'previous rate');
            $diff = BcMath::sub($rate, $previous, $this->currencyPrecision);
            $dateDiff = $previousRate->getDate()->format('Y-m-d');
        }

        return new RateResponse(
            rate: $rateEntity->getRate(),
            diff: $diff,
            date: $rateEntity->getDate()->format('Y-m-d'),
            dateDiff: $dateDiff,
        );
    }

    /**
     * @return numeric-string
     */
    private function requireNumericString(?string $value, string $field): string
    {
        if (null === $value || !is_numeric($value)) {
            throw new \LogicException(sprintf('Expected numeric-string for %s, got: %s', $field, (string) $value));
        }

        return $value;
    }
}
