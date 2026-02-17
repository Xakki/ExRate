<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Contract\Cache\RateCacheInterface;
use App\Contract\Cache\SkipDayCacheInterface;
use App\Contract\Cache\TimeseriesCacheInterface;
use App\Contract\ProviderInterface;
use App\DTO\RateResponse;
use App\Entity\ExchangeRate;
use App\Enum\ProviderEnum;
use App\Exception\RateNotFoundException;
use App\Repository\ExchangeRateRepository;
use App\Service\ProviderManager;
use App\Service\ProviderRegistry;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class ProviderManagerTest extends TestCase
{
    /** @var ProviderRegistry&MockObject */
    private ProviderRegistry $providerRegistry;
    /** @var ExchangeRateRepository&MockObject */
    private ExchangeRateRepository $repository;
    /** @var RateCacheInterface&MockObject */
    private RateCacheInterface $rateCache;
    /** @var SkipDayCacheInterface&MockObject */
    private SkipDayCacheInterface $correctedDayCache;

    /** @var TimeseriesCacheInterface&MockObject */
    private TimeseriesCacheInterface $timeseriesCache;

    private ProviderManager $provider;

    protected function setUp(): void
    {
        $this->providerRegistry = $this->createMock(ProviderRegistry::class);
        $this->repository = $this->createMock(ExchangeRateRepository::class);
        $this->rateCache = $this->createMock(RateCacheInterface::class);
        $this->correctedDayCache = $this->createMock(SkipDayCacheInterface::class);
        $this->timeseriesCache = $this->createMock(TimeseriesCacheInterface::class);

        $this->provider = new ProviderManager(
            $this->providerRegistry,
            $this->repository,
            $this->rateCache,
            $this->correctedDayCache,
            $this->timeseriesCache,
            currencyPrecision: 4
        );
    }

    public function testGetRateFromCache(): void
    {
        $date = new \DateTimeImmutable('2024-01-01');
        $currency = 'USD';
        $baseCurrency = 'RUR';
        $provider = ProviderEnum::CBR;

        $cachedResponse = new RateResponse('75.0', '2024-01-01', null, null);

        $this->correctedDayCache->method('get')->with($provider, $date)->willReturn($date);

        $this->rateCache->method('get')
            ->with($date, $provider, $baseCurrency, $currency)
            ->willReturn($cachedResponse);

        $result = $this->provider->getRate($date, $currency, $baseCurrency, $provider);

        $this->assertSame($cachedResponse, $result);
    }

    public function testGetRateAndSetToCache(): void
    {
        $dateNow = new \DateTimeImmutable();
        $currency = 'USD';
        $baseCurrency = 'RUR';
        $providerEnum = ProviderEnum::CBR;

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getBaseCurrency')->willReturn($baseCurrency);
        $provider->method('getId')->willReturn(1);
        $provider->method('getEnum')->willReturn($providerEnum);

        $this->providerRegistry->method('get')->with($providerEnum)->willReturn($provider);
        $this->rateCache->method('get')->willReturn(null);
        $this->correctedDayCache->method('get')->willReturn(null);

        $rateEntity = new ExchangeRate($dateNow, $currency, $baseCurrency, '75.0', $provider->getId());

        $previousRateEntity = new ExchangeRate($dateNow->modify('-1 day'), $currency, $baseCurrency, '74.0', $provider->getId());

        $this->repository->method('findTwoLastRates')
            ->willReturnCallback(function (int $providerId, string $currency, string $baseCurrency, \DateTimeImmutable $date) use ($dateNow, $rateEntity, $previousRateEntity) {
                $rows = [];
                if ($date->format('Y-m-d') === $dateNow->format('Y-m-d')) {
                    $rows[] = $rateEntity;
                    $rows[] = $previousRateEntity;
                }

                return $rows;
            });

        $this->rateCache->expects($this->once())->method('set');

        $data = $this->provider->getRate($dateNow, $currency, $baseCurrency, $providerEnum);

        $this->assertEquals($rateEntity->getRate(), $data->rate);
        $this->assertEquals($rateEntity->getDate()->format('Y-m-d'), $data->date);
    }

    public function testGetRateDirectFound(): void
    {
        $dateNow = new \DateTimeImmutable('2024-01-01');
        $currency = 'USD';
        $baseCurrency = 'RUR';
        $providerEnum = ProviderEnum::CBR;

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getBaseCurrency')->willReturn($baseCurrency);
        $provider->method('getId')->willReturn(1);
        $provider->method('getEnum')->willReturn($providerEnum);

        $this->providerRegistry->method('get')->with($providerEnum)->willReturn($provider);
        $this->rateCache->method('get')->willReturn(null);
        $this->correctedDayCache->method('get')->willReturn(null);

        $rateEntity = new ExchangeRate($dateNow, $currency, $baseCurrency, '75.0', $provider->getId());

        $previousRateEntity = new ExchangeRate($dateNow->modify('-1 day'), $currency, $baseCurrency, '74.0', $provider->getId());

        $this->repository->method('findTwoLastRates')
            ->willReturnCallback(function (int $providerId, string $currency, string $baseCurrency, \DateTimeImmutable $date) use ($dateNow, $rateEntity, $previousRateEntity) {
                $rows = [];
                if ($date->format('Y-m-d') === $dateNow->format('Y-m-d')) {
                    $rows[] = $rateEntity;
                    $rows[] = $previousRateEntity;
                }

                return $rows;
            });

        $result = $this->provider->getRate($dateNow, $currency, $baseCurrency, $providerEnum);

        $this->assertEquals('75.0', $result->rate);
        $this->assertEquals('1.0000', $result->diff);
    }

    public function testGetRateDirectNotFound(): void
    {
        $date = new \DateTimeImmutable('2024-01-01');
        $currency = 'USD';
        $baseCurrency = 'RUR';
        $providerEnum = ProviderEnum::CBR;

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getBaseCurrency')->willReturn('RUR');
        $provider->method('getId')->willReturn(1);
        $provider->method('getEnum')->willReturn($providerEnum);

        $this->providerRegistry->method('get')->with($providerEnum)->willReturn($provider);
        $this->rateCache->method('get')->willReturn(null);
        $this->correctedDayCache->method('get')->willReturn(null);

        $this->repository->method('findTwoLastRates')->willReturn([]);

        $this->expectException(RateNotFoundException::class);

        $this->provider->getRate($date, $currency, $baseCurrency, $providerEnum);
    }

    public function testGetSkipDayCached(): void
    {
        $date = new \DateTimeImmutable('2024-01-02');
        $provider = ProviderEnum::CBR;

        $this->correctedDayCache->method('get')
            ->with($provider, $date)
            ->willReturn(new \DateTimeImmutable('2024-01-01'));

        $result = $this->provider->getCorrectedDay($provider, $date);

        $this->assertEquals('2024-01-01', $result->format('Y-m-d'));
    }

    public function testGetCorrectedDayNotCached(): void
    {
        $date = new \DateTimeImmutable('2024-01-02');
        $provider = ProviderEnum::CBR;

        $this->correctedDayCache->method('get')->willReturn(null);

        $result = $this->provider->getCorrectedDay($provider, $date);

        $this->assertEquals('2024-01-02', $result->format('Y-m-d'));
    }
}
