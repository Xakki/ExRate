<?php

declare(strict_types=1);

namespace App\Provider;

use App\DTO\GetRatesResult;
use App\DTO\RateData;
use App\Enum\ProviderEnum;
use App\Exception\FailedProviderException;
use App\Service\AbstractProviderRate;
use App\Util\BcMath;
use App\Util\Currencies;
use App\Util\Date;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://www.tcmb.gov.tr/wps/wcm/connect/EN/TCMB+EN
 */
final readonly class CbrtProvider extends AbstractProviderRate
{
    public function __construct(
        protected HttpClientInterface $httpClient,
        protected LoggerInterface $logger,
        protected int $id,
        private string $url,
        private int $currencyPrecision,
    ) {
    }

    public static function getServiceName(): string
    {
        return 'provider.cbrt';
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::CBRT;
    }

    public function getBaseCurrency(): string
    {
        return Currencies::TRY;
    }

    public function getHomePage(): string
    {
        return 'https://www.tcmb.gov.tr';
    }

    public function getDescription(): string
    {
        return 'Central Bank of the Republic of Turkey';
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
                return new GetRatesResult($this, $this->getBaseCurrency(), $date, []);
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
                $rates[$code] = new RateData(BcMath::div($value, $unit, $this->currencyPrecision));
            }
        }

        return new GetRatesResult($this, $this->getBaseCurrency(), $responseDate, $rates);
    }

    public function getAvailableCurrencies(): array
    {
        return [Currencies::AED, Currencies::AUD, Currencies::AZN, Currencies::CAD, Currencies::CHF, Currencies::CNY, Currencies::DKK, Currencies::EUR, Currencies::GBP, Currencies::JPY, Currencies::KRW, Currencies::KWD, Currencies::KZT, Currencies::NOK, Currencies::PKR, Currencies::QAR, Currencies::RON, Currencies::RUB, Currencies::SAR, Currencies::SEK, Currencies::USD];
    }

    private function buildUrl(\DateTimeImmutable $date): string
    {
        $baseUrl = rtrim($this->url, '/').'/';

        if (0 === Date::getDayDiff($date)) {
            return $baseUrl.'today.xml';
        }

        $yearMonth = $date->format('Ym');
        $dayMonthYear = $date->format('dmY');

        return $baseUrl.$yearMonth.'/'.$dayMonthYear.'.xml';
    }

    /**
     * @return GetRatesResult[]
     */
    public function getRatesByRangeDate(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        throw new \App\Exception\NotAvailableMethod();
    }
}
