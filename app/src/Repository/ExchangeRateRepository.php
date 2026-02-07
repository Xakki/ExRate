<?php

namespace App\Repository;

use App\Entity\ExchangeRate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExchangeRate>
 */
class ExchangeRateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExchangeRate::class);
    }

    public function findOneByDateAndCurrency(\DateTimeImmutable $date, string $currency, string $baseCurrency, int $sourceId): ?ExchangeRate
    {
        return $this->findOneBy([
            'date' => $date,
            'currency' => $currency,
            'baseCurrency' => $baseCurrency,
            'sourceId' => $sourceId,
        ]);
    }

    public function existRates(\DateTimeImmutable $date, string $baseCurrency, int $sourceId): bool
    {
        return null !== $this->findOneBy([
            'date' => $date,
            'baseCurrency' => $baseCurrency,
            'sourceId' => $sourceId,
        ]);
    }
}
