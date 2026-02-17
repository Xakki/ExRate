<?php

declare(strict_types=1);

namespace App\Exception;

class RetryByDateException extends \ErrorException
{
    public function __construct(public readonly \DateTimeImmutable $availableDate)
    {
        parent::__construct();
    }
}
