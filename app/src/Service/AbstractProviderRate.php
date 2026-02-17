<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\ProviderRateInterface;
use App\DTO\GetRatesResult;
use App\Exception\FailedProviderException;
use App\Exception\LimitException;
use App\Exception\NotAvailableMethod;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

abstract readonly class AbstractProviderRate implements ProviderRateInterface
{
    protected const int HTTP_CODE_RETRY = 429;

    public function __construct(
        protected readonly HttpClientInterface $httpClient,
        protected readonly LoggerInterface $logger,
        protected readonly int $id,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function isActive(): bool
    {
        return true;
    }

    public function getRequestLimit(): int
    {
        return 0;
    }

    public function getRequestLimitPeriod(): int
    {
        return 0;
    }

    public function getRequestDelay(): int
    {
        return 2;
    }

    public function getHistoryDaysLag(): int
    {
        return 1;
    }

    public function getPeriodDays(): int
    {
        return 60;
    }

    public function getRatesToday(\DateTimeImmutable $date): GetRatesResult
    {
        throw new NotAvailableMethod();
    }

    /**
     * @param array<string, string|string[]> $headers
     * @param array<string, mixed>           $options
     *
     * @return array<mixed>
     */
    protected function jsonRequest(string $url, array $headers = [], string $method = 'GET', array $options = [], int $attempt = 0): array
    {
        $response = $this->request(url: $url, headers: $headers, method: $method, options: $options, attempt: $attempt);

        return $response->toArray();
    }

    /**
     * @param array<string, string|string[]> $headers
     * @param array<string, mixed>           $options
     */
    protected function xmlRequest(string $url, array $headers = [], string $method = 'GET', array $options = [], int $attempt = 0): \SimpleXMLElement
    {
        $response = $this->request(url: $url, headers: $headers, method: $method, options: $options, attempt: $attempt);
        $content = $response->getContent(false);
        if (false === strpos($content, '<?xml')) {
            throw new FailedProviderException('Response content is not XML', $content);
        }
        $data = simplexml_load_string($content);
        if (!$data) {
            throw new FailedProviderException('Failed to parse XML response', $content);
        }

        return $data;
    }

    /**
     * @param array<string, string|string[]> $headers
     * @param array<string, mixed>           $options
     */
    protected function request(string $url, array $headers = [], string $method = 'GET', array $options = [], int $attempt = 0): ResponseInterface
    {
        ++$attempt;
        if (count($headers)) {
            if (isset($options['headers'])) {
                $options['headers'] = array_merge($headers, $options['headers']);
            } else {
                $options['headers'] = $headers;
            }
        }
        if (!isset($options['timeout'])) {
            $options['timeout'] = 10;
        }

        try {
            $response = $this->httpClient->request($method, $url, $options);
            $response->getContent(false);
            $response->getHeaders(false);
        } catch (\Throwable $e) {
            if ($e instanceof TransportExceptionInterface) {
                if ($attempt > 3) {
                    throw $e;
                }
                if ($e instanceof TimeoutExceptionInterface) {
                    $options['timeout'] += 5;
                }
                if (str_contains($e->getMessage(), 'Could not resolve host:')) {
                    $options['resolve'] = $this->getDnsResolveOptions($attempt);
                }

                return $this->request(url: $url, method: $method, options: $options, attempt: $attempt);
            } elseif ($e instanceof HttpExceptionInterface) {
                $headers = $e->getResponse()->getHeaders(false);
                $content = $e->getResponse()->getContent(false);
                $statusCode = $e->getResponse()->getStatusCode();

                if (self::HTTP_CODE_RETRY === $statusCode) {
                    $retryAfter = $headers['retry-after'][0] ?? $headers['ratelimit-reset'][0] ?? 86400;
                    throw new LimitException((int) $retryAfter);
                }
                if ('prod' !== getenv('APP_ENV')) {
                    echo PHP_EOL.'<<<TODO: разобраться с этим кейсом: '.PHP_EOL;
                    echo PHP_EOL.'Response code : '.$e->getResponse()->getStatusCode().PHP_EOL;
                    echo 'Headers: '.PHP_EOL;
                    print_r($headers);
                    echo 'Response: '.PHP_EOL;
                    print_r($content);
                    print_r(PHP_EOL);
                }
            } else {
                if ('prod' !== getenv('APP_ENV')) {
                    echo PHP_EOL.'<<<TODO: разобраться с этим кейсом: '.PHP_EOL;
                }
            }
            if ('prod' !== getenv('APP_ENV')) {
                print_r(PHP_EOL);
                print_r(PHP_EOL);
                print_r($e::class);
                print_r(PHP_EOL);
                print_r($e->getMessage());
                print_r(PHP_EOL);
                print_r(PHP_EOL);
                print_r($e->getTraceAsString());
                print_r(PHP_EOL);
            }
            throw new \Exception('TODO>>>', 0, $e);
        }

        $this->logRequest($url, $response);

        return $response;
    }

    /**
     * @return array<string, string>
     */
    protected function getDnsResolveOptions(int $attempt = 0): array
    {
        return [];
    }

    protected function logRequest(string $url, ResponseInterface $response): void
    {
        try {
            if ('prod' === getenv('APP_ENV')) {
                return;
            }

            $logDir = 'var/log/requests';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0777, true);
            }

            $safeUrl = preg_replace('/https?:\/\/(www.)?/i', '', $url);
            $safeUrl = preg_replace('/[^a-z0-9.]/i', '_', $safeUrl);
            $logFile = $logDir.'/'.$this->getEnum()->value.'_'.$safeUrl;
            if (strlen($logFile) > 255) {
                $logFile = substr($logFile, 0, 200).'_'.md5($url);
            }
            $content = trim($response->getContent(false));
            $startSimbol = mb_substr($content, 0, 1);
            if ('{' === $startSimbol || '[' === $startSimbol) {
                $ext = '.json';
            } elseif ('<' === $startSimbol) {
                $ext = '.xml';
            } else {
                $ext = '.txt';
            }
            if (file_exists($logFile.$ext)) {
                $logFile .= '_'.str_replace(' ', '_', microtime());
            }
            file_put_contents($logFile.$ext, $content);
        } catch (\Throwable $e) {
            $this->logger->error('Fail log request save: '.$e->getMessage(), ['trace' => $e->getTrace(), 'class' => $e::class]);
        }
    }
}
