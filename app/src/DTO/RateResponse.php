<?php

declare(strict_types=1);

namespace App\DTO;

use OpenApi\Attributes as OA;

#[OA\Schema(
    description: 'Response containing the exchange rate, its difference from the previous day, and related metadata.'
)]
readonly class RateResponse
{
    #[OA\Property(description: 'Timestamp of when the data was fetched/cached', example: '2024-03-20T12:00:00+00:00')]
    public string $timestamp;

    public function __construct(
        #[OA\Property(description: 'The exchange rate value', example: '92.50')]
        public string $rate,
        #[OA\Property(description: 'Difference with the previous trading day', example: '-0.50')]
        public ?string $diff,
        #[OA\Property(description: 'Date of the exchange rate', example: '2024-03-20')]
        public string $date,
        #[OA\Property(description: 'Date of the previous exchange rate used for diff', example: '2024-03-19')]
        public ?string $dateDiff = null,
        #[OA\Property(description: 'Indicates if the rate diff is not ready (repeat request later)', example: false)]
        public bool $isFallback = false,
    ) {
        $this->timestamp = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
    }
}
