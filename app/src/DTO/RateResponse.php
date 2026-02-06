<?php

namespace App\DTO;

use OpenApi\Attributes as OA;

class RateResponse
{
    #[OA\Property(description: 'The exchange rate value', example: '92.50')]
    public string $rate;

    #[OA\Property(description: 'Difference with the previous trading day', example: '-0.50')]
    public ?string $diff;

    #[OA\Property(description: 'Date of the exchange rate', example: '2024-03-20')]
    public string $date;

    #[OA\Property(description: 'Timestamp of when the data was fetched/cached', example: '2024-03-20T12:00:00+00:00')]
    public string $timestamp;

    public function __construct(string $rate, ?string $diff, string $date)
    {
        $this->rate = $rate;
        $this->diff = $diff;
        $this->date = $date;
        $this->timestamp = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
    }
}
