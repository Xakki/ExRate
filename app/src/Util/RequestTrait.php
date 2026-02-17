<?php

declare(strict_types=1);

namespace App\Util;

use App\Exception\FailedProviderException;
use App\Exception\LimitException;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

trait RequestTrait
{
    // private HttpClientInterface $httpClient;

    /**
     * @param array<string, string|string[]> $headers
     * @param array<string, mixed>           $options
     *
     * @return array<mixed>
     */
    private function jsonRequest(string $url, array $headers = [], string $method = 'GET', array $options = [], int $repeat = 0): array
    {
        $response = $this->request(url: $url, headers: $headers, method: $method, options: $options, repeat: $repeat);

        return $response->toArray();
    }

    /**
     * @param array<string, string|string[]> $headers
     * @param array<string, mixed>           $options
     */
    private function xmlRequest(string $url, array $headers = [], string $method = 'GET', array $options = [], int $repeat = 0): \SimpleXMLElement
    {
        $response = $this->request(url: $url, headers: $headers, method: $method, options: $options, repeat: $repeat);
        $data = simplexml_load_string($response->getContent(false));
        if (!$data) {
            throw new FailedProviderException('Failed to parse XML response', $response->getContent(false));
        }

        return $data;
    }

    /**
     * @param array<string, string|string[]> $headers
     * @param array<string, mixed>           $options
     */
    private function request(string $url, array $headers = [], string $method = 'GET', array $options = [], int $repeat = 0): ResponseInterface
    {
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
        } catch (\Throwable $e) {
            if ($e instanceof TransportExceptionInterface) {
                if ($repeat > 3) {
                    throw $e;
                }
                if ($e instanceof TimeoutExceptionInterface) {
                    $options['timeout'] += 5;
                }

                return $this->request(url: $url, method: $method, options: $options, repeat: ++$repeat);
            } elseif ($e instanceof HttpExceptionInterface) {
                $statusCode = $e->getResponse()->getStatusCode();
                $headers = $e->getResponse()->getHeaders(false);
                $content = $e->getResponse()->getContent(false);
                if (429 === $statusCode) {
                    $retryAfter = $headers['retry-after'][0] ?? $headers['ratelimit-reset'][0] ?? 86400;
                    throw new LimitException((int) $retryAfter);
                }
                echo PHP_EOL.'<<<TODO: разобраться с этим кейсом: '.PHP_EOL;
                echo PHP_EOL.'Response code : '.$e->getResponse()->getStatusCode().PHP_EOL;
                echo 'Headers: '.PHP_EOL;
                print_r($headers);
                echo 'Response: '.PHP_EOL;
                print_r($content);
                print_r(PHP_EOL);
            } else {
                echo PHP_EOL.'<<<TODO: разобраться с этим кейсом: '.PHP_EOL;
            }

            print_r(PHP_EOL);
            print_r(PHP_EOL);
            print_r($e::class);
            print_r(PHP_EOL);
            print_r($e->getMessage());
            print_r(PHP_EOL);
            print_r(PHP_EOL);
            print_r($e->getTraceAsString());
            print_r(PHP_EOL);

            throw new \Exception('TODO>>>');
        }

        return $response;
    }
}
