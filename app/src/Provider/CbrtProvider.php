<?php

declare(strict_types=1);

namespace App\Provider;

use App\Contract\ProviderInterface;
use App\DTO\GetRatesResult;
use App\Enum\ProviderEnum;
use App\Exception\FailedProviderException;
use App\Util\BcMath;
use App\Util\RequestTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://www.tcmb.gov.tr/wps/wcm/connect/EN/TCMB+EN
 */
final readonly class CbrtProvider implements ProviderInterface
{
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
        return 'provider.cbrt';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::CBRT;
    }

    public function getBaseCurrency(): string
    {
        return 'TRY';
    }

    public function getHomePage(): string
    {
        return 'https://www.tcmb.gov.tr';
    }

    public function getDescription(): string
    {
        return 'Central Bank of the Republic of Turkey';
    }

    public function getDaysLag(): int
    {
        return 0;
    }

    public function getRatesByDate(\DateTimeImmutable $date): GetRatesResult
    {
        return $this->getRatesTry($date);
    }

    public function getRatesTry(\DateTimeImmutable $date, int $try = 1): GetRatesResult
    {
        $url = $this->buildUrl($date);

        $response = $this->request($url);

        // CBRT returns 404 if no rates for the day (e.g. weekend)
        if (404 === $response->getStatusCode()) {
            if ($try > 10) {
                return new GetRatesResult($this->getId(), $this->getBaseCurrency(), $date, []);
            }
            // Try previous day
            $prevDate = $date->modify('-1 day');

            // To avoid infinite recursion or too many attempts, we should probably just return what we find or throw.
            // But for now, let's just try once more or use the provider logic.
            return $this->getRatesTry($prevDate, ++$try);
        }

        $xml = simplexml_load_string($response->getContent(false));

        if (false === $xml) {
            throw new FailedProviderException('Failed to parse CBRT XML response');
        }

        $responseDate = new \DateTimeImmutable((string) $xml['Date']);
        $rates = [];

        foreach ($xml->Currency as $currency) {
            $code = (string) $currency['CurrencyCode'];
            $value = (string) $currency->ForexSelling;
            $unit = (string) ($currency->Unit ?? '1');

            if ($value) {
                $rates[$code] = BcMath::div($value, $unit, $this->currencyPrecision);
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
        return ['AED', 'AUD', 'AZN', 'CAD', 'CHF', 'CNY', 'DKK', 'EUR', 'GBP', 'JPY', 'KRW', 'KWD', 'KZT', 'NOK', 'PKR', 'QAR', 'RON', 'RUB', 'SAR', 'SEK', 'USD'];
    }

    private function buildUrl(\DateTimeImmutable $date): string
    {
        $now = new \DateTimeImmutable();
        $baseUrl = rtrim($this->url, '/').'/';

        if ($date->format('Y-m-d') === $now->format('Y-m-d')) {
            return $baseUrl.'today.xml';
        }

        $yearMonth = $date->format('Ym');
        $dayMonthYear = $date->format('dmY');

        return $baseUrl.$yearMonth.'/'.$dayMonthYear.'.xml';
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
