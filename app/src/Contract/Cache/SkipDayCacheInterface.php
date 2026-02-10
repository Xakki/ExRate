<?php

declare(strict_types=1);

namespace App\Contract\Cache;

use App\Enum\ProviderEnum;

interface SkipDayCacheInterface
{
    public function get(ProviderEnum $providerEnum, \DateTimeImmutable $date): ?\DateTimeImmutable;

    public function set(ProviderEnum $providerEnum, \DateTimeImmutable $date, \DateTimeImmutable $correctedDate): void;

    public function clear(): void;
}
