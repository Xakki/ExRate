<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Contract\CacheManagerInterface;
use App\Contract\RateSourceInterface;
use App\DTO\RateResponse;
use App\Entity\ExchangeRate;
use App\Enum\RateSource;
use App\Exception\RateNotFoundException;
use App\Message\FetchRateMessage;
use App\Repository\ExchangeRateRepository;
use App\Service\ExchangeRateProvider;
use App\Service\RateSourceRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class ExchangeRateProviderTest extends TestCase
{
    /** @var RateSourceRegistry&MockObject */
    private RateSourceRegistry $rateSourceRegistry;
    /** @var ExchangeRateRepository&MockObject */
    private ExchangeRateRepository $repository;
    /** @var CacheManagerInterface&MockObject */
    private CacheManagerInterface $cache;
    /** @var MessageBusInterface&MockObject */
    private MessageBusInterface $bus;
    private ExchangeRateProvider $provider;

    protected function setUp(): void
    {
        $this->rateSourceRegistry = $this->createMock(RateSourceRegistry::class);
        $this->repository = $this->createMock(ExchangeRateRepository::class);
        $this->cache = $this->createMock(CacheManagerInterface::class);
        $this->bus = $this->createMock(MessageBusInterface::class);

        $this->provider = new ExchangeRateProvider(
            $this->rateSourceRegistry,
            $this->repository,
            $this->cache,
            $this->bus,
            4 // currencyPrecision
        );
    }

    public function testGetRateFromCache(): void
    {
        $date = new \DateTimeImmutable('2024-01-01');
        $currency = 'USD';
        $baseCurrency = 'RUR';
        $rateSource = RateSource::CBR;

        $cachedResponse = new RateResponse('75.0', null, '2024-01-01', null, false);

        $correctedDayKey = 'corrected_day_cbr_2024-01-01';
        $rateKey = 'rate_2024-01-01_USD_RUR_cbr';

        $this->cache->method('get')
            ->willReturnCallback(function ($key) use ($correctedDayKey, $rateKey, $cachedResponse) {
                if ($key === $correctedDayKey) {
                    return null;
                }
                if ($key === $rateKey) {
                    return $cachedResponse;
                }

                return null;
            });

        $result = $this->provider->getRate($date, $currency, $baseCurrency, $rateSource);

        $this->assertSame($cachedResponse, $result);
    }

    public function testGetRateDirectFound(): void
    {
        $date = new \DateTimeImmutable('2024-01-01');
        $currency = 'USD';
        $baseCurrency = 'RUR';
        $rateSource = RateSource::CBR;

        $source = $this->createMock(RateSourceInterface::class);
        $source->method('getBaseCurrency')->willReturn('RUR');
        $source->method('getId')->willReturn(1);
        $source->method('getEnum')->willReturn($rateSource);

        $this->rateSourceRegistry->method('get')->with($rateSource)->willReturn($source);
        $this->cache->method('get')->willReturn(null);

        $rateEntity = new ExchangeRate();
        $rateEntity->setRate('75.0');
        $rateEntity->setDate($date);
        $rateEntity->setCurrency($currency);
        $rateEntity->setBaseCurrency($baseCurrency);

        $previousRateEntity = new ExchangeRate();
        $previousRateEntity->setRate('74.0');
        $previousRateEntity->setDate($date->modify('-1 day'));

        $this->repository->method('findOneByDateAndCurrency')
            ->willReturnCallback(function ($argDate) use ($date, $rateEntity, $previousRateEntity) {
                if ($argDate->format('Y-m-d') === $date->format('Y-m-d')) {
                    return $rateEntity;
                }
                if ($argDate->format('Y-m-d') === $date->modify('-1 day')->format('Y-m-d')) {
                    return $previousRateEntity;
                }

                return null;
            });

        $result = $this->provider->getRate($date, $currency, $baseCurrency, $rateSource);

        $this->assertEquals('75.0', $result->rate);
        $this->assertEquals('1.0000', $result->diff);
        $this->assertFalse($result->isFallback);
    }

    public function testGetRateDirectNotFound(): void
    {
        $date = new \DateTimeImmutable('2024-01-01');
        $currency = 'USD';
        $baseCurrency = 'RUR';
        $rateSource = RateSource::CBR;

        $source = $this->createMock(RateSourceInterface::class);
        $source->method('getBaseCurrency')->willReturn('RUR');
        $source->method('getId')->willReturn(1);
        $source->method('getEnum')->willReturn($rateSource);

        $this->rateSourceRegistry->method('get')->with($rateSource)->willReturn($source);
        $this->cache->method('get')->willReturn(null);

        $this->repository->method('findOneByDateAndCurrency')->willReturn(null);

        $this->bus->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->isInstanceOf(FetchRateMessage::class))
            ->willReturn(new Envelope(new \stdClass()));

        $this->expectException(RateNotFoundException::class);

        $this->provider->getRate($date, $currency, $baseCurrency, $rateSource);
    }

    public function testGetCorrectedDayCached(): void
    {
        $date = new \DateTimeImmutable('2024-01-02');
        $rateSource = RateSource::CBR;

        $this->cache->method('get')
            ->with('corrected_day_cbr_2024-01-02')
            ->willReturn('2024-01-01');

        $result = $this->provider->getCorrectedDay($rateSource, $date);

        $this->assertEquals('2024-01-01', $result->format('Y-m-d'));
    }

    public function testGetCorrectedDayNotCached(): void
    {
        $date = new \DateTimeImmutable('2024-01-02');
        $rateSource = RateSource::CBR;

        $this->cache->method('get')->willReturn(null);

        $result = $this->provider->getCorrectedDay($rateSource, $date);

        $this->assertEquals('2024-01-02', $result->format('Y-m-d'));
    }
}
