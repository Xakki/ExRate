<?php

declare(strict_types=1);

namespace App\Provider;

use App\Contract\ProviderInterface;
use App\DTO\GetRatesResult;
use App\Enum\ProviderEnum;
use App\Util\BcMath;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://cbr.ru/development/SXML/
 */
final readonly class CbrProvider implements ProviderInterface
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
        return 'provider.cbr';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::CBR;
    }

    public function getBaseCurrency(): string
    {
        return 'RUB';
    }

    public function getHomePage(): string
    {
        return 'https://cbr.ru';
    }

    public function getDescription(): string
    {
        return 'Central Bank of the Russian Federation';
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
        if (isset($xml->Valute)) {
            foreach ($xml->Valute as $valute) {
                $code = (string) $valute->CharCode;
                $value = (string) $valute->Value;
                $nominal = (string) $valute->Nominal;

                $rates[$code] = BcMath::div($value, $nominal, $this->currencyPrecision);
            }
        }

        $responseDate = \DateTimeImmutable::createFromFormat('d.m.Y', (string) $xml['Date']);
        if (false === $responseDate) {
            $responseDate = $date;
        }

        return new GetRatesResult($this->getId(), $this->getBaseCurrency(), $responseDate, $rates);
    }

    public function isActive(): bool
    {
        return true;
    }

    public function getAvailableCurrencies(): array
    {
        return ['AED', 'AMD', 'AUD', 'AZN', 'BDT', 'BHD', 'BOB', 'BRL', 'BYN', 'CAD', 'CHF', 'CNY', 'CUP', 'CZK', 'DKK', 'DZD', 'EGP', 'ETB', 'EUR', 'GBP', 'GEL', 'HKD', 'HUF', 'IDR', 'INR', 'IRR', 'JPY', 'KGS', 'KRW', 'KZT', 'MDL', 'MMK', 'MNT', 'NGN', 'NOK', 'NZD', 'OMR', 'PLN', 'QAR', 'RON', 'RSD', 'SAR', 'SEK', 'SGD', 'THB', 'TJS', 'TMT', 'TRY', 'UAH', 'USD', 'UZS', 'VND', 'XDR', 'ZAR'];
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
}
