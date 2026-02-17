<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Contract\Cache\RateLimitCacheInterface;
use App\Contract\ProviderRateInterface;
use App\Contract\RateEntityInterface;
use App\Contract\RateRepositoryInterface;
use App\DTO\GetRatesResult;
use App\DTO\RateData;
use App\Enum\ProviderEnum;
use App\Exception\NotAvailableMethod;
use App\Service\ProviderImporter;
use App\Util\Currencies;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class ProviderImporterTest extends TestCase
{
    /** @var RateRepositoryInterface&MockObject */
    private RateRepositoryInterface $repository;

    /** @var RateLimitCacheInterface&MockObject */
    private RateLimitCacheInterface $rateLimitCache;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $entityManager;

    private ProviderImporter $importer;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(RateRepositoryInterface::class);

        $this->rateLimitCache = $this->createMock(RateLimitCacheInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->importer = new ProviderImporter(
            repository: $this->repository,
            rateLimitCache: $this->rateLimitCache,
            logger: $this->logger,
            entityManager: $this->entityManager,
        );
    }

    public function testFetchAndSaveRatesWhenRatesExist(): void
    {
        $date = (new \DateTimeImmutable('2024-01-01'))->setTime(12, 0);

        $provider = $this->createMock(ProviderRateInterface::class);
        $provider->method('getBaseCurrency')->willReturn(Currencies::EUR);
        $provider->method('getId')->willReturn(1);
        $provider->method('getEnum')->willReturn(ProviderEnum::ECB);

        $rateEntity = $this->createMock(RateEntityInterface::class);
        $rateEntity->method('getDate')->willReturn($date);

        $this->repository->method('findOneByDateRange')
            ->with($provider, $provider->getBaseCurrency(), $this->anything(), $this->anything())
            ->willReturn($rateEntity);

        $this->repository->method('getMinDate')->willReturn($date);

        $this->repository->expects($this->never())->method('saveRatesBatch');

        $this->importer->fetchAndSaveRates($provider, $date);
    }

    public function testFetchAndSaveRatesNewRates(): void
    {
        $date = (new \DateTimeImmutable('2024-01-01'))->setTime(12, 0);
        $providerEnum = ProviderEnum::ECB;
        $rates = [Currencies::USD => new RateData('75.0'), Currencies::EUR => new RateData('90.0')];

        $provider = $this->createMock(ProviderRateInterface::class);
        $provider->method('getEnum')->willReturn($providerEnum);
        $provider->method('getAvailableCurrencies')->willReturn([Currencies::USD, Currencies::EUR]);
        $provider->method('getBaseCurrency')->willReturn(Currencies::RUB);
        $provider->method('getId')->willReturn(1);
        $provider->method('getRatesByRangeDate')->willThrowException(new NotAvailableMethod());
        $provider->method('getRatesByDate')->with($date)->willReturn(new GetRatesResult($provider, Currencies::RUB, $date, $rates));

        $this->repository->expects($this->once())
            ->method('saveRatesBatch')
            ->with($provider, Currencies::RUB, $this->anything(), $rates);

        $this->importer->fetchAndSaveRates($provider, $date);
    }

    public function testFetchAndSaveRatesWithCurrencyMismatch(): void
    {
        $date = (new \DateTimeImmutable('2024-01-01'))->setTime(12, 0);
        $providerEnum = ProviderEnum::CBR;
        $rates = [Currencies::USD => new RateData('75.0')]; // Only USD
        $availableCurrencies = [Currencies::USD, Currencies::RUB]; // USD and RUB available

        $provider = $this->createMock(ProviderRateInterface::class);
        $provider->method('getEnum')->willReturn($providerEnum);
        $provider->method('getAvailableCurrencies')->willReturn($availableCurrencies);
        $provider->method('getBaseCurrency')->willReturn(Currencies::EUR);
        $provider->method('getId')->willReturn(1);
        $provider->method('getRatesByRangeDate')->willThrowException(new NotAvailableMethod());
        $provider->method('getRatesByDate')->with($date)->willReturn(new GetRatesResult($provider, $provider->getBaseCurrency(), $date, $rates));

        $this->logger->expects($this->atLeastOnce())
            ->method('info')
            ->willReturnCallback(function ($message, $context) {
                if (str_contains($message, 'Currency mismatch')) {
                    $this->assertEquals('cbr', $context['provider']);
                    $this->assertEquals([Currencies::RUB], $context['missing_in_fetched']);
                    $this->assertEmpty($context['extra_in_fetched']);
                }
            });

        $this->importer->fetchAndSaveRates($provider, $date);
    }

    public function testFetchAndSaveRatesWithRange(): void
    {
        $date = (new \DateTimeImmutable('2024-01-10'))->setTime(12, 0);
        $providerEnum = ProviderEnum::ECB;
        $ratesDay1 = [Currencies::USD => new RateData('1.1')];
        $ratesDay2 = [Currencies::USD => new RateData('1.2')];

        $date1 = new \DateTimeImmutable('2024-01-01');
        $date2 = new \DateTimeImmutable('2024-01-10');

        $provider = $this->createMock(ProviderRateInterface::class);
        $provider->method('getEnum')->willReturn($providerEnum);
        $provider->method('getId')->willReturn(1);
        $provider->method('getPeriodDays')->willReturn(10);
        $provider->method('getBaseCurrency')->willReturn(Currencies::EUR);
        $provider->method('getRatesByRangeDate')->willReturn([
            new GetRatesResult($provider, Currencies::EUR, $date1, $ratesDay1),
            new GetRatesResult($provider, Currencies::EUR, $date2, $ratesDay2),
        ]);

        $this->repository->expects($this->exactly(2))
            ->method('saveRatesBatch');
        $this->repository->expects($this->exactly(1))
            ->method('findOneByDateRange');

        $result = $this->importer->fetchAndSaveRates($provider, $date);

        $this->assertEquals(\App\Enum\FetchStatusEnum::SUCCESS, $result[0]);
        $this->assertEquals($date->modify('-'.$provider->getPeriodDays().' day'), $result[1]);
    }
}
