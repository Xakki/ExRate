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

    public function findOneByDateAndCurrency(\DateTimeImmutable $date, string $currency, string $baseCurrency): ?ExchangeRate
    {
        return $this->findOneBy([
            'date' => $date,
            'currency' => $currency,
            'baseCurrency' => $baseCurrency,
        ]);
    }

    public function findLatestBeforeDate(\DateTimeImmutable $date, string $currency, string $baseCurrency): ?ExchangeRate
    {
        return $this->createQueryBuilder('e')
            ->where('e.date < :date')
            ->andWhere('e.currency = :currency')
            ->andWhere('e.baseCurrency = :baseCurrency')
            ->setParameter('date', $date)
            ->setParameter('currency', $currency)
            ->setParameter('baseCurrency', $baseCurrency)
            ->orderBy('e.date', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
