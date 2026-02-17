<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\ProviderEnum;
use App\Validator\Constraints\MinDate;
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
    #[Assert\Length(min: 2, max: 10)]
    #[OA\Property(description: 'Target currency code', maxLength: 10, minLength: 2, example: 'USD')]
    public string $currency = '';

    #[Assert\Length(min: 2, max: 10)]
    #[OA\Property(description: 'Base currency code', maxLength: 10, minLength: 2, example: 'EUR')]
    public string $baseCurrency = 'EUR';

    #[Assert\Choice(callback: [ProviderEnum::class, 'cases'])]
    #[OA\Property(description: 'Data provider', type: 'string', enum: [ProviderEnum::ECB])]
    public ProviderEnum $provider = ProviderEnum::ECB;

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

        // Validate min date using custom constraint that has access to the repository
        $context->getValidator()
            ->inContext($context)
            ->atPath('date')
            ->validate($this->date, new MinDate(provider: $this->provider));

        $max = new \DateTimeImmutable('today');

        if ($date->diff($max)->invert) {
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
