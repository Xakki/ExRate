<?php

declare(strict_types=1);

namespace App\DTO;

use OpenApi\Attributes as OA;

#[OA\Schema(
    description: 'Response containing the exchange rates for a specified period.'
)]
readonly class TimeseriesResponse
{
    /**
     * @param array<string, string> $rates Map of date (YYYY-MM-DD) to exchange rate value
     */
    public function __construct(
        #[OA\Property(description: 'Base currency code', example: 'RUB')]
        public string $baseCurrency,
        #[OA\Property(description: 'Target currency code', example: 'USD')]
        public string $currency,
        #[OA\Property(description: 'Requested start date', example: '2026-02-01')]
        public string $startDate,
        #[OA\Property(description: 'Requested end date', example: '2026-02-15')]
        public string $endDate,
        #[OA\Property(
            description: 'Map of date to exchange rate',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(type: 'string'),
            example: ['2026-01-03' => '77.02230000', '2026-02-04' => '76.98170000']
        )]
        public array $rates,
    ) {
    }
}
