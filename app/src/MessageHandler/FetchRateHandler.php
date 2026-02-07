<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\FetchRateMessage;
use App\Service\ExchangeRateImporter;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[AsMessageHandler]
class FetchRateHandler
{
    public function __construct(
        private ExchangeRateImporter $importer,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
        #[Autowire('%fetch_rate_interval%')]
        private int $fetchRateInterval,
    ) {
    }

    public function __invoke(FetchRateMessage $message): void
    {
        try {
            $this->importer->fetchAndSaveRates($message->date, $message->rateSource);
        } catch (\Exception $e) {
            $this->logger->error('Error fetching rate: '.$e->getMessage(), [
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
                ++$message->retryCount;

                $this->logger->info('Retrying message in {delay} seconds (attempt {attempt})', [
                    'delay' => $delay,
                    'attempt' => $message->retryCount,
                ]);

                $this->bus->dispatch($message, [
                    new DelayStamp($delay * 1000),
                ]);
            } else {
                $this->logger->error('Max retries reached for message', ['message' => $message]);
            }
        }
        // rate limit
        sleep($this->fetchRateInterval);
    }
}
