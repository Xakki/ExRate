<?php

namespace App\MessageHandler;

use App\Message\FetchRateMessage;
use App\Service\ExchangeRateService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\RateLimiter\RateLimiterFactory;

#[AsMessageHandler]
class FetchRateHandler
{
    public function __construct(
        private ExchangeRateService $service,
        private RateLimiterFactory $fetchRateLimiter,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(FetchRateMessage $message): void
    {
        // reserve(1) returns a Reservation object.
        // wait() will block the execution until the token is available.
        // This prevents the "busy loop" of re-dispatching messages to the queue,
        // reducing load on the transport (Redis) and CPU.
        $limiter = $this->fetchRateLimiter->create('global_fetch_rate');
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            // If limit exceeded, delay execution for random 60-600 seconds
            $delay = random_int(60, 600);
            $this->logger->warning('Rate limit exceeded. Retrying in {delay} seconds.', ['delay' => $delay]);

            $this->bus->dispatch($message, [
                new DelayStamp($delay * 1000)
            ]);
            return;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $message->date);

        if (!$date) {
            $this->logger->error('Invalid date format in message', ['date' => $message->date]);
            return;
        }

        try {
            $this->service->getRate($date, $message->currency, $message->baseCurrency);
        } catch (\Exception $e) {
            $this->logger->error('Error fetching rate: ' . $e->getMessage(), [
                'exception' => $e,
                'message' => $message,
            ]);

            // Retry logic: 1, 10, 60 minutes
            $retries = [
                0 => 60,      // 1st retry: 1 min
                1 => 600,     // 2nd retry: 10 min
                2 => 3600,    // 3rd retry: 60 min
            ];

            if (isset($retries[$message->retryCount])) {
                $delay = $retries[$message->retryCount];
                $message->retryCount++;

                $this->logger->info('Retrying message in {delay} seconds (attempt {attempt})', [
                    'delay' => $delay,
                    'attempt' => $message->retryCount
                ]);

                $this->bus->dispatch($message, [
                    new DelayStamp($delay * 1000)
                ]);
            } else {
                $this->logger->error('Max retries reached for message', ['message' => $message]);
            }
        }
    }
}
