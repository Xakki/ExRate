<?php

namespace App\Message;

use App\Enum\RateSource;

class FetchRateMessage
{
    public function __construct(
        public \DateTimeImmutable $date,
        public RateSource $rateSource = RateSource::CBR,
        public int $retryCount = 0,
    ) {
    }
}
