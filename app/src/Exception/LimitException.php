<?php

declare(strict_types=1);

namespace App\Exception;

class LimitException extends \RuntimeException
{
    public function __construct(public readonly int $secondsToReset)
    {
        parent::__construct('Превышен лимит запросов');
    }
}
