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
 * @see https://marketplace.apilayer.com/fixer-api
 */
final readonly class ApiLayerFixerProvider implements ProviderInterface
{
    public const string LATEST_URL = 'https://api.apilayer.com/fixer/latest';
    public const string HISTORICAL_URL = 'https://api.apilayer.com/fixer/%s';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private int $id,
        private int $currencyPrecision,
    ) {
        if (empty($this->apiKey)) {
            throw new DisabledProviderException('Provider disabled: Need API key');
        }
    }

    public static function getServiceName(): string
    {
        return 'provider.api_layer_fixer';
    }

    public function isActive(): bool
    {
        return true;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::API_LAYER_FIXER;
    }

    public function getBaseCurrency(): string
    {
        return 'EUR';
    }

    public function getHomePage(): string
    {
        return 'https://apilayer.com';
    }

    public function getDescription(): string
    {
        return 'APILayer APIs are feature-rich and easy to integrate, offering low latency for an enhanced developer experience.';
    }

    public function getRates(\DateTimeImmutable $date): GetRatesResult
    {
        $isToday = $date->format('Y-m-d') === (new \DateTimeImmutable())->format('Y-m-d');
        $url = $isToday ? self::LATEST_URL : sprintf(self::HISTORICAL_URL, $date->format('Y-m-d'));

        $response = $this->httpClient->request('GET', $url, [
            'headers' => [
                'apikey' => $this->apiKey,
            ],
        ]);

        $content = $response->getContent();
        $data = json_decode($content, true);

        if (!is_array($data) || (isset($data['success']) && !$data['success'])) {
            throw new \RuntimeException($data['error']['info'] ?? 'Failed to parse ApiLayer Fixer response');
        }

        $responseDate = new \DateTimeImmutable($data['date']);
        $rates = [];

        foreach ($data['rates'] as $code => $value) {
            $rates[$code] = BcMath::round((string) $value, $this->currencyPrecision);
        }

        return new GetRatesResult($this->getId(), $this->getBaseCurrency(), $responseDate, $rates);
    }

    public function getAvailableCurrencies(): array
    {
        return ['AED', 'AFN', 'ALL', 'AMD', 'ANG', 'AOA', 'ARS', 'AUD', 'AWG', 'AZN', 'BAM', 'BBD', 'BDT', 'BGN', 'BHD', 'BIF', 'BMD', 'BND', 'BOB', 'BRL', 'BSD', 'BTC', 'BTN', 'BWP', 'BYN', 'BYR', 'BZD', 'CAD', 'CDF', 'CHF', 'CLF', 'CLP', 'CNH', 'CNY', 'COP', 'CRC', 'CUC', 'CUP', 'CVE', 'CZK', 'DJF', 'DKK', 'DOP', 'DZD', 'EGP', 'ERN', 'ETB', 'EUR', 'FJD', 'FKP', 'GBP', 'GEL', 'GGP', 'GHS', 'GIP', 'GMD', 'GNF', 'GTQ', 'GYD', 'HKD', 'HNL', 'HRK', 'HTG', 'HUF', 'IDR', 'ILS', 'IMP', 'INR', 'IQD', 'IRR', 'ISK', 'JEP', 'JMD', 'JOD', 'JPY', 'KES', 'KGS', 'KHR', 'KMF', 'KPW', 'KRW', 'KWD', 'KYD', 'KZT', 'LAK', 'LBP', 'LKR', 'LRD', 'LSL', 'LTL', 'LVL', 'LYD', 'MAD', 'MDL', 'MGA', 'MKD', 'MMK', 'MNT', 'MOP', 'MRU', 'MUR', 'MVR', 'MWK', 'MXN', 'MYR', 'MZN', 'NAD', 'NGN', 'NIO', 'NOK', 'NPR', 'NZD', 'OMR', 'PAB', 'PEN', 'PGK', 'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RSD', 'RUB', 'RWF', 'SAR', 'SBD', 'SCR', 'SDG', 'SEK', 'SGD', 'SHP', 'SLE', 'SLL', 'SOS', 'SRD', 'STD', 'STN', 'SVC', 'SYP', 'SZL', 'THB', 'TJS', 'TMT', 'TND', 'TOP', 'TRY', 'TTD', 'TWD', 'TZS', 'UAH', 'UGX', 'USD', 'UYU', 'UZS', 'VES', 'VND', 'VUV', 'WST', 'XAF', 'XAG', 'XAU', 'XCD', 'XCG', 'XDR', 'XOF', 'XPF', 'YER', 'ZAR', 'ZMK', 'ZMW', 'ZWL'];
    }

    public function getRequestLimit(): int
    {
        return 100; // Лимит на Бесплатном плане
    }

    public function getRequestLimitPeriod(): int
    {
        return 86400 * 31;
    }

    public function getRequestDelay(): int
    {
        return 2;
    }
}
