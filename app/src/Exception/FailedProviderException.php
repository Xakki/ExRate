<?php

declare(strict_types=1);

namespace App\Exception;

class FailedProviderException extends \RuntimeException
{
    public function __construct(string $message = '', public readonly string $content = '')
    {
        parent::__construct($message);
    }
}
