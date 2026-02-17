<?php

declare(strict_types=1);

namespace App\Provider;

use App\Contract\ProviderInterface;
use App\DTO\GetRatesResult;
use App\Enum\ProviderEnum;
use App\Util\BcMath;
use App\Util\RequestTrait;
use App\Util\UrlTemplateTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://bank.gov.ua/en/markets/exchangerates
 */
final readonly class NbuProvider implements ProviderInterface
{
    use UrlTemplateTrait;
    use RequestTrait;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $url,
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

    public function getDaysLag(): int
    {
        return 0;
    }

    public function getRatesByDate(\DateTimeImmutable $date): GetRatesResult
    {
        $url = $this->prepareUrl($this->url, $date, $this->getBaseCurrency());

        $xml = $this->xmlRequest($url);

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

    /**
     * @return GetRatesResult[]
     */
    public function getRatesByRangeDate(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        throw new \App\Exception\NotAvailableMethod();
    }
}
