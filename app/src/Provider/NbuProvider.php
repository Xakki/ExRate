<?php

declare(strict_types=1);

namespace App\Provider;

use App\Contract\ProviderInterface;
use App\DTO\GetRatesResult;
use App\Enum\ProviderEnum;
use App\Util\BcMath;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://bank.gov.ua/en/markets/exchangerates
 */
final readonly class NbuProvider implements ProviderInterface
{
    public const string URL = 'https://bank.gov.ua/NBUStatService/v1/statdirectory/exchange';

    public function __construct(
        private HttpClientInterface $httpClient,
        private int $id,
        private int $currencyPrecision,
    ) {
    }

    public static function getServiceName(): string
    {
        return 'provider.nbu';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::NBU;
    }

    public function getBaseCurrency(): string
    {
        return 'UAH';
    }

    public function getHomePage(): string
    {
        return 'https://bank.gov.ua';
    }

    public function getDescription(): string
    {
        return 'National Bank of Ukraine';
    }

    public function getRates(\DateTimeImmutable $date): GetRatesResult
    {
        $formattedDate = $date->format('Ymd');
        $url = self::URL.'?date='.$formattedDate;

        $response = $this->httpClient->request('GET', $url);
        $content = $response->getContent();

        $xml = simplexml_load_string($content);

        if (false === $xml) {
            throw new \RuntimeException('Failed to parse NBU XML response');
        }

        $rates = [];
        $responseDate = $date;

        foreach ($xml->currency as $item) {
            $code = (string) $item->cc;
            $rate = (string) $item->rate;

            if ($code && $rate) {
                $rates[$code] = BcMath::round($rate, $this->currencyPrecision);
            }

            if (isset($item->exchangedate)) {
                $responseDate = \DateTimeImmutable::createFromFormat('d.m.Y', (string) $item->exchangedate) ?: $responseDate;
            }
        }

        return new GetRatesResult($this->getId(), $this->getBaseCurrency(), $responseDate, $rates);
    }

    public function isActive(): bool
    {
        return true;
    }

    public function getAvailableCurrencies(): array
    {
        return ['AED', 'AUD', 'AZN', 'BDT', 'CAD', 'CHF', 'CNY', 'CZK', 'DKK', 'DZD', 'EGP', 'EUR', 'GBP', 'GEL', 'HKD', 'HUF', 'IDR', 'ILS', 'INR', 'JPY', 'KRW', 'KZT', 'LBP', 'MDL', 'MXN', 'MYR', 'NOK', 'NZD', 'PLN', 'RON', 'RSD', 'SAR', 'SEK', 'SGD', 'THB', 'TND', 'TRY', 'USD', 'VND', 'XAG', 'XAU', 'XPD', 'XPT', 'XDR', 'ZAR'];
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
