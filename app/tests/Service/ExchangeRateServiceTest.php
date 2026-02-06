<?php

namespace App\Tests\Service;

use App\Entity\ExchangeRate;
use App\Repository\ExchangeRateRepository;
use App\Service\CbrRateSource;
use App\Service\ExchangeRateService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ExchangeRateServiceTest extends TestCase
{
    public function testGetRateReturnsDataFromDb(): void
    {
        $rateSource = $this->createMock(CbrRateSource::class);
        $repo = $this->createMock(ExchangeRateRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);

        $date = new \DateTimeImmutable('2023-10-01');
        $currency = 'USD';
        $base = 'RUB';

        $existingRate = new ExchangeRate();
        $existingRate->setDate($date);
        $existingRate->setRate('95.00');

        $repo->expects($this->once())
            ->method('findOneByDateAndCurrency')
            ->with($date, $currency, $base)
            ->willReturn($existingRate);

        $repo->expects($this->once())
            ->method('findLatestBeforeDate')
            ->willReturn(null); // No previous rate for diff

        $service = new ExchangeRateService($rateSource, $repo, $em);
        $result = $service->getRate($date, $currency, $base);

        $this->assertEquals('95.00', $result['rate']);
        $this->assertNull($result['diff']);
    }

    public function testGetRateFetchesFromCbrWhenNotInDb(): void
    {
        $rateSource = $this->createMock(CbrRateSource::class);
        $repo = $this->createMock(ExchangeRateRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);

        $date = new \DateTimeImmutable('2023-10-01');
        $currency = 'USD';
        $base = 'RUB';

        $repo->expects($this->once())
            ->method('findOneByDateAndCurrency')
            ->willReturn(null);

        $rateSource->expects($this->once())
            ->method('getRate')
            ->with($date, $currency)
            ->willReturn(96.50);

        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        // Mock previous rate for diff
        $prevRate = new ExchangeRate();
        $prevRate->setRate('95.50');
        $repo->expects($this->once())
            ->method('findLatestBeforeDate')
            ->willReturn($prevRate);

        $service = new ExchangeRateService($rateSource, $repo, $em);
        $result = $service->getRate($date, $currency, $base);

        $this->assertEquals('96.5', $result['rate']);
        $this->assertEquals('1.0000', $result['diff']);
    }
}
