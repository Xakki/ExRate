<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Contract\Cache\RateLimitCacheInterface;
use App\Contract\Cache\SkipDayCacheInterface;
use App\Contract\ProviderInterface;
use App\DTO\GetRatesResult;
use App\Entity\ExchangeRate;
use App\Enum\ProviderEnum;
use App\Repository\ExchangeRateRepository;
use App\Service\ProviderImporter;
use App\Service\ProviderRegistry;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class ProviderImporterTest extends TestCase
{
    /** @var ProviderRegistry&MockObject */
    private ProviderRegistry $providerRegistry;

    /** @var ExchangeRateRepository&MockObject */
    private ExchangeRateRepository $repository;

    /** @var SkipDayCacheInterface&MockObject */
    private SkipDayCacheInterface $correctedDayCache;

    /** @var RateLimitCacheInterface&MockObject */
    private RateLimitCacheInterface $rateLimitCache;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    private ProviderImporter $importer;

    protected function setUp(): void
    {
        $this->providerRegistry = $this->createMock(ProviderRegistry::class);
        $this->repository = $this->createMock(ExchangeRateRepository::class);

        $this->correctedDayCache = $this->createMock(SkipDayCacheInterface::class);
        $this->rateLimitCache = $this->createMock(RateLimitCacheInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->importer = new ProviderImporter(
            $this->providerRegistry,
            $this->repository,
            $this->correctedDayCache,
            $this->rateLimitCache,
            $this->logger,
        );
    }

    public function testFetchAndSaveRatesWhenRatesExist(): void
    {
        $date = (new \DateTimeImmutable('2024-01-01'))->setTime(12, 0);
        $providerEnum = ProviderEnum::ECB;

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getBaseCurrency')->willReturn('EUR');
        $provider->method('getId')->willReturn(1);

        $this->providerRegistry->method('get')->with($providerEnum)->willReturn($provider);
        $this->repository->method('findOneByDateRange')->with()
            ->willReturn(new ExchangeRate($date, 'USD', $provider->getBaseCurrency(), '0.1', $provider->getId()));

        $this->repository->expects($this->never())->method('saveRatesBatch');

        $this->importer->fetchAndSaveRates($providerEnum, $date);
    }

    public function testFetchAndSaveRatesNewRates(): void
    {
        $date = (new \DateTimeImmutable('2024-01-01'))->setTime(12, 0);
        $providerEnum = ProviderEnum::ECB;
        $rates = ['USD' => '75.0', 'EUR' => '90.0'];

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getEnum')->willReturn($providerEnum);
        $provider->method('getAvailableCurrencies')->willReturn(['USD', 'EUR']);
        $provider->method('getBaseCurrency')->willReturn('RUR');
        $provider->method('getId')->willReturn(1);
        $provider->method('getRatesByDate')->with($date)->willReturn(new GetRatesResult(1, 'RUR', $date, $rates));

        $this->providerRegistry->method('get')->with($providerEnum)->willReturn($provider);
        $this->repository->method('existRates')->with(1, 'RUR', $date)->willReturn(false);

        $this->repository->expects($this->once())
            ->method('saveRatesBatch')
            ->with(1, 'RUR', $this->anything(), $rates);

        $this->importer->fetchAndSaveRates($providerEnum, $date);
    }

    public function testFetchAndSaveRatesWithCurrencyMismatch(): void
    {
        $date = (new \DateTimeImmutable('2024-01-01'))->setTime(12, 0);
        $providerEnum = ProviderEnum::CBR;
        $rates = ['USD' => '75.0']; // Only USD
        $availableCurrencies = ['USD', 'RUB']; // USD and RUB available

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getEnum')->willReturn($providerEnum);
        $provider->method('getAvailableCurrencies')->willReturn($availableCurrencies);
        $provider->method('getBaseCurrency')->willReturn('EUR');
        $provider->method('getId')->willReturn(1);
        $provider->method('getRatesByDate')->with($date)->willReturn(new GetRatesResult(1, $provider->getBaseCurrency(), $date, $rates));

        $this->providerRegistry->method('get')->with($providerEnum)->willReturn($provider);
        $this->repository->method('existRates')->with(1, $provider->getBaseCurrency(), $date)->willReturn(false);

        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Currency mismatch'), $this->callback(function ($context) {
                return 'cbr' === $context['provider']
                    && $context['missing_in_fetched'] === ['RUB']
                    && empty($context['extra_in_fetched']);
            }));

        $this->importer->fetchAndSaveRates($providerEnum, $date);
    }
}
