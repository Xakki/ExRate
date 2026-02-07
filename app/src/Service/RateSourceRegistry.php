<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\RateSourceInterface;
use App\Enum\RateSource;
use Psr\Container\ContainerInterface;

readonly class RateSourceRegistry
{
    public function __construct(
        private ContainerInterface $locator,
    ) {
    }

    public function get(RateSource $rateSource): RateSourceInterface
    {
        $serviceName = 'rate_source.'.$rateSource->value;

        if (!$this->locator->has($serviceName)) {
            throw new \RuntimeException(sprintf('Rate source %s not found', $rateSource->value));
        }

        /* @var RateSourceInterface */
        return $this->locator->get($serviceName);
    }
}
