<?php

declare(strict_types=1);

namespace App\Util;

final class BcMath
{
    /**
     * @return numeric-string
     */
    public static function div(string|int|float $left, string|int|float $right, int $scale): string
    {
        $left = self::normalize($left);
        $right = self::normalize($right);
        self::assertNumeric($left, $right);

        /** @var numeric-string $result */
        $result = \bcdiv($left, $right, $scale);

        return $result;
    }

    /**
     * @return numeric-string
     */
    public static function sub(string|int|float $left, string|int|float $right, int $scale): string
    {
        $left = self::normalize($left);
        $right = self::normalize($right);
        self::assertNumeric($left, $right);

        return \bcsub($left, $right, $scale);
    }

    public static function comp(string|int|float $left, string|int|float $right, int $scale): int
    {
        $left = self::normalize($left);
        $right = self::normalize($right);
        self::assertNumeric($left, $right);

        return \bccomp($left, $right, $scale);
    }

    public static function round(string|int|float $value, int $scale): string
    {
        $value = self::normalize($value);

        if (!is_numeric($value)) {
            throw new \LogicException(sprintf('Expected numeric string, got: %s', $value));
        }

        if (function_exists('bcround')) {
            return \bcround($value, $scale);
        }

        if (false === strpos($value, '.')) {
            return \bcadd($value, '0', $scale);
        }

        $isNegative = -1 === \bccomp($value, '0', $scale + 1);
        $add = '0.'.str_repeat('0', $scale).'5';

        if ($isNegative) {
            // @phpstan-ignore argument.type
            return \bcsub($value, $add, $scale);
        }

        // @phpstan-ignore argument.type
        return \bcadd($value, $add, $scale);
    }

    public static function normalize(string|int|float $value): string
    {
        $value = str_replace(',', '.', (string) $value);

        if (is_numeric($value) && str_contains(strtolower($value), 'e')) {
            $parts = explode('e', strtolower($value));
            $num = $parts[0];
            $exp = (int) $parts[1];

            $isNegative = str_starts_with($num, '-');
            if ($isNegative) {
                $num = substr($num, 1);
            }

            $dotPos = strpos($num, '.');
            if (false !== $dotPos) {
                $precision = strlen($num) - $dotPos - 1;
                $num = str_replace('.', '', $num);
                $exp -= $precision;
            }

            if ($exp >= 0) {
                $res = $num.str_repeat('0', $exp);
            } else {
                $exp = abs($exp);
                if ($exp >= strlen($num)) {
                    $res = '0.'.str_repeat('0', $exp - strlen($num)).$num;
                } else {
                    $res = substr($num, 0, strlen($num) - $exp).'.'.substr($num, strlen($num) - $exp);
                }
            }

            $value = ($isNegative ? '-' : '').$res;
        }

        return $value;
    }

    /**
     * @phpstan-assert numeric-string $left
     * @phpstan-assert numeric-string $right
     */
    private static function assertNumeric(string $left, string $right): void
    {
        if (!is_numeric($left) || !is_numeric($right)) {
            throw new \LogicException(sprintf('Expected numeric strings, got: %s and %s', $left, $right));
        }
    }
}
