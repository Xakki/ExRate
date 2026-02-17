<?php

declare(strict_types=1);

namespace App\Response;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'Currency',
    description: 'Information about a fiat currency.'
)]
readonly class CurrencyResponse
{
    public function __construct(
        #[OA\Property(description: 'Currency code', example: 'USD')]
        public string $code,
        #[OA\Property(description: 'Currency symbol', example: '$')]
        public string $symbol,
        #[OA\Property(description: 'Currency name', example: 'US Dollar')]
        public string $name,
        #[OA\Property(description: 'Countries using this currency', example: 'United States, Ecuador, El Salvador')]
        public string $countries,
    ) {
    }
}
