<?php

declare(strict_types=1);

namespace App\Repository;

use App\Contract\RateDataInterface;
use App\DTO\RateExtendData;
use App\Entity\RateExtend;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AbstractRateRepository<RateExtend>
 */
class RateExtendRepository extends AbstractRateRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RateExtend::class);
    }

    protected function getInsertColumns(): array
    {
        return ['rate_open', 'rate_low', 'rate_high', 'rate', 'volume'];
    }

    protected function getInsertPlaceholders(): string
    {
        return '?, ?, ?, ?, ?';
    }

    protected function getInsertValues(RateDataInterface $data): array
    {
        if (!$data instanceof RateExtendData) {
            throw new \InvalidArgumentException('Expected RateExtendData, got '.get_class($data));
        }

        return [
            $data->open,
            $data->low,
            $data->high,
            $data->close,
            $data->volume,
        ];
    }
}
