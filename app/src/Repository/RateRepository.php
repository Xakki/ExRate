<?php

declare(strict_types=1);

namespace App\Repository;

use App\Contract\RateDataInterface;
use App\DTO\RateData;
use App\Entity\Rate;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AbstractRateRepository<Rate>
 */
class RateRepository extends AbstractRateRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rate::class);
    }

    protected function getInsertColumns(): array
    {
        return ['rate'];
    }

    protected function getInsertPlaceholders(): string
    {
        return '?';
    }

    protected function getInsertValues(RateDataInterface $data): array
    {
        if (!$data instanceof RateData) {
            throw new \InvalidArgumentException('Expected RateData, got '.get_class($data));
        }

        return [$data->close];
    }
}
