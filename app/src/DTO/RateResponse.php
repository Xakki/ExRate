<?php

declare(strict_types=1);

namespace App\DTO;

use OpenApi\Attributes as OA;

#[OA\Schema(
    description: 'Response containing the exchange rate, its difference from the previous day, and related metadata.'
)]
readonly class RateResponse
{
    #[OA\Property(description: 'Timestamp of when the data was fetched/cached', example: '2026-02-10T12:00:00+00:00')]
    public string $timestamp;

    public function __construct(
        #[OA\Property(description: 'The exchange rate value', example: '77.65020000')]
        public string $rate,
        #[OA\Property(description: 'Difference with the previous trading day', example: '0.59620000')]
        public ?string $diff,
        #[OA\Property(description: 'Date of the exchange rate', example: '2026-02-10')]
        public string $date,
        #[OA\Property(description: 'Date of the previous exchange rate used for diff', example: '2026-02-07')]
        public ?string $dateDiff = null,
    ) {
        $this->timestamp = (new \DateTimeImmutable())->format(\DateTimeImmutable::ATOM);
    }
}
