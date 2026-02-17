<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Contract\ProviderRateInterface;
use App\Contract\RateRepositoryInterface;
use App\Entity\Rate;
use App\Repository\ProviderRateRepository;
use App\Tests\WebTestCase;
use App\Util\Currencies;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class TimeseriesControllerTest extends WebTestCase
{
    public function testGetTimeseriesSuccess(): void
    {
        $client = static::createClient();
        $mockRepository = $this->createMock(RateRepositoryInterface::class);
        $mockRepository->method('findRatesByPeriod')->willReturnCallback(function (ProviderRateInterface $p, $curr, $base, $start, $end) {
            return [
                new Rate(new \DateTimeImmutable('2026-02-01'), Currencies::USD, Currencies::EUR, '75.0', 1),
                new Rate(new \DateTimeImmutable('2026-02-03'), Currencies::USD, Currencies::EUR, '76.0', 1),
            ];
        });
        $client->getContainer()->set(RateRepositoryInterface::class, $mockRepository);
        $client->getContainer()->set(ProviderRateRepository::class, $mockRepository);

        $url = '/api/v1/timeseries?start_date=2026-02-01&end_date=2026-02-03&currency='.Currencies::USD.'&base_currency='.Currencies::EUR.'&provider=ecb';
        $resp = $this->jsonRequest($client, 'GET', $url);

        $this->assertEquals(Currencies::EUR, $resp['base_currency']);
        $this->assertEquals(Currencies::USD, $resp['currency']);
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
        $url = '/api/v1/timeseries?start_date=2026-02-15&end_date=2026-02-01&currency='.Currencies::USD;
        $this->jsonRequest($client, 'GET', $url, 400, [
            ['level' => 'error', 'message' => '/Start date must be before or equal to end date/'],
        ]);

        // Range > 5 years
        $url = '/api/v1/timeseries?start_date=2016-01-01&end_date=2022-01-01&currency='.Currencies::USD;
        $this->jsonRequest($client, 'GET', $url, 400, [
            ['level' => 'error', 'message' => '/The maximum allowed range more than 5 years for free/'],
        ]);
    }
}
