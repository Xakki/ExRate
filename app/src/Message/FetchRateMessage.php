<?php

namespace App\Message;

class FetchRateMessage
{
    public function __construct(
        public string $date,
        public string $currency,
        public string $baseCurrency,
        public int $retryCount = 0,
    ) {
    }
}
