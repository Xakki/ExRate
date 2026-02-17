<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Contract\Cache\RateCacheInterface;
use App\Contract\Cache\TimeseriesCacheInterface;
use App\Contract\ProviderRateInterface;
use App\Contract\RateRepositoryInterface;
use App\Entity\Rate;
use App\Enum\ProviderEnum;
use App\Exception\RateNotFoundException;
use App\Response\RateResponse;
use App\Service\ProviderManager;
use App\Service\ProviderRegistry;
use App\Util\Currencies;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class ProviderManagerTest extends TestCase
{
    /** @var ProviderRegistry&MockObject */
    private ProviderRegistry $providerRegistry;
    /** @var RateRepositoryInterface&MockObject */
    private RateRepositoryInterface $repository;
    /** @var RateCacheInterface&MockObject */
    private RateCacheInterface $rateCache;

    /** @var TimeseriesCacheInterface&MockObject */
    private TimeseriesCacheInterface $timeseriesCache;

    private ProviderManager $provider;

    protected function setUp(): void
    {
        $this->providerRegistry = $this->createMock(ProviderRegistry::class);
        $this->repository = $this->createMock(RateRepositoryInterface::class);
        $this->rateCache = $this->createMock(RateCacheInterface::class);
        $this->timeseriesCache = $this->createMock(TimeseriesCacheInterface::class);

        $this->provider = new ProviderManager(
            $this->providerRegistry,
            $this->repository,
            $this->rateCache,
            $this->timeseriesCache,
            currencyPrecision: 8
        );
    }

    public function testGetRateFromCache(): void
    {
        $date = new \DateTimeImmutable('2024-01-01');
        $currency = Currencies::USD;
        $baseCurrency = Currencies::RUB;
        $provider = ProviderEnum::CBR;

        $cachedResponse = new RateResponse('75.0', '2024-01-01', null, null);

        $this->rateCache->method('get')
            ->with($date, $provider, $baseCurrency, $currency)
            ->willReturn($cachedResponse);

        $result = $this->provider->getRate($date, $currency, $baseCurrency, $provider);

        $this->assertSame($cachedResponse, $result);
    }

    public function testGetRateAndSetToCache(): void
    {
        $dateNow = new \DateTimeImmutable();
        $currency = Currencies::USD;
        $baseCurrency = Currencies::RUB;
        $providerEnum = ProviderEnum::CBR;

        $provider = $this->createMock(ProviderRateInterface::class);
        $provider->method('getBaseCurrency')->willReturn($baseCurrency);
        $provider->method('getId')->willReturn(1);
        $provider->method('getEnum')->willReturn($providerEnum);

        $this->providerRegistry->method('get')->with($providerEnum)->willReturn($provider);
        $this->rateCache->method('get')->willReturn(null);

        $rateEntity = new Rate($dateNow, $currency, $baseCurrency, '75.0', $provider->getId());

        $previousRateEntity = new Rate($dateNow->modify('-1 day'), $currency, $baseCurrency, '74.0', $provider->getId());

        $this->repository->method('findTwoLastRates')
            ->willReturnCallback(function (ProviderRateInterface $p, string $currency, string $baseCurrency, \DateTimeImmutable $date) use ($dateNow, $rateEntity, $previousRateEntity) {
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
        $currency = Currencies::USD;
        $baseCurrency = Currencies::RUB;
        $providerEnum = ProviderEnum::CBR;

        $provider = $this->createMock(ProviderRateInterface::class);
        $provider->method('getBaseCurrency')->willReturn($baseCurrency);
        $provider->method('getId')->willReturn(1);
        $provider->method('getEnum')->willReturn($providerEnum);

        $this->providerRegistry->method('get')->with($providerEnum)->willReturn($provider);
        $this->rateCache->method('get')->willReturn(null);

        $rateEntity = new Rate($dateNow, $currency, $baseCurrency, '75.0', $provider->getId());

        $previousRateEntity = new Rate($dateNow->modify('-1 day'), $currency, $baseCurrency, '74.0', $provider->getId());

        $this->repository->method('findTwoLastRates')
            ->willReturnCallback(function (ProviderRateInterface $p, string $currency, string $baseCurrency, \DateTimeImmutable $date) use ($dateNow, $rateEntity, $previousRateEntity) {
                $rows = [];
                if ($date->format('Y-m-d') === $dateNow->format('Y-m-d')) {
                    $rows[] = $rateEntity;
                    $rows[] = $previousRateEntity;
                }

                return $rows;
            });

        $result = $this->provider->getRate($dateNow, $currency, $baseCurrency, $providerEnum);

        $this->assertEquals('75.0', $result->rate);
        $this->assertEquals('1', $result->diff);
    }

    public function testGetRateDirectNotFound(): void
    {
        $date = new \DateTimeImmutable('2024-01-01');
        $currency = Currencies::USD;
        $baseCurrency = Currencies::RUB;
        $providerEnum = ProviderEnum::CBR;

        $provider = $this->createMock(ProviderRateInterface::class);
        $provider->method('getBaseCurrency')->willReturn(Currencies::RUB);
        $provider->method('getId')->willReturn(1);
        $provider->method('getEnum')->willReturn($providerEnum);

        $this->providerRegistry->method('get')->with($providerEnum)->willReturn($provider);
        $this->rateCache->method('get')->willReturn(null);

        $this->repository->method('findTwoLastRates')->willReturn([]);

        $this->expectException(RateNotFoundException::class);

        $this->provider->getRate($date, $currency, $baseCurrency, $providerEnum);
    }
}
