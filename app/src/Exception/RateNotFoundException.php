<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(202, ['Retry-After' => '5'])]
class RateNotFoundException extends \RuntimeException
{
    public function __construct(string $currency, string $baseCurrency)
    {
        parent::__construct(sprintf('Rate for %s/%s not exist yet. Try later.', $currency, $baseCurrency));
    }
}
