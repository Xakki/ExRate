<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Contract\Cache\RateLimitCacheInterface;
use App\Enum\FetchStatusEnum;
use App\Exception\DisabledProviderException;
use App\Message\FetchRateMessage;
use App\Service\ProviderImporter;
use App\Service\ProviderRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;

#[AsMessageHandler]
class FetchRateHandler
{
    public function __construct(
        private readonly ProviderImporter $importer,
        private readonly ProviderRegistry $providerRegistry,
        private readonly RateLimitCacheInterface $rateLimitCache,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.environment%')]
        private readonly string $appEnv,
        // Example for rate limiter
        // #[Autowire('%fetch_rate_interval%')]
        // private int $fetchRateInterval,
    ) {
    }

    public function __invoke(FetchRateMessage $message): void
    {
        if ($this->logger instanceof \Monolog\Logger) {
            $processor = function (\Monolog\LogRecord $record) use ($message): \Monolog\LogRecord {
                return $record->with(context: array_merge($record->context, [
                    'provider' => $message->providerEnum->value,
                    'date' => $message->date,
                ]));
            };
            $this->logger->pushProcessor($processor);
        }

        try {
            // $this->logger->info('---RUN---:', ['date' => $message->date->format('Y-m-d')]);
            $provider = $this->providerRegistry->get($message->providerEnum);
            [$status, $correctedDate] = $this->importer->fetchAndSaveRates($message->providerEnum, $message->date);
            if ($message->loadPrevious) {
                $noRate = 0;
                if (FetchStatusEnum::EMPTY === $status) {
                    if ($message->noRate > 10) {
                        $this->logger->info('No more rates available for :'.$message->providerEnum->value);

                        return;
                    }
                    $noRate += $message->noRate + 1;
                }
                // загружаем предыдущий день
                $delaySecund = 0;
                if (FetchStatusEnum::SUCCESS === $status) {
                    // Если данные были получены, выполняем загрузку предыдущего дня с задержкой в секунду
                    $delaySecund = $provider->getRequestDelay();
                }

                $dateNext = $message->date->modify('-1 day');
                if ($correctedDate->diff($dateNext)->d) {
                    $dateNext = $correctedDate;
                }

                $messageNext = new FetchRateMessage($dateNext, $message->providerEnum, --$message->loadPrevious, $noRate);
                // $this->logger->info('---NEXT---:', ['date' => $dateNext->format('Y-m-d')]);
                $this->bus->dispatch($messageNext, $messageNext->getStamps($delaySecund, $this->rateLimitCache, $provider));
            }
        } catch (DisabledProviderException $e) {
            // skipp
        } catch (\Throwable $e) {
            $delayMinutes = 10;
            $context = [
                'exception' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => array_slice($e->getTrace(), 0, 5),
            ];
            try {
                if ($e instanceof ClientExceptionInterface) {
                    $context = [];
                    $context['http_info'] = $e->getResponse()->getInfo('http_code');
                    $context['http_content'] = $e->getResponse()->getContent(false);

                    $res = json_decode($context['http_content'], true);
                    if ($res && isset($res['message'])) {
                        $context['exception'] = $e->getMessage();
                        $this->logger->notice($res['message'], $context);
                    } else {
                        $this->logger->warning($e->getMessage(), $context);
                    }
                } else {
                    $mess = $e->getMessage();
                    $context['trace'] = json_encode(array_slice($e->getTrace(), 0, 3));
                    $this->logger->error('Error fetching rate: '.$mess, $context);
                }
            } catch (\Throwable $e) {
                $this->logger->warning($e->getMessage(), $context);
            }

            if ('test' === $this->appEnv) {
                // For test dont retry
                return;
            }

            // Retry logic: use multiplier for $delayMinutes
            $retries = [
                0 => 1,
                1 => 6,
                2 => 144,
                3 => 1008,
            ];

            if (isset($retries[$message->retryCount])) {
                $delayMinutes = $retries[$message->retryCount] * $delayMinutes;
                ++$message->retryCount;

                $this->logger->info('Retrying message in {delay} minutes (attempt {attempt})', [
                    'delay' => $delayMinutes,
                    'attempt' => $message->retryCount,
                ]);

                $this->bus->dispatch($message, [
                    new DelayStamp($delayMinutes * 60000),
                ]);
            } else {
                $this->logger->error('Max retries reached', $context);
                throw $e;
            }
        } finally {
            if ($this->logger instanceof \Monolog\Logger) {
                $this->logger->popProcessor();
            }
        }
        // rate limit
        // $this->logger->info('Sleep '.$this->fetchRateInterval);
        // sleep($this->fetchRateInterval);
    }
}
