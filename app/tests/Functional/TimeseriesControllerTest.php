<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\ExchangeRate;
use App\Repository\ExchangeRateRepository;
use App\Tests\WebTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class TimeseriesControllerTest extends WebTestCase
{
    public function testGetTimeseriesSuccess(): void
    {
        $client = static::createClient();
        $mockRepository = $this->createMock(ExchangeRateRepository::class);
        $mockRepository->method('findRatesByPeriod')->willReturnCallback(function ($pid, $curr, $base, $start, $end) {
            return [
                new ExchangeRate(new \DateTimeImmutable('2026-02-01'), 'USD', 'RUB', '75.0', 1),
                new ExchangeRate(new \DateTimeImmutable('2026-02-03'), 'USD', 'RUB', '76.0', 1),
            ];
        });
        $client->getContainer()->set(ExchangeRateRepository::class, $mockRepository);

        $url = '/api/v1/timeseries?start_date=2026-02-01&end_date=2026-02-03&currency=USD&base_currency=RUB';
        $resp = $this->jsonRequest($client, 'GET', $url);

        $this->assertEquals('RUB', $resp['base_currency']);
        $this->assertEquals('USD', $resp['currency']);
        $this->assertEquals('2026-02-01', $resp['start_date']);
        $this->assertEquals('2026-02-03', $resp['end_date']);
        $this->assertCount(2, $resp['rates']);
        $this->assertEquals('75.0', $resp['rates']['2026-02-01']);
        $this->assertEquals('76.0', $resp['rates']['2026-02-03']);
    }

    public function testGetTimeseriesValidationError(): void
    {
        $client = static::createClient();

        // Invalid date range (start > end)
        $url = '/api/v1/timeseries?start_date=2026-02-15&end_date=2026-02-01&currency=USD';
        $this->jsonRequest($client, 'GET', $url, 400, [
            ['level' => 'error', 'message' => '/Start date must be before or equal to end date/'],
        ]);

        // Range > 5 years
        $url = '/api/v1/timeseries?start_date=2020-01-01&end_date=2026-01-01&currency=USD';
        $this->jsonRequest($client, 'GET', $url, 400, [
            ['level' => 'error', 'message' => '/The maximum allowed range is 5 years/'],
        ]);
    }
}
