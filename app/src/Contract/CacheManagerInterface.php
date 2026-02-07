<?php

declare(strict_types=1);

namespace App\Contract;

interface CacheManagerInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    public function has(string $key): bool;
}
