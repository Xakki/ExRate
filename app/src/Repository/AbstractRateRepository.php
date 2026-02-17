<?php

declare(strict_types=1);

namespace App\Repository;

use App\Contract\ProviderRateInterface;
use App\Contract\RateDataInterface;
use App\Contract\RateEntityInterface;
use App\Contract\RateRepositoryInterface;
use App\Util\Date;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @template T of RateEntityInterface
 *
 * @extends ServiceEntityRepository<T>
 */
abstract class AbstractRateRepository extends ServiceEntityRepository implements RateRepositoryInterface
{
    /**
     * First item is closet day, second item is previous closest day.
     *
     * @return T[]
     */
    public function findTwoLastRates(ProviderRateInterface $provider, string $currency, string $baseCurrency, \DateTimeImmutable $maxDate, ?\DateTimeImmutable $minDate = null): array
    {
        $builder = $this->createQueryBuilder('r')
            ->where('r.providerId = :providerId')
            ->andWhere('r.currency = :currency')
            ->andWhere('r.baseCurrency = :baseCurrency')
            ->andWhere('r.date <= :maxDate')
            ->setParameter('providerId', $provider->getId())
            ->setParameter('currency', $currency)
            ->setParameter('baseCurrency', $baseCurrency)
            ->setParameter('maxDate', $maxDate->format(Date::FORMAT));

        if ($minDate) {
            $builder->andWhere('r.date >= :minDate')
                ->setParameter('minDate', $minDate->format(Date::FORMAT));
        }

        return $builder->orderBy('r.date', 'DESC')
            ->setMaxResults(2)
            ->getQuery()
            ->getResult();
    }

    public function findOneByDateRange(ProviderRateInterface $provider, string $baseCurrency, \DateTimeImmutable $minDate, \DateTimeImmutable $maxDate): ?RateEntityInterface
    {
        return $this->createQueryBuilder('r')
            ->where('r.providerId = :providerId')
            ->andWhere('r.baseCurrency = :baseCurrency')
            ->andWhere('r.date <= :maxDate')
            ->setParameter('providerId', $provider->getId())
            ->setParameter('baseCurrency', $baseCurrency)
            ->setParameter('maxDate', $maxDate->format(Date::FORMAT))
            ->andWhere('r.date >= :minDate')
            ->setParameter('minDate', $minDate->format(Date::FORMAT))
            ->orderBy('r.date', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return T[]
     */
    public function findRatesByPeriod(ProviderRateInterface $provider, string $currency, string $baseCurrency, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        if (Date::getDayDiff($start, $end) < 0) {
            throw new \DateRangeError();
        }

        return $this->createQueryBuilder('r')
            ->where('r.providerId = :providerId')
            ->andWhere('r.currency = :currency')
            ->andWhere('r.baseCurrency = :baseCurrency')
            ->andWhere('r.date >= :start')
            ->andWhere('r.date <= :end')
            ->setParameter('providerId', $provider->getId())
            ->setParameter('currency', $currency)
            ->setParameter('baseCurrency', $baseCurrency)
            ->setParameter('start', $start->format(Date::FORMAT))
            ->setParameter('end', $end->format(Date::FORMAT))
            ->orderBy('r.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getMinDate(ProviderRateInterface $provider): ?\DateTimeImmutable
    {
        $qb = $this->createQueryBuilder('r')
            ->select('MIN(r.date)')
            ->where('r.providerId = :providerId')
                ->setParameter('providerId', $provider->getId());

        $result = $qb->getQuery()
            ->enableResultCache(3600)
            ->getSingleScalarResult();

        return $result ? Date::createFromFormat(Date::FORMAT, (string) $result) : null;
    }

    public function getMaxDate(ProviderRateInterface $provider): ?\DateTimeImmutable
    {
        $qb = $this->createQueryBuilder('r')
            ->select('MAX(r.date)')
            ->where('r.providerId = :providerId')
                ->setParameter('providerId', $provider->getId());

        $result = $qb->getQuery()
            ->enableResultCache(3600)
            ->getSingleScalarResult();

        return $result ? Date::createFromFormat(Date::FORMAT, (string) $result) : null;
    }

    /**
     * @param array<string, RateDataInterface> $rates
     */
    public function saveRatesBatch(ProviderRateInterface $provider, string $baseCurrency, \DateTimeImmutable $date, array $rates): void
    {
        if (empty($rates)) {
            return;
        }

        $em = $this->getEntityManager();
        $connection = $em->getConnection();
        $createdAt = (new \DateTimeImmutable())->format(Date::FORMAT_TIME);
        $formattedDate = $date->format(Date::FORMAT);
        $table = $this->getClassMetadata()->getTableName();

        $columns = $this->getInsertColumns();
        $placeholders = $this->getInsertPlaceholders();

        $baseQuery = 'INSERT IGNORE INTO '.$table.' (created_at, date, currency, base_currency, provider_id, '.implode(', ', $columns).') VALUES ';

        $config = $connection->getConfiguration();
        $logger = method_exists($config, 'getSQLLogger') ? $config->getSQLLogger() : null;
        if (method_exists($config, 'setSQLLogger')) {
            $config->setSQLLogger(null);
        }

        try {
            $em->wrapInTransaction(function () use ($rates, $connection, $baseQuery, $createdAt, $formattedDate, $baseCurrency, $provider, $placeholders) {
                foreach (array_chunk($rates, 200, true) as $chunk) {
                    $values = [];
                    $params = [];

                    foreach ($chunk as $currency => $data) {
                        $values[] = '(?, ?, ?, ?, ?, '.$placeholders.')';
                        $params[] = $createdAt;
                        $params[] = $formattedDate;
                        $params[] = $currency;
                        $params[] = $baseCurrency;
                        $params[] = $provider->getId();
                        foreach ($this->getInsertValues($data) as $val) {
                            $params[] = $val;
                        }
                    }

                    $connection->executeStatement($baseQuery.implode(', ', $values), $params);
                }
            });
        } finally {
            if (method_exists($config, 'setSQLLogger')) {
                $config->setSQLLogger($logger);
            }
        }
    }

    /**
     * @return string[]
     */
    abstract protected function getInsertColumns(): array;

    abstract protected function getInsertPlaceholders(): string;

    /**
     * @return array<int, mixed>
     */
    abstract protected function getInsertValues(RateDataInterface $data): array;
}
