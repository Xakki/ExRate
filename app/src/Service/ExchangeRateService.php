<?php

namespace App\Service;

use App\Entity\ExchangeRate;
use App\Repository\ExchangeRateRepository;
use Doctrine\ORM\EntityManagerInterface;

class ExchangeRateService
{
    public function __construct(
        private CbrRateSource $rateSource,
        private ExchangeRateRepository $repository,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @return array{rate: string, diff: ?string, date: string}
     */
    public function getRate(\DateTimeImmutable $date, string $currency, string $baseCurrency): array
    {
        // 1. Try to get from DB
        $currentRate = $this->repository->findOneByDateAndCurrency($date, $currency, $baseCurrency);

        if (!$currentRate) {
            // 2. If not in DB, fetch from CBR
            try {
                $rateValue = $this->fetchFromProvider($date, $currency, $baseCurrency);

                $currentRate = new ExchangeRate();
                $currentRate->setDate($date);
                $currentRate->setCurrency($currency);
                $currentRate->setBaseCurrency($baseCurrency);
                $currentRate->setRate((string) $rateValue);

                $this->em->persist($currentRate);
                $this->em->flush();
            } catch (\Exception $e) {
                // Fallback: get latest available before this date
                $currentRate = $this->repository->findLatestBeforeDate($date, $currency, $baseCurrency);
                if (!$currentRate) {
                    throw new \RuntimeException('Rate not found and no fallback available.', 0, $e);
                }
            }
        }

        // 3. Calculate Diff
        $previousRate = $this->repository->findLatestBeforeDate($currentRate->getDate(), $currency, $baseCurrency);
        $diff = null;

        if ($previousRate) {
            $diff = bcsub($currentRate->getRate(), $previousRate->getRate(), 4);
        }

        return [
            'rate' => $currentRate->getRate(),
            'diff' => $diff,
            'date' => $currentRate->getDate()->format('Y-m-d'),
        ];
    }

    private function fetchFromProvider(\DateTimeImmutable $date, string $currency, string $baseCurrency): float
    {
        // Currently only supports RUB as base currency for CBR
        if ('RUB' !== $baseCurrency) {
            throw new \RuntimeException('CBR source only supports RUB as base currency');
        }

        $rate = $this->rateSource->getRate($date, $currency);

        if (null === $rate) {
            throw new \RuntimeException(sprintf('Rate for %s not found in CBR response', $currency));
        }

        return $rate;
    }
}
