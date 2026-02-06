<?php

namespace App\DTO;

use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class RateRequest
{
    #[Assert\Date]
    #[OA\Property(description: 'Date in YYYY-MM-DD format. Min: 1993-01-29, Max: today', format: 'date', example: '2025-02-06')]
    public ?string $date = null;

    #[Assert\NotBlank]
    #[Assert\Currency]
    #[OA\Property(description: 'Target currency code (ISO 4217)', example: 'USD')]
    public string $currency;

    #[Assert\Currency]
    #[OA\Property(description: 'Base currency code (ISO 4217)', example: 'RUB')]
    public string $baseCurrency = 'RUB';

    #[Assert\Callback]
    public function validateDateRange(ExecutionContextInterface $context): void
    {
        if (null === $this->date) {
            return;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $this->date);

        if (false === $date) {
            return;
        }

        $date = $date->setTime(0, 0);
        $min = new \DateTimeImmutable('1993-01-29');
        $max = new \DateTimeImmutable('today');

        if ($date < $min) {
            $context->buildViolation('Date must be greater than or equal to 1993-01-29.')
                ->atPath('date')
                ->addViolation();
        }

        if ($date > $max) {
            $context->buildViolation('Date must be less than or equal to today.')
                ->atPath('date')
                ->addViolation();
        }
    }

    #[Ignore]
    public function getDateImmutable(): \DateTimeImmutable
    {
        return $this->date
            ? \DateTimeImmutable::createFromFormat('Y-m-d', $this->date)->setTime(0, 0)
            : new \DateTimeImmutable('today');
    }
}
