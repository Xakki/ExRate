<?php

declare(strict_types=1);

namespace App\DTO;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'Provider',
    description: 'Information about an exchange rate provider.'
)]
readonly class Provider
{
    /**
     * @param string[] $currencies
     */
    public function __construct(
        #[OA\Property(description: 'Unique key of the provider', example: 'cbr')]
        public string $key,
        #[OA\Property(description: 'Official website of the provider', example: 'https://www.cbr.ru')]
        public string $homePage,
        #[OA\Property(description: 'Description of the provider', example: 'Central Bank of the Russian Federation')]
        public string $description,
        #[OA\Property(description: 'Base currency used by the provider', example: 'RUB')]
        public string $baseCurrency,
        #[OA\Property(
            description: 'List of available currency codes for this provider',
            type: 'array',
            items: new OA\Items(type: 'string'),
            example: ['USD', 'EUR']
        )]
        public array $currencies,
        #[OA\Property(description: 'Minimum available date in the database', format: 'date', example: '1993-01-29')]
        public ?string $minDate = null,
    ) {
    }
}
