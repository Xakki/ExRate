<?php

declare(strict_types=1);

namespace App\Request;

use App\Enum\FrequencyEnum;
use App\Enum\ProviderEnum;
use App\Util\Currencies;
use App\Util\Date;
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
    #[Assert\Length(min: 2, max: 10)]
    #[OA\Property(description: 'Target currency code', maxLength: 10, minLength: 2, example: Currencies::USD)]
    public string $currency = '';

    #[Assert\Length(min: 2, max: 10)]
    #[OA\Property(description: 'Base currency code', maxLength: 10, minLength: 2, example: Currencies::EUR)]
    public string $baseCurrency = Currencies::EUR;

    #[Assert\Choice(callback: [ProviderEnum::class, 'cases'])]
    #[OA\Property(description: 'Data provider', type: 'string', enum: [ProviderEnum::ECB], default: 'ecb')]
    public ProviderEnum $provider = ProviderEnum::ECB;

    #[Assert\Choice(callback: [FrequencyEnum::class, 'cases'])]
    #[OA\Property(description: 'Grouping period', type: 'string', enum: [FrequencyEnum::Daily, FrequencyEnum::Weekly, FrequencyEnum::Monthly], default: 'Daily')]
    public FrequencyEnum $group = FrequencyEnum::Daily;

    #[Assert\Callback]
    public function validateDateRange(ExecutionContextInterface $context): void
    {
        $start = Date::createFromFormat(Date::FORMAT, $this->startDate);
        $end = Date::createFromFormat(Date::FORMAT, $this->endDate);

        $dayDiff = Date::getDayDiff($start, $end);

        if ($dayDiff < 0) {
            $context->buildViolation('Start date must be before or equal to end date.')
                ->atPath('startDate')
                ->addViolation();
        }

        if ($dayDiff > 370 * 5) {
            $context->buildViolation('The maximum allowed range more than 5 years for free.')
                ->atPath('endDate')
                ->addViolation();
        }
    }

    #[Ignore]
    public function getStartDateImmutable(): \DateTimeImmutable
    {
        return Date::createFromFormat(Date::FORMAT, $this->startDate);
    }

    #[Ignore]
    public function getEndDateImmutable(): \DateTimeImmutable
    {
        return Date::createFromFormat(Date::FORMAT, $this->endDate);
    }
}
