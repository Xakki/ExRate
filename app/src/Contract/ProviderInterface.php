<?php

declare(strict_types=1);

namespace App\Contract;

use App\DTO\GetRatesResult;
use App\Enum\ProviderEnum;

interface ProviderInterface
{
    public static function getServiceName(): string;

    public function getId(): int;

    public function getEnum(): ProviderEnum;

    public function getBaseCurrency(): string;

    public function getHomePage(): string;

    public function getDescription(): string;

    /*
     * Указывает день последних доступных курсов (NOW - getDaysLag)
     */
    public function getDaysLag(): int;

    public function getRatesByDate(\DateTimeImmutable $date): GetRatesResult;

    /**
     * @return GetRatesResult[]
     */
    public function getRatesByRangeDate(\DateTimeImmutable $start, \DateTimeImmutable $end): array;

    public function isActive(): bool;

    /**
     * @return string[]
     */
    public function getAvailableCurrencies(): array;

    public function getRequestLimit(): int;

    public function getRequestLimitPeriod(): int;

    public function getRequestDelay(): int;
}
