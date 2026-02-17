<?php

declare(strict_types=1);

namespace App\Provider;

use App\DTO\GetRatesResult;
use App\DTO\RateData;
use App\Enum\ProviderEnum;
use App\Service\AbstractProviderRate;
use App\Util\BcMath;
use App\Util\Currencies;
use App\Util\Date;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://www.ecb.europa.eu/stats/policy_and_exchange_rates/euro_reference_exchange_rates/html/index.en.html
 */
final readonly class EcbProvider extends AbstractProviderRate
{
    public function __construct(
        protected HttpClientInterface $httpClient,
        protected LoggerInterface $logger,
        protected int $id,
        private string $url,
        private int $currencyPrecision,
        private int $periodDays = 60,
    ) {
    }

    public function getPeriodDays(): int
    {
        return $this->periodDays;
    }

    public static function getServiceName(): string
    {
        return 'provider.ecb';
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::ECB;
    }

    public function getBaseCurrency(): string
    {
        return Currencies::EUR;
    }

    public function getHomePage(): string
    {
        return 'https://www.ecb.europa.eu';
    }

    public function getDescription(): string
    {
        return 'The European Central Bank';
    }

    public function getAvailableCurrencies(): array
    {
        return [Currencies::AUD, Currencies::BRL, Currencies::CAD, Currencies::CHF, Currencies::CNY, Currencies::CZK, Currencies::DKK, Currencies::GBP, Currencies::HKD, Currencies::HUF, Currencies::IDR, Currencies::ILS, Currencies::INR, Currencies::ISK, Currencies::JPY, Currencies::KRW, Currencies::MXN, Currencies::MYR, Currencies::NOK, Currencies::NZD, Currencies::PHP, Currencies::PLN, Currencies::RON, Currencies::SEK, Currencies::SGD, Currencies::THB, Currencies::TRY, Currencies::USD, Currencies::ZAR, Currencies::BGN, Currencies::HRK, Currencies::RUB];
    }

    #[\Deprecated]
    public function getRatesByDate(\DateTimeImmutable $date): GetRatesResult
    {
        throw new \App\Exception\NotAvailableMethod();
    }

    /**
     * @return GetRatesResult[]
     */
    public function getRatesByRangeDate(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $url = $this->getUrlForDate($start);
        // If the range spans across different files, we might need to fetch more.
        // But hist-90d.xml contains 90 days, and eurofxref-hist.xml contains everything.
        // Since we take 60 days, hist-90d.xml or hist.xml should be enough.

        $xml = $this->xmlRequest($url);
        $xml->registerXPathNamespace('gesmes', 'http://www.gesmes.org/xml/2002-08-01');
        $xml->registerXPathNamespace('ns', 'http://www.ecb.int/vocabulary/2002-08-01/eurofxref');

        $cubes = $xml->xpath('//ns:Cube[@time]');
        $results = [];

        if (is_array($cubes)) {
            foreach ($cubes as $cube) {
                // Format 2026-02-12
                $date = Date::createFromFormat(Date::FORMAT, (string) $cube['time']);
                $rates = [];
                foreach ($cube->Cube as $rate) {
                    $code = (string) $rate['currency'];
                    $value = (string) $rate['rate'];
                    $rates[$code] = new RateData(BcMath::div(1, $value, $this->currencyPrecision));
                }
                $results[] = new GetRatesResult($this, $this->getBaseCurrency(), $date, $rates);
            }
        }

        return $results;
    }

    private function getUrlForDate(\DateTimeImmutable $date): string
    {
        $diff = Date::getDayDiff($date);

        $baseUrl = rtrim($this->url, '/');

        if ($diff <= 1) {
            return $baseUrl.'/eurofxref-daily.xml';
        }

        if ($diff <= 90) {
            return $baseUrl.'/eurofxref-hist-90d.xml';
        }

        return $baseUrl.'/eurofxref-hist.xml';
    }
}
