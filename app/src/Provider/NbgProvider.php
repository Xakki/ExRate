<?php

declare(strict_types=1);

namespace App\Provider;

use App\Contract\ProviderInterface;
use App\DTO\GetRatesResult;
use App\Enum\ProviderEnum;
use App\Util\BcMath;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://nbg.gov.ge/en/monetary-policy/currency
 */
final readonly class NbgProvider implements ProviderInterface
{
    public const string BASE_URL = 'https://nbg.gov.ge/gw/api/ct/monetarypolicy/currencies/en/json';

    public function __construct(
        private HttpClientInterface $httpClient,
        private int $id,
        private int $currencyPrecision,
    ) {
    }

    public static function getServiceName(): string
    {
        return 'provider.nbg';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::NBG;
    }

    public function getBaseCurrency(): string
    {
        return 'GEL';
    }

    public function getHomePage(): string
    {
        return 'https://nbg.gov.ge';
    }

    public function getDescription(): string
    {
        return 'National Bank of Georgia';
    }

    public function getRates(\DateTimeImmutable $date): GetRatesResult
    {
        $url = self::BASE_URL.'?date='.$date->format('Y-m-d');

        $response = $this->httpClient->request('GET', $url);
        $content = $response->getContent();
        $data = json_decode($content, true);

        if (!is_array($data) || !isset($data[0])) {
            throw new \RuntimeException('Failed to parse NBG JSON response');
        }

        $element = $data[0];
        $responseDate = new \DateTimeImmutable((string) $element['date']);
        $rates = [];

        foreach ($element['currencies'] as $currency) {
            $code = $currency['code'];
            $rateStr = (string) $currency['rate'];
            $quantity = (string) $currency['quantity'];

            $rates[$code] = BcMath::div($rateStr, $quantity, $this->currencyPrecision);
        }

        return new GetRatesResult($this->getId(), $this->getBaseCurrency(), $responseDate, $rates);
    }

    public function isActive(): bool
    {
        return true;
    }

    public function getAvailableCurrencies(): array
    {
        return ['AED', 'AMD', 'AUD', 'AZN', 'BRL', 'BYN', 'CAD', 'CHF', 'CNY', 'CZK', 'DKK', 'EGP', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'INR', 'IRR', 'ISK', 'JPY', 'KGS', 'KRW', 'KWD', 'KZT', 'MDL', 'NOK', 'NZD', 'PLN', 'QAR', 'RON', 'RSD', 'RUB', 'SEK', 'SGD', 'TJS', 'TMT', 'TRY', 'UAH', 'USD', 'UZS', 'ZAR'];
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
