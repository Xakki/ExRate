<?php

declare(strict_types=1);

namespace App\Response;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'CryptoCurrency',
    description: 'Information about a cryptocurrency.'
)]
readonly class CryptoCurrencyResponse
{
    public function __construct(
        #[OA\Property(description: 'Cryptocurrency code', example: 'BTC')]
        public string $code,
        #[OA\Property(description: 'Cryptocurrency name', example: 'Bitcoin')]
        public string $name,
        #[OA\Property(description: 'Icon path', example: 'icons/crypto/btc.svg')]
        public string $icon,
    ) {
    }
}
