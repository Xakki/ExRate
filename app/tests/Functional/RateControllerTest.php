<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Contract\Cache\RateCacheInterface;
use App\Entity\ExchangeRate;
use App\Repository\ExchangeRateRepository;
use App\Tests\WebTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[AllowMockObjectsWithoutExpectations]
class RateControllerTest extends WebTestCase
{
    public function testGetRateValidationFail(): void
    {
        $client = static::createClient();
        $this->setUpMocks($client, 0, new \DateTimeImmutable('2026-02-10'), new \DateTimeImmutable('2026-02-09'));

        $resp = $this->jsonRequest($client, 'GET', '/api/v1/rate', 400, [
            ['level' => 'error', 'message' => '/This value should not be blank/'],
        ]);
        $this->assertArrayHasKey('title', $resp);
        $this->assertArrayHasKey('status', $resp);
        $this->assertArrayIsEqualToArrayOnlyConsideringListOfKeys([
            'title' => 'Validation Failed',
            'status' => '400',
            'detail' => 'currency: This value should not be blank.',
        ], $resp, ['title', 'status', 'detail']);
    }

    public function testGetRateNotFound(): void
    {
        static::ensureKernelShutdown();
        $client = static::createClient();
        $this->setUpMocks($client, 0, new \DateTimeImmutable('2026-02-10'), new \DateTimeImmutable('2026-02-09'));
        $url = '/api/v1/rate?currency=USD&date=2026-02-10';
        $data = $this->jsonRequest($client, 'GET', $url, 202);
        $this->assertEquals('', $data['rate']);
        $this->assertEquals('', $data['diff']);
    }

    public function testGetRateFoundNoDiff(): void
    {
        static::ensureKernelShutdown();
        $client = static::createClient();
        $this->setUpMocks($client, 1, new \DateTimeImmutable('2026-02-10'), new \DateTimeImmutable('2026-02-09'));
        $url = '/api/v1/rate?currency=USD&date=2026-02-10';
        $data = $this->jsonRequest($client, 'GET', $url, 202);
        $this->assertEquals('75.0', $data['rate']);
        $this->assertEquals('', $data['diff']);
    }

    public function testGetRateFoundWithDiff(): void
    {
        static::ensureKernelShutdown();
        $client = static::createClient();
        $this->setUpMocks($client, 2, new \DateTimeImmutable('2026-02-10'), new \DateTimeImmutable('2026-02-09'));
        $url = '/api/v1/rate?currency=USD&date=2026-02-10';
        $data = $this->jsonRequest($client, 'GET', $url);
        $this->assertEquals('75.0', $data['rate']);
        $this->assertEquals('2026-02-10', $data['date']);
        $this->assertEquals('-3.00000000', $data['diff']);
        $this->assertEquals('2026-02-09', $data['date_diff']);
    }

    private function setUpMocks(KernelBrowser $client, int $step, \DateTimeImmutable $today, \DateTimeImmutable $yesterday): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $client->getContainer()->set(MessageBusInterface::class, $bus);

        $cache = $this->createMock(RateCacheInterface::class);
        $cache->method('get')->willReturn(null);
        $client->getContainer()->set(RateCacheInterface::class, $cache);

        $mockRepository = $this->createMock(ExchangeRateRepository::class);

        $mockRepository->method('findOneByDateAndCurrency')
            ->willReturnCallback(function (int $providerId, string $currency, string $baseCurrency, \DateTimeImmutable $date) use ($step, $today, $yesterday) {
                $requestedDate = $date->format('Y-m-d');

                if (0 === $step) {
                    return null;
                }

                if (1 <= $step && $requestedDate === $today->format('Y-m-d')) {
                    return new ExchangeRate($today, 'USD', 'RUB', '75.0', 1);
                }

                if (2 <= $step && $requestedDate === $yesterday->format('Y-m-d')) {
                    return new ExchangeRate($yesterday, 'USD', 'RUB', '78.0', 1);
                }

                return null;
            });

        $client->getContainer()->set(ExchangeRateRepository::class, $mockRepository);
    }
}
