<?php

declare(strict_types=1);

namespace App\Provider;

use App\Contract\ProviderInterface;
use App\DTO\GetRatesResult;
use App\Enum\ProviderEnum;
use App\Exception\DisabledProviderException;
use App\Exception\FailedProviderException;
use App\Util\BcMath;
use App\Util\RequestTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://docs.openexchangerates.org/
 */
final readonly class OpenExchangeRatesProvider implements ProviderInterface
{
    use RequestTrait;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $url,
        private string $appId,
        private int $id,
        private int $currencyPrecision,
    ) {
        if (empty($this->appId)) {
            throw new DisabledProviderException('Provider disabled: Need API key');
        }
    }

    public static function getServiceName(): string
    {
        return 'provider.open_exchange_rates';
    }

    public function isActive(): bool
    {
        return !empty($this->appId);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::OPEN_EXCHANGE_RATES;
    }

    public function getBaseCurrency(): string
    {
        return 'USD';
    }

    public function getHomePage(): string
    {
        return 'https://openexchangerates.org';
    }

    public function getDescription(): string
    {
        return 'The original simple, accurate and transparent exchange rates and currency conversion data API.';
    }

    public function getDaysLag(): int
    {
        return 0;
    }

    public function getRatesByDate(\DateTimeImmutable $date): GetRatesResult
    {
        $isToday = $date->format('Y-m-d') === (new \DateTimeImmutable())->format('Y-m-d');
        $baseUrl = rtrim($this->url, '/');
        $url = $isToday ? $baseUrl.'/latest.json?show_alternative=1' : sprintf($baseUrl.'/historical/%s.json?show_alternative=1', $date->format('Y-m-d'));

        $data = $this->jsonRequest($url, options: [
            'query' => [
                'app_id' => $this->appId,
            ],
        ]);

        if (isset($data['error'])) {
            throw new FailedProviderException($data['description'] ?? 'Failed to parse Open Exchange Rates response');
        }

        $responseDate = (new \DateTimeImmutable(timezone: new \DateTimeZone('UTC')))
            ->setTimestamp($data['timestamp']);
        $rates = [];

        foreach ($data['rates'] as $code => $value) {
            $rates[$code] = BcMath::round((string) $value, $this->currencyPrecision);
        }

        return new GetRatesResult($this->getId(), $this->getBaseCurrency(), $responseDate, $rates);
    }

    public function getAvailableCurrencies(): array
    {
        return ['AED', 'AFN', 'ALL', 'AMD', 'ANG', 'AOA', 'ARS', 'AUD', 'AWG', 'AZN', 'BAM', 'BBD', 'BDT', 'BGN', 'BHD', 'BIF', 'BMD', 'BND', 'BOB', 'BRL', 'BSD', 'BTC', 'BTN', 'BTS', 'BWP', 'BYN', 'BZD', 'CAD', 'CDF', 'CHF', 'CLF', 'CLP', 'CNH', 'CNY', 'COP', 'CRC', 'CUC', 'CUP', 'CVE', 'CZK', 'DASH', 'DJF', 'DKK', 'DOGE', 'DOP', 'DZD', 'EGP', 'ERN', 'ETB', 'ETH', 'EUR', 'FJD', 'FKP', 'GBP', 'GEL', 'GGP', 'GHS', 'GIP', 'GMD', 'GNF', 'GTQ', 'GYD', 'HKD', 'HNL', 'HRK', 'HTG', 'HUF', 'IDR', 'ILS', 'IMP', 'INR', 'IQD', 'IRR', 'ISK', 'JEP', 'JMD', 'JOD', 'JPY', 'KES', 'KGS', 'KHR', 'KMF', 'KPW', 'KRW', 'KWD', 'KYD', 'KZT', 'LAK', 'LBP', 'LD', 'LKR', 'LRD', 'LSL', 'LTC', 'LYD', 'MAD', 'MDL', 'MGA', 'MKD', 'MMK', 'MNT', 'MOP', 'MRU', 'MUR', 'MVR', 'MWK', 'MXN', 'MYR', 'MZN', 'NAD', 'NGN', 'NIO', 'NOK', 'NPR', 'NXT', 'NZD', 'OMR', 'PAB', 'PEN', 'PGK', 'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RSD', 'RUB', 'RWF', 'SAR', 'SBD', 'SCR', 'SDG', 'SEK', 'SGD', 'SHP', 'SLE', 'SLL', 'SOS', 'SRD', 'SSP', 'STD', 'STN', 'STR', 'SVC', 'SYP', 'SZL', 'THB', 'TJS', 'TMT', 'TND', 'TOP', 'TRY', 'TTD', 'TWD', 'TZS', 'UAH', 'UGX', 'USD', 'UYU', 'UZS', 'VEF_BLKMKT', 'VEF_DICOM', 'VEF_DIPRO', 'VES', 'VND', 'VUV', 'WST', 'XAF', 'XAG', 'XAU', 'XCD', 'XCG', 'XDR', 'XMR', 'XOF', 'XPD', 'XPF', 'XPT', 'XRP', 'YER', 'ZAR', 'ZMW', 'ZWG', 'ZWL'];
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

    /**
     * @return GetRatesResult[]
     */
    public function getRatesByRangeDate(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        throw new \App\Exception\NotAvailableMethod();
    }
}
