<?php

declare(strict_types=1);

namespace App\Service\RateSource;

use App\Contract\RateSourceInterface;
use App\Enum\RateSource;
use App\Service\RateSource\Dto\GetRatesResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class CbrRateSource implements RateSourceInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $url,
        private int $id,
        private int $currencyPrecision,
    ) {
    }

    public static function getServiceName(): string
    {
        return 'rate_source.cbr';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEnum(): RateSource
    {
        return RateSource::CBR;
    }

    public function getBaseCurrency(): string
    {
        return 'RUB';
    }

    public function getRates(\DateTimeImmutable $date): GetRatesResult
    {
        // CBR expects date in format dd/mm/yyyy
        $dateStr = $date->format('d/m/Y');

        $response = $this->httpClient->request('GET', $this->url, [
            'query' => [
                'date_req' => $dateStr,
            ],
            'timeout' => 5.0,
        ]);

        $content = $response->getContent();

        $xml = simplexml_load_string($content);

        if (false === $xml) {
            throw new \RuntimeException('Failed to parse CBR XML response');
        }

        $rates = [];
        foreach ($xml->Valute as $valute) {
            $code = (string) $valute->CharCode;
            $valueStr = str_replace(',', '.', (string) $valute->Value);
            $nominal = (string) $valute->Nominal;

            $rates[$code] = bcdiv($valueStr, $nominal, $this->currencyPrecision);
        }

        $responseDate = \DateTimeImmutable::createFromFormat('d.m.Y', (string) $xml['Date']);

        return new GetRatesResult($responseDate, $rates);
    }
}
