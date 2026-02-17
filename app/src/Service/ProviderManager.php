<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\Cache\RateCacheInterface;
use App\Contract\Cache\TimeseriesCacheInterface;
use App\Contract\ProviderRateInterface;
use App\Contract\RateRepositoryInterface;
use App\Enum\FrequencyEnum;
use App\Enum\ProviderEnum;
use App\Exception\RateNotFoundException;
use App\Response\RateResponse;
use App\Response\TimeseriesResponse;
use App\Util\BcMath;
use App\Util\Date;

readonly class ProviderManager
{
    public function __construct(
        private ProviderRegistry $providerRegistry,
        private RateRepositoryInterface $repository,
        private RateCacheInterface $rateCache,
        private TimeseriesCacheInterface $timeseriesCache,
        private int $currencyPrecision,
    ) {
    }

    public function getTimeseries(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        string $currency,
        string $baseCurrency,
        ProviderEnum $providerEnum = ProviderEnum::ECB,
        FrequencyEnum $group = FrequencyEnum::Daily,
    ): TimeseriesResponse {
        if ($cached = $this->timeseriesCache->get($start, $end, $providerEnum, $baseCurrency, $currency, $group)) {
            return $cached;
        }

        $provider = $this->providerRegistry->get($providerEnum);
        $providerBaseCurrency = $provider->getBaseCurrency();

        $rates = [];
        if ($providerBaseCurrency === $currency) {
            $rates = $this->getRatesMap($provider, $baseCurrency, $currency, $start, $end, true);
        } elseif ($providerBaseCurrency !== $baseCurrency) {
            // Cross rate logic
            $targetRates = $this->getRatesMap($provider, $currency, $providerBaseCurrency, $start, $end);
            $baseRates = $this->getRatesMap($provider, $baseCurrency, $providerBaseCurrency, $start, $end);

            foreach ($targetRates as $date => $targetRate) {
                if (isset($baseRates[$date]) && 0 !== BcMath::comp($baseRates[$date], '0', $this->currencyPrecision)) {
                    $rates[$date] = BcMath::div($targetRate, $baseRates[$date], $this->currencyPrecision);
                }
            }
        } else {
            $rates = $this->getRatesMap($provider, $currency, $baseCurrency, $start, $end);
        }

        if (FrequencyEnum::Daily !== $group) {
            $rates = $this->groupRates($rates, $group);
        }

        $response = new TimeseriesResponse(
            baseCurrency: $baseCurrency,
            currency: $currency,
            startDate: $start->format(Date::FORMAT),
            endDate: $end->format(Date::FORMAT),
            rates: $rates
        );

        $this->timeseriesCache->set($start, $end, $providerEnum, $baseCurrency, $currency, $response, $group);

        return $response;
    }

    /**
     * @param array<string, string> $rates
     *
     * @return array<string, string>
     */
    private function groupRates(array $rates, FrequencyEnum $group): array
    {
        $grouped = [];
        foreach ($rates as $date => $rate) {
            $dt = new \DateTimeImmutable($date);
            if (FrequencyEnum::Weekly === $group) {
                $key = $dt->modify('monday this week')->format(Date::FORMAT);
            } elseif (FrequencyEnum::Monthly === $group) {
                $key = $dt->format('Y-m-01');
            } else {
                $key = $date;
            }

            $grouped[$key] = $rate;
        }

        return $grouped;
    }

    /**
     * @return array<string, string>
     */
    private function getRatesMap(ProviderRateInterface $provider, string $currency, string $baseCurrency, \DateTimeImmutable $start, \DateTimeImmutable $end, bool $invert = false): array
    {
        $entities = $this->repository->findRatesByPeriod($provider, $currency, $baseCurrency, $start, $end);
        $map = [];
        foreach ($entities as $entity) {
            $map[$entity->getDate()->format(Date::FORMAT)] = $entity->getRate($invert);
        }

        return $map;
    }

    public function getRate(\DateTimeImmutable $date, string $currency, string $baseCurrency, ProviderEnum $providerEnum = ProviderEnum::ECB): RateResponse
    {
        if ($cacheResponse = $this->rateCache->get($date, $providerEnum, $baseCurrency, $currency)) {
            return $cacheResponse;
        }

        $provider = $this->providerRegistry->get($providerEnum);
        $providerBaseCurrency = $provider->getBaseCurrency();

        if ($providerBaseCurrency === $currency) {
            $response = $this->fetchDirectRate($provider, $date, $baseCurrency, true);
        } elseif ($providerBaseCurrency !== $baseCurrency) {
            $response = $this->calculateCrossRate($provider, $date, $currency, $baseCurrency);
        } else {
            $response = $this->fetchDirectRate($provider, $date, $currency);
        }

        if ($response->isFullData()) {
            // Если данные полные - кешируем
            $this->rateCache->set($date, $providerEnum, $baseCurrency, $currency, $response);
        }

        return $response;
    }

    private function calculateCrossRate(ProviderRateInterface $provider, \DateTimeImmutable $date, string $currency, string $baseCurrency): RateResponse
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
            date: $targetTo->date,
            diff: $diff,
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

    private function fetchDirectRate(ProviderRateInterface $provider, \DateTimeImmutable $date, string $currency, bool $invert = false): RateResponse
    {
        $rates = $this->repository->findTwoLastRates($provider, $currency, $provider->getBaseCurrency(), $date);

        if (!$rates) {
            throw new RateNotFoundException($currency, $provider->getBaseCurrency());
        }

        $diff = null;
        $dateDiff = null;

        if (isset($rates[1])) {
            $current = $this->requireNumericString($rates[0]->getRate($invert), 'current rate');
            $previous = $this->requireNumericString($rates[1]->getRate($invert), 'previous rate');
            $diff = BcMath::sub($current, $previous, $this->currencyPrecision);
            $dateDiff = $rates[1]->getDate()->format(Date::FORMAT);
        }

        return new RateResponse(
            rate: $rates[0]->getRate($invert),
            date: $rates[0]->getDate()->format(Date::FORMAT),
            diff: $diff,
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
