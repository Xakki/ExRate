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
 * @see https://1forge.com/forex-data-api/api-documentation
 */
final readonly class ForgeProvider implements ProviderInterface
{
    public const string URL = 'https://api.1forge.com/quotes?pairs=USD/EUR,USD/GBP,USD/JPY,USD/RUB';

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
        return 'provider.forge';
    }

    public function isActive(): bool
    {
        return !empty($this->apiKey);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::FORGE;
    }

    public function getBaseCurrency(): string
    {
        return 'USD';
    }

    public function getHomePage(): string
    {
        return 'https://1forge.com';
    }

    public function getDescription(): string
    {
        return 'Real-time Forex and Crypto API';
    }

    public function getRates(\DateTimeImmutable $date): GetRatesResult
    {
        // Forge API requires specific pairs. This is just an example implementation.
        $response = $this->httpClient->request('GET', self::URL, [
            'query' => [
                'api_key' => $this->apiKey,
            ],
        ]);

        $content = $response->getContent();
        $data = json_decode($content, true);

        if (!is_array($data)) {
            throw new \RuntimeException('Failed to parse Forge response');
        }

        $rates = [];
        $responseDate = $date;

        foreach ($data as $item) {
            $pair = $item['s']; // e.g. USDEUR
            $code = substr($pair, 3);
            $rates[$code] = BcMath::round((string) $item['p'], $this->currencyPrecision);
            if (isset($item['t'])) {
                $responseDate = (new \DateTimeImmutable(timezone: new \DateTimeZone('UTC')))->setTimestamp($item['t']);
            }
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
