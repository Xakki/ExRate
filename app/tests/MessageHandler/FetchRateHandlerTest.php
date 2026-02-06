<?php

namespace App\Tests\MessageHandler;

use App\Message\FetchRateMessage;
use App\MessageHandler\FetchRateHandler;
use App\Service\ExchangeRateService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\LimiterInterface;

class FetchRateHandlerTest extends TestCase
{
    private $service;
    private $limiterFactory;
    private $bus;
    private $logger;
    private $handler;
    private $limiter;

    protected function setUp(): void
    {
        $this->service = $this->createMock(ExchangeRateService::class);
        $this->limiterFactory = $this->createMock(RateLimiterFactory::class);
        $this->limiter = $this->createMock(LimiterInterface::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->limiterFactory->method('create')->willReturn($this->limiter);

        $this->handler = new FetchRateHandler(
            $this->service,
            $this->limiterFactory,
            $this->bus,
            $this->logger
        );
    }

    public function testSuccessfulExecution(): void
    {
        $message = new FetchRateMessage('2023-10-01', 'USD', 'RUB');

        // Mock limiter accepted
        $rateLimit = $this->createMock(RateLimit::class);
        $rateLimit->method('isAccepted')->willReturn(true);
        $this->limiter->method('consume')->willReturn($rateLimit);

        // Expect service call
        $this->service->expects($this->once())
            ->method('getRate')
            ->with(
                $this->callback(fn($date) => $date->format('Y-m-d') === '2023-10-01'),
                'USD',
                'RUB'
            );

        // Expect no bus dispatch
        $this->bus->expects($this->never())->method('dispatch');

        ($this->handler)($message);
    }

    public function testRateLimitExceeded(): void
    {
        $message = new FetchRateMessage('2023-10-01', 'USD', 'RUB');

        // Mock limiter NOT accepted
        $rateLimit = $this->createMock(RateLimit::class);
        $rateLimit->method('isAccepted')->willReturn(false);
        $this->limiter->method('consume')->willReturn($rateLimit);

        // Expect NO service call
        $this->service->expects($this->never())->method('getRate');

        // Expect bus dispatch with delay
        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with(
                $message,
                $this->callback(function ($stamps) {
                    foreach ($stamps as $stamp) {
                        if ($stamp instanceof DelayStamp) {
                            $delay = $stamp->getDelay();
                            // 60 to 600 seconds * 1000 ms
                            return $delay >= 60000 && $delay <= 600000;
                        }
                    }
                    return false;
                })
            )
            ->willReturn(new Envelope($message));

        ($this->handler)($message);
    }

    public function testServiceErrorRetryLogic(): void
    {
        $message = new FetchRateMessage('2023-10-01', 'USD', 'RUB');

        // Mock limiter accepted
        $rateLimit = $this->createMock(RateLimit::class);
        $rateLimit->method('isAccepted')->willReturn(true);
        $this->limiter->method('consume')->willReturn($rateLimit);

        // Expect service call throwing exception
        $this->service->expects($this->once())
            ->method('getRate')
            ->willThrowException(new \Exception('Service unavailable'));

        // Expect bus dispatch with 1 minute delay (first retry)
        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(fn($msg) => $msg->retryCount === 1),
                $this->callback(function ($stamps) {
                    foreach ($stamps as $stamp) {
                        if ($stamp instanceof DelayStamp) {
                            return $stamp->getDelay() === 60000; // 60 seconds
                        }
                    }
                    return false;
                })
            )
            ->willReturn(new Envelope($message));

        ($this->handler)($message);
    }

    public function testMaxRetriesReached(): void
    {
        $message = new FetchRateMessage('2023-10-01', 'USD', 'RUB', 3); // Already retried 3 times (0, 1, 2 done)

        // Mock limiter accepted
        $rateLimit = $this->createMock(RateLimit::class);
        $rateLimit->method('isAccepted')->willReturn(true);
        $this->limiter->method('consume')->willReturn($rateLimit);

        // Expect service call throwing exception
        $this->service->expects($this->once())
            ->method('getRate')
            ->willThrowException(new \Exception('Service unavailable'));

        // Expect NO bus dispatch (max retries reached)
        $this->bus->expects($this->never())->method('dispatch');

        // Expect error log
        $this->logger->expects($this->exactly(2))->method('error'); // 1 for exception, 1 for max retries

        ($this->handler)($message);
    }
}
