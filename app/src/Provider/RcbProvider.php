<?php

declare(strict_types=1);

namespace App\Provider;

use App\Contract\ProviderInterface;
use App\DTO\GetRatesResult;
use App\Enum\ProviderEnum;
use App\Util\BcMath;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://cbu.uz/en/arkhiv-kursov-valyut/
 */
final readonly class RcbProvider implements ProviderInterface
{
    public const string BASE_URL = 'https://cbu.uz/common/json';

    public function __construct(
        private HttpClientInterface $httpClient,
        private int $id,
        private int $currencyPrecision,
    ) {
    }

    public static function getServiceName(): string
    {
        return 'provider.rcb';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::RCB;
    }

    public function getBaseCurrency(): string
    {
        return 'UZS';
    }

    public function getHomePage(): string
    {
        return 'https://cbu.uz';
    }

    public function getDescription(): string
    {
        return 'Central Bank of the Republic of Uzbekistan';
    }

    public function getRates(\DateTimeImmutable $date): GetRatesResult
    {
        $url = self::BASE_URL.'/?date='.$date->format('d.m.Y');

        $response = $this->httpClient->request('GET', $url);
        $content = $response->getContent();
        $data = json_decode($content, true);

        if (!is_array($data)) {
            throw new \RuntimeException('Failed to parse RCB JSON response');
        }

        $rates = [];
        $responseDate = $date;

        foreach ($data as $item) {
            $code = $item['Ccy'];
            $rateStr = $item['Rate'];
            $nominal = $item['Nominal'];

            $rates[$code] = BcMath::div($rateStr, $nominal, $this->currencyPrecision);

            if (isset($item['Date'])) {
                $responseDate = \DateTimeImmutable::createFromFormat('d.m.Y', $item['Date']) ?: $responseDate;
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
        return ['AED', 'AFN', 'AMD', 'ARS', 'AUD', 'AZN', 'BDT', 'BGN', 'BHD', 'BND', 'BRL', 'BYN', 'CAD', 'CHF', 'CNY', 'CUP', 'CZK', 'DKK', 'DZD', 'EGP', 'EUR', 'GBP', 'GEL', 'HKD', 'HUF', 'IDR', 'ILS', 'INR', 'IQD', 'IRR', 'ISK', 'JOD', 'JPY', 'KHR', 'KGS', 'KRW', 'KWD', 'KZT', 'LAK', 'LBP', 'LYD', 'MAD', 'MDL', 'MMK', 'MNT', 'MXN', 'MYR', 'NOK', 'NZD', 'OMR', 'PHP', 'PKR', 'PLN', 'QAR', 'RON', 'RSD', 'RUB', 'SAR', 'SDG', 'SEK', 'SGD', 'SYP', 'THB', 'TJS', 'TMT', 'TND', 'TRY', 'UAH', 'USD', 'UYU', 'VES', 'VND', 'XDR', 'YER', 'ZAR'];
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
