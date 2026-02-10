<?php

declare(strict_types=1);

namespace App\Tests\Unit\Util;

use App\Util\BcMath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BcMathTest extends TestCase
{
    #[DataProvider('provideRoundData')]
    public function testRound(string|int|float $value, int $scale, string $expected): void
    {
        $this->assertSame($expected, BcMath::round($value, $scale));
    }

    /**
     * @return array<string, array{string|int|float, int, string}>
     */
    public static function provideRoundData(): array
    {
        return [
            'round simple up' => ['1.235', 2, '1.24'],
            'round simple down' => ['1.234', 2, '1.23'],
            'round negative up' => ['-1.235', 2, '-1.24'],
            'round negative down' => ['-1.234', 2, '-1.23'],
            'round zero scale' => ['1.5', 0, '2'],
            'round zero' => ['0', 8, '0.00000000'],
            'round zero dot' => ['0.0', 2, '0.00'],
            'round scientific positive' => ['2.3E-5', 8, '0.00002300'],
            'round scientific negative' => ['-2.3E-5', 8, '-0.00002300'],
            'round scientific large' => ['1.23E2', 0, '123'],
            'round very small' => ['1E-10', 8, '0.00000000'],
            'round comma decimal' => ['1,235', 2, '1.24'],
        ];
    }

    #[DataProvider('provideDivData')]
    public function testDiv(string|int|float $left, string|int|float $right, int $scale, string $expected): void
    {
        $this->assertSame($expected, BcMath::div($left, $right, $scale));
    }

    /**
     * @return array<string, array{string|int|float, string|int|float, int, string}>
     */
    public static function provideDivData(): array
    {
        return [
            'div simple' => ['10', '3', 2, '3.33'],
            'div scientific' => ['2.3E-5', '1', 8, '0.00002300'],
            'div by small scientific' => ['1', '1E-2', 2, '100.00'],
        ];
    }

    #[DataProvider('provideSubData')]
    public function testSub(string|int|float $left, string|int|float $right, int $scale, string $expected): void
    {
        $this->assertSame($expected, BcMath::sub($left, $right, $scale));
    }

    /**
     * @return array<string, array{string|int|float, string|int|float, int, string}>
     */
    public static function provideSubData(): array
    {
        return [
            'sub simple' => ['1', '0.1', 2, '0.90'],
            'sub scientific' => ['1E-1', '0.05', 2, '0.05'],
        ];
    }

    #[DataProvider('provideCompData')]
    public function testComp(string|int|float $left, string|int|float $right, int $scale, int $expected): void
    {
        $this->assertSame($expected, BcMath::comp($left, $right, $scale));
    }

    /**
     * @return array<string, array{string|int|float, string|int|float, int, int}>
     */
    public static function provideCompData(): array
    {
        return [
            'comp equal' => ['1.00', '1', 2, 0],
            'comp greater' => ['1.01', '1', 2, 1],
            'comp smaller' => ['0.99', '1', 2, -1],
            'comp scientific' => ['1E-2', '0.01', 2, 0],
        ];
    }

    public function testRoundInvalidInput(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Expected numeric string, got: not-numeric');
        BcMath::round('not-numeric', 2);
    }

    public function testRoundEmptyInput(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Expected numeric string, got: ');
        BcMath::round('', 2);
    }
}
