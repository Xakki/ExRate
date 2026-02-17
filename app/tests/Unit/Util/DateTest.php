<?php

declare(strict_types=1);

namespace App\Tests\Unit\Util;

use App\Exception\BadDateException;
use App\Util\Date;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DateTest extends TestCase
{
    public function testCreateFromFormat(): void
    {
        $format = 'Y-m-d';
        $datetime = '2026-02-20';
        $date = Date::createFromFormat($format, $datetime);

        $this->assertInstanceOf(\DateTimeImmutable::class, $date);
        $this->assertSame('2026-02-20 12:00:00', $date->format('Y-m-d H:i:s'));
    }

    public function testCreateFromFormatWithTimezone(): void
    {
        $format = 'Y-m-d';
        $datetime = '2026-02-20';
        $timezone = new \DateTimeZone('Europe/Prague');
        $date = Date::createFromFormat($format, $datetime, $timezone);

        $this->assertSame('Europe/Prague', $date->getTimezone()->getName());
        $this->assertSame('2026-02-20 12:00:00', $date->format('Y-m-d H:i:s'));
    }

    public function testCreateFromFormatInvalidDate(): void
    {
        $this->expectException(BadDateException::class);
        $this->expectExceptionMessage('Invalid date string "invalid-date" for format "Y-m-d"');

        Date::createFromFormat('Y-m-d', 'invalid-date');
    }

    #[DataProvider('provideDayDiffData')]
    public function testGetDayDiff(\DateTimeImmutable $dateBase, \DateTimeImmutable $dateDiff, int $expected): void
    {
        $this->assertSame($expected, Date::getDayDiff($dateBase, $dateDiff));
    }

    /**
     * @return array<string, array{\DateTimeImmutable, \DateTimeImmutable, int}>
     */
    public static function provideDayDiffData(): array
    {
        return [
            'one day forward' => [
                new \DateTimeImmutable('2026-02-21 12:00:00'),
                new \DateTimeImmutable('2026-02-20 12:00:00'),
                -1,
            ],
            'one day backward' => [
                new \DateTimeImmutable('2026-02-19 12:00:00'),
                new \DateTimeImmutable('2026-02-20 12:00:00'),
                1,
            ],
            'same day' => [
                new \DateTimeImmutable('2026-02-20 12:00:00'),
                new \DateTimeImmutable('2026-02-20 12:00:00'),
                0,
            ],
            'different times same day' => [
                new \DateTimeImmutable('2026-02-20 23:59:59'),
                new \DateTimeImmutable('2026-02-20 00:00:01'),
                0,
            ],
            'exactly 24 hours' => [
                new \DateTimeImmutable('2026-02-21 12:00:00'),
                new \DateTimeImmutable('2026-02-20 12:00:00'),
                -1,
            ],
            'leap year' => [
                new \DateTimeImmutable('2024-03-01 12:00:00'),
                new \DateTimeImmutable('2024-02-28 12:00:00'),
                -2,
            ],
            'long period' => [
                new \DateTimeImmutable('2027-02-20 12:00:00'),
                new \DateTimeImmutable('2026-02-20 12:00:00'),
                -365,
            ],
        ];
    }

    public function testGetDayDiffDefaultSecondParam(): void
    {
        $dateBase = new \DateTimeImmutable('-2 days');
        $diff = Date::getDayDiff($dateBase);

        // It should be at least 1 (likely 2, but 1 is safe if we crossed a tiny bit of time)
        $this->assertGreaterThanOrEqual(1, $diff);
    }
}
