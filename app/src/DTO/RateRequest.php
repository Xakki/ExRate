<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\ProviderEnum;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Request DTO for fetching exchange rates.
 */
class RateRequest
{
    #[Assert\Date]
    #[OA\Property(description: 'Date in YYYY-MM-DD format. Min: 1993-01-29, Max: today', format: 'date', default: 'today', example: '2025-02-06')]
    public ?string $date = null;

    #[Assert\NotBlank]
    #[Assert\Currency]
    #[OA\Property(description: 'Target currency code (ISO 4217)', maxLength: 3, minLength: 3, example: 'USD')]
    public string $currency = '';

    #[Assert\Currency]
    #[OA\Property(description: 'Base currency code (ISO 4217)', maxLength: 3, minLength: 3, example: 'RUB')]
    public string $baseCurrency = 'RUB';

    #[Assert\Choice(callback: [ProviderEnum::class, 'cases'])]
    #[OA\Property(description: 'Data provider', type: 'string', enum: [ProviderEnum::CBR])]
    public ProviderEnum $provider = ProviderEnum::CBR;

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
        if (null === $this->date) {
            return new \DateTimeImmutable('today');
        }

        $parsedDate = \DateTimeImmutable::createFromFormat('Y-m-d', $this->date);

        if (false === $parsedDate) {
            return new \DateTimeImmutable('today');
        }

        return $parsedDate->setTime(0, 0);
    }
}
