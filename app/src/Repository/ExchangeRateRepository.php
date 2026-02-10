<?php

declare(strict_types=1);

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

    public function findOneByDateAndCurrency(int $providerId, string $currency, string $baseCurrency, \DateTimeImmutable $date): ?ExchangeRate
    {
        return $this->findOneBy([
            'date' => $date,
            'currency' => $currency,
            'baseCurrency' => $baseCurrency,
            'providerId' => $providerId,
        ]);
    }

    public function existRates(int $providerId, string $baseCurrency, \DateTimeImmutable $date): bool
    {
        return null !== $this->findOneBy([
            'date' => $date,
            'baseCurrency' => $baseCurrency,
            'providerId' => $providerId,
        ]);
    }

    /**
     * @return string[]
     */
    public function findExistingCurrencyCodes(int $providerId, string $baseCurrency, \DateTimeImmutable $date): array
    {
        $results = $this->createQueryBuilder('er')
            ->select('er.currency')
            ->where('er.providerId = :providerId')
            ->andWhere('er.baseCurrency = :baseCurrency')
            ->andWhere('er.date = :date')
            ->setParameter('providerId', $providerId)
            ->setParameter('baseCurrency', $baseCurrency)
            ->setParameter('date', $date)
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'currency');
    }

    /**
     * @return ExchangeRate[]
     */
    public function findRatesByPeriod(int $providerId, string $currency, string $baseCurrency, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->createQueryBuilder('er')
            ->where('er.providerId = :providerId')
            ->andWhere('er.currency = :currency')
            ->andWhere('er.baseCurrency = :baseCurrency')
            ->andWhere('er.date >= :start')
            ->andWhere('er.date <= :end')
            ->setParameter('providerId', $providerId)
            ->setParameter('currency', $currency)
            ->setParameter('baseCurrency', $baseCurrency)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('er.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array<string, string> $rates
     */
    public function saveRatesBatch(int $providerId, string $baseCurrency, \DateTimeImmutable $date, array $rates): void
    {
        if (empty($rates)) {
            return;
        }

        $em = $this->getEntityManager();
        $connection = $em->getConnection();
        $createdAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $formattedDate = $date->format('Y-m-d');

        $query = 'INSERT IGNORE INTO exchange_rates (date, currency, base_currency, rate, provider_id, created_at) VALUES ';
        $values = [];
        $params = [];

        foreach ($rates as $currency => $rateValue) {
            $values[] = '(?, ?, ?, ?, ?, ?)';
            $params[] = $formattedDate;
            $params[] = $currency;
            $params[] = $baseCurrency;
            $params[] = $rateValue;
            $params[] = $providerId;
            $params[] = $createdAt;
        }

        $query .= implode(', ', $values);

        $em->wrapInTransaction(fn () => $connection->executeStatement($query, $params));
    }
}
