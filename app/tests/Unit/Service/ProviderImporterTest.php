<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Contract\Cache\RateLimitCacheInterface;
use App\Contract\Cache\SkipDayCacheInterface;
use App\Contract\ProviderInterface;
use App\DTO\GetRatesResult;
use App\Enum\ProviderEnum;
use App\Repository\ExchangeRateRepository;
use App\Service\ProviderImporter;
use App\Service\ProviderManager;
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

    /** @var ProviderManager&MockObject */
    private ProviderManager $provider;

    private ProviderImporter $importer;

    protected function setUp(): void
    {
        $this->providerRegistry = $this->createMock(ProviderRegistry::class);
        $this->repository = $this->createMock(ExchangeRateRepository::class);

        $this->correctedDayCache = $this->createMock(SkipDayCacheInterface::class);
        $this->rateLimitCache = $this->createMock(RateLimitCacheInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->provider = $this->createMock(ProviderManager::class);

        $this->importer = new ProviderImporter(
            $this->providerRegistry,
            $this->repository,
            $this->correctedDayCache,
            $this->rateLimitCache,
            $this->logger,
            $this->provider
        );
    }

    public function testFetchAndSaveRatesWhenRatesExist(): void
    {
        $date = (new \DateTimeImmutable('2024-01-01'))->setTime(12, 0);
        $providerEnum = ProviderEnum::CBR;

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getBaseCurrency')->willReturn('RUR');
        $provider->method('getId')->willReturn(1);

        $this->providerRegistry->method('get')->with($providerEnum)->willReturn($provider);
        $this->provider->method('getCorrectedDay')->with($providerEnum, $date)->willReturn($date);
        $this->repository->method('existRates')->with(1, 'RUR', $date)->willReturn(true);

        $this->repository->expects($this->never())->method('saveRatesBatch');

        $this->importer->fetchAndSaveRates($providerEnum, $date);
    }

    public function testFetchAndSaveRatesNewRates(): void
    {
        $date = (new \DateTimeImmutable('2024-01-01'))->setTime(12, 0);
        $providerEnum = ProviderEnum::CBR;
        $rates = ['USD' => '75.0', 'EUR' => '90.0'];

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getBaseCurrency')->willReturn('RUR');
        $provider->method('getId')->willReturn(1);
        $provider->method('getRates')->with($date)->willReturn(new GetRatesResult(1, 'RUR', $date, $rates));

        $this->providerRegistry->method('get')->with($providerEnum)->willReturn($provider);
        $this->provider->method('getCorrectedDay')->with($providerEnum, $date)->willReturn($date);
        $this->repository->method('existRates')->with(1, 'RUR', $date)->willReturn(false);

        $this->repository->expects($this->once())
            ->method('saveRatesBatch')
            ->with(1, 'RUR', $this->anything(), $rates);

        $this->importer->fetchAndSaveRates($providerEnum, $date);
    }

    public function testFetchAndSaveRatesWithDateCorrection(): void
    {
        $expectDate = (new \DateTimeImmutable('2024-01-02'))->setTime(12, 0);
        $actualDate = (new \DateTimeImmutable('2024-01-01'))->setTime(12, 0);
        $providerEnum = ProviderEnum::CBR;
        $rates = ['USD' => '75.0'];

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getBaseCurrency')->willReturn('RUR');
        $provider->method('getId')->willReturn(1);
        $provider->method('getRates')->with($actualDate)->willReturn(new GetRatesResult(1, 'RUR', $actualDate, $rates));

        $this->providerRegistry->method('get')->with($providerEnum)->willReturn($provider);
        $this->provider->method('getCorrectedDay')->with($providerEnum, $expectDate)->willReturn($actualDate);
        $this->repository->method('existRates')->with(1, 'RUR', $actualDate)->willReturn(false);

        $this->correctedDayCache->expects($this->once())
            ->method('set')
            ->with($providerEnum, $expectDate, $actualDate);

        $this->repository->expects($this->once())
            ->method('saveRatesBatch')
            ->with(1, 'RUR', $this->anything(), $rates);

        $this->importer->fetchAndSaveRates($providerEnum, $expectDate);
    }
}
