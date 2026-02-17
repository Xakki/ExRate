<?php

declare(strict_types=1);

namespace App\Util;

use App\Exception\BadDateException;

class Date
{
    public const string FORMAT = 'Y-m-d';
    public const string FORMAT_TIME = 'Y-m-d H:i:s';

    public static function createFromFormat(string $format, string $datetime, ?\DateTimeZone $timezone = null): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat($format, $datetime, $timezone);

        if (false === $date) {
            throw new BadDateException(sprintf('Invalid date string "%s" for format "%s"', $datetime, $format));
        }

        return $date->setTime(12, 0);
    }

    public static function getDayDiff(\DateTimeImmutable $dateBase, \DateTimeImmutable $dateDiff = new \DateTimeImmutable()): int
    {
        $diff = $dateBase->setTime(12, 0)->diff($dateDiff->setTime(12, 0));

        return $diff->days * ($diff->invert ? -1 : 1);
    }
}
