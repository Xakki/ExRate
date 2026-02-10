<?php

declare(strict_types=1);

namespace App\Provider;

use App\Contract\ProviderInterface;
use App\DTO\GetRatesResult;
use App\Enum\ProviderEnum;
use App\Exception\DisabledProviderException;
use App\Util\BcMath;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://currencydatafeed.com/documentation
 */
final readonly class CurrencyDataFeedProvider implements ProviderInterface
{
    public const string URL = 'https://currencydatafeed.com/api/data.php';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $token,
        private int $id,
        private int $currencyPrecision,
    ) {
        if (empty($this->token)) {
            throw new DisabledProviderException('Provider disabled: Need API key');
        }
    }

    public static function getServiceName(): string
    {
        return 'provider.currency_data_feed';
    }

    public function isActive(): bool
    {
        return !empty($this->token);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::CURRENCY_DATA_FEED;
    }

    public function getBaseCurrency(): string
    {
        return 'USD';
    }

    public function getHomePage(): string
    {
        return 'https://currencydatafeed.com';
    }

    public function getDescription(): string
    {
        return 'Currency API delivering real-time FX & crypto rates, historical data and a powerful currency converter. Simple REST & WebSocket endpoints since 2015.';
    }

    public function getRates(\DateTimeImmutable $date): GetRatesResult
    {
        $response = $this->httpClient->request('GET', self::URL, [
            'query' => [
                'token' => $this->token,
                'currency' => 'USD/EUR,USD/GBP,USD/JPY,USD/RUB', // Example
            ],
        ]);

        $content = $response->getContent();
        $data = json_decode($content, true);

        if (!is_array($data) || !$data['status']) {
            throw new \RuntimeException('Failed to parse CurrencyDataFeed response');
        }

        $rates = [];
        $responseDate = $date;

        foreach ($data['currency'] as $item) {
            $pair = $item['currency']; // e.g. USD/EUR
            $code = substr($pair, 4);
            $rates[$code] = BcMath::round((string) $item['value'], $this->currencyPrecision);
            $responseDate = new \DateTimeImmutable($item['date']);
        }

        return new GetRatesResult($this->getId(), $this->getBaseCurrency(), $responseDate, $rates);
    }

    public function getAvailableCurrencies(): array
    {
        return ['AED', 'AFN', 'ALL', 'AMD', 'ANG', 'AOA', 'ARS', 'AUD', 'AWG', 'AZN', 'BAM', 'BBD', 'BDT', 'BGN', 'BHD', 'BIF', 'BMD', 'BND', 'BOB', 'BRL', 'BSD', 'BTC', 'BTN', 'BWP', 'BYN', 'BZD', 'CAD', 'CDF', 'CHF', 'CLF', 'CLP', 'CNH', 'CNY', 'COP', 'CRC', 'CUC', 'CUP', 'CVE', 'CZK', 'DJF', 'DKK', 'DOP', 'DZD', 'EGP', 'ERN', 'ETB', 'EUR', 'FJD', 'FKP', 'GBP', 'GEL', 'GGP', 'GHS', 'GIP', 'GMD', 'GNF', 'GTQ', 'GYD', 'HKD', 'HNL', 'HRK', 'HTG', 'HUF', 'IDR', 'ILS', 'IMP', 'INR', 'IQD', 'IRR', 'ISK', 'JEP', 'JMD', 'JOD', 'JPY', 'KES', 'KGS', 'KHR', 'KMF', 'KPW', 'KRW', 'KWD', 'KYD', 'KZT', 'LAK', 'LBP', 'LKR', 'LRD', 'LSL', 'LYD', 'MAD', 'MDL', 'MGA', 'MKD', 'MMK', 'MNT', 'MOP', 'MRU', 'MUR', 'MVR', 'MWK', 'MXN', 'MYR', 'MZN', 'NAD', 'NGN', 'NIO', 'NOK', 'NPR', 'NZD', 'OMR', 'PAB', 'PEN', 'PGK', 'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RSD', 'RUB', 'RWF', 'SAR', 'SBD', 'SCR', 'SDG', 'SEK', 'SGD', 'SHP', 'SLE', 'SLL', 'SOS', 'SRD', 'SSP', 'STD', 'STN', 'SVC', 'SYP', 'SZL', 'THB', 'TJS', 'TMT', 'TND', 'TOP', 'TRY', 'TTD', 'TWD', 'TZS', 'UAH', 'UGX', 'USD', 'UYU', 'UZS', 'VEF', 'VES', 'VND', 'VUV', 'WST', 'XAF', 'XAG', 'XAU', 'XCD', 'XCG', 'XDR', 'XOF', 'XPD', 'XPF', 'XPT', 'YER', 'ZAR', 'ZMW', 'ZWG', 'ZWL'];
    }

    public function getRequestLimit(): int
    {
        return 100;
    }

    public function getRequestLimitPeriod(): int
    {
        return 86400;
    }

    public function getRequestDelay(): int
    {
        return 2;
    }
}
