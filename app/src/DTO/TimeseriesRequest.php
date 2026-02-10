<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\ProviderEnum;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[OA\Schema(
    description: 'Request DTO for fetching timeseries exchange rates.'
)]
class TimeseriesRequest
{
    #[Assert\NotBlank]
    #[Assert\Date]
    #[OA\Property(description: 'Start date in YYYY-MM-DD format', format: 'date', example: '2026-02-01')]
    public string $startDate = '';

    #[Assert\NotBlank]
    #[Assert\Date]
    #[OA\Property(description: 'End date in YYYY-MM-DD format', format: 'date', example: '2026-02-15')]
    public string $endDate = '';

    #[Assert\NotBlank]
    #[Assert\Currency]
    #[OA\Property(description: 'Target currency code (ISO 4217)', maxLength: 3, minLength: 3, example: 'USD')]
    public string $currency = '';

    #[Assert\Currency]
    #[OA\Property(description: 'Base currency code (ISO 4217)', maxLength: 3, minLength: 3, example: 'RUB')]
    public string $baseCurrency = 'RUB';

    #[Assert\Choice(callback: [ProviderEnum::class, 'cases'])]
    #[OA\Property(description: 'Data provider', type: 'string', enum: [ProviderEnum::CBR], default: 'cbr')]
    public ProviderEnum $provider = ProviderEnum::CBR;

    #[Assert\Callback]
    public function validateDateRange(ExecutionContextInterface $context): void
    {
        $start = \DateTimeImmutable::createFromFormat('Y-m-d', $this->startDate);
        $end = \DateTimeImmutable::createFromFormat('Y-m-d', $this->endDate);

        if (false === $start || false === $end) {
            return;
        }

        if ($start > $end) {
            $context->buildViolation('Start date must be before or equal to end date.')
                ->atPath('startDate')
                ->addViolation();
        }

        $diff = $start->diff($end);
        if ($diff->y >= 5) {
            $context->buildViolation('The maximum allowed range is 5 years.')
                ->atPath('endDate')
                ->addViolation();
        }
    }

    #[Ignore]
    public function getStartDateImmutable(): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $this->startDate);

        if (false === $date) {
            throw new \UnexpectedValueException('Invalid start date format');
        }

        return $date->setTime(0, 0);
    }

    #[Ignore]
    public function getEndDateImmutable(): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $this->endDate);

        if (false === $date) {
            throw new \UnexpectedValueException('Invalid end date format');
        }

        return $date->setTime(0, 0);
    }
}
