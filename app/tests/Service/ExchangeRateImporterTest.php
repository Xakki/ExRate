<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Contract\CacheManagerInterface;
use App\Contract\RateSourceInterface;
use App\Entity\ExchangeRate;
use App\Enum\RateSource;
use App\Message\FetchRateMessage;
use App\Repository\ExchangeRateRepository;
use App\Service\ExchangeRateImporter;
use App\Service\ExchangeRateProvider;
use App\Service\RateSource\Dto\GetRatesResult;
use App\Service\RateSourceRegistry;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class ExchangeRateImporterTest extends TestCase
{
    /** @var RateSourceRegistry&MockObject */
    private RateSourceRegistry $rateSourceRegistry;
    /** @var ExchangeRateRepository&MockObject */
    private ExchangeRateRepository $repository;
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;
    /** @var CacheManagerInterface&MockObject */
    private CacheManagerInterface $cache;
    /** @var MessageBusInterface&MockObject */
    private MessageBusInterface $bus;
    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;
    /** @var ExchangeRateProvider&MockObject */
    private ExchangeRateProvider $provider;
    private ExchangeRateImporter $importer;

    protected function setUp(): void
    {
        $this->rateSourceRegistry = $this->createMock(RateSourceRegistry::class);
        $this->repository = $this->createMock(ExchangeRateRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->cache = $this->createMock(CacheManagerInterface::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->provider = $this->createMock(ExchangeRateProvider::class);

        $this->importer = new ExchangeRateImporter(
            $this->rateSourceRegistry,
            $this->repository,
            $this->em,
            $this->cache,
            $this->bus,
            $this->logger,
            $this->provider
        );
    }

    public function testFetchAndSaveRatesWhenRatesExist(): void
    {
        $date = new \DateTimeImmutable('2024-01-01');
        $rateSource = RateSource::CBR;

        $source = $this->createMock(RateSourceInterface::class);
        $source->method('getBaseCurrency')->willReturn('RUR');
        $source->method('getId')->willReturn(1);

        $this->rateSourceRegistry->method('get')->with($rateSource)->willReturn($source);
        $this->provider->method('getCorrectedDay')->with($rateSource, $date)->willReturn($date);
        $this->repository->method('existRates')->with($date, 'RUR', 1)->willReturn(true);

        $this->logger->expects($this->once())->method('info')->with('Already exist rate for date 2024-01-01');
        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $this->importer->fetchAndSaveRates($date, $rateSource);
    }

    public function testFetchAndSaveRatesNewRates(): void
    {
        $date = new \DateTimeImmutable('2024-01-01');
        $rateSource = RateSource::CBR;
        $rates = ['USD' => '75.0', 'EUR' => '90.0'];

        $source = $this->createMock(RateSourceInterface::class);
        $source->method('getBaseCurrency')->willReturn('RUR');
        $source->method('getId')->willReturn(1);
        $source->method('getRates')->with($date)->willReturn(new GetRatesResult($date, $rates));

        $this->rateSourceRegistry->method('get')->with($rateSource)->willReturn($source);
        $this->provider->method('getCorrectedDay')->with($rateSource, $date)->willReturn($date);
        $this->repository->method('existRates')->with($date, 'RUR', 1)->willReturn(false);

        $this->em->expects($this->exactly(2))->method('persist')->with($this->isInstanceOf(ExchangeRate::class));
        $this->em->expects($this->once())->method('flush');

        $this->importer->fetchAndSaveRates($date, $rateSource);
    }

    public function testFetchAndSaveRatesWithDateCorrection(): void
    {
        $expectDate = new \DateTimeImmutable('2024-01-02');
        $actualDate = new \DateTimeImmutable('2024-01-01');
        $rateSource = RateSource::CBR;
        $rates = ['USD' => '75.0'];

        $source = $this->createMock(RateSourceInterface::class);
        $source->method('getBaseCurrency')->willReturn('RUR');
        $source->method('getId')->willReturn(1);
        $source->method('getRates')->with($actualDate)->willReturn(new GetRatesResult($actualDate, $rates));

        $this->rateSourceRegistry->method('get')->with($rateSource)->willReturn($source);
        $this->provider->method('getCorrectedDay')->with($rateSource, $expectDate)->willReturn($actualDate);
        $this->repository->method('existRates')->with($actualDate, 'RUR', 1)->willReturn(false);

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(FetchRateMessage::class))
            ->willReturn(new Envelope(new \stdClass()));

        $this->cache->expects($this->once())
            ->method('set')
            ->with('corrected_day_cbr_2024-01-02', '2024-01-01');

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $this->importer->fetchAndSaveRates($expectDate, $rateSource);
    }
}
