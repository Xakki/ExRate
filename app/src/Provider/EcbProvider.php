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
 * @see https://www.ecb.europa.eu/stats/policy_and_exchange_rates/euro_reference_exchange_rates/html/index.en.html
 */
final readonly class EcbProvider implements ProviderInterface
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
        return 'provider.ecb';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::ECB;
    }

    public function getBaseCurrency(): string
    {
        return 'EUR';
    }

    public function getHomePage(): string
    {
        return 'https://www.ecb.europa.eu';
    }

    public function getDescription(): string
    {
        return 'The European Central Bank (ECB) is the central bank of the European Union countries which have adopted the euro.';
    }

    public function getDaysLag(): int
    {
        return 0;
    }

    public function getRatesByDate(\DateTimeImmutable $date): GetRatesResult
    {
        $url = $this->getUrlForDate($date);

        $xml = $this->xmlRequest($url);

        $xml->registerXPathNamespace('gesmes', 'http://www.gesmes.org/xml/2002-08-01');
        $xml->registerXPathNamespace('ns', 'http://www.ecb.int/vocabulary/2002-08-01/eurofxref');

        $targetDateStr = $date->format('Y-m-d');
        $cube = $xml->xpath("//ns:Cube[@time='{$targetDateStr}']");

        if (!$cube) {
            // If the specific date is not found, it might be a weekend or holiday.
            // In a real scenario, we might want to find the closest previous date.
            // For ECB historical XMLs, it contains multiple dates.
            // Let's try to find the latest available date that is <= requested date.
            $cubes = $xml->xpath('//ns:Cube[@time]');
            $bestCube = null;
            $bestDateStr = null;

            if (is_array($cubes)) {
                foreach ($cubes as $c) {
                    $cDateStr = (string) $c['time'];
                    if ($cDateStr <= $targetDateStr) {
                        if (null === $bestDateStr || $cDateStr > $bestDateStr) {
                            $bestDateStr = $cDateStr;
                            $bestCube = $c;
                        }
                    }
                }
            }

            if (!$bestCube) {
                throw new FailedProviderException(sprintf('No ECB rates found for date %s', $targetDateStr));
            }
            $cube = [$bestCube];
        }

        $responseDate = new \DateTimeImmutable((string) $cube[0]['time']);
        $rates = [];

        foreach ($cube[0]->Cube as $rate) {
            $code = (string) $rate['currency'];
            $value = (string) $rate['rate'];
            $rates[$code] = BcMath::round($value, $this->currencyPrecision);
        }

        return new GetRatesResult($this->getId(), $this->getBaseCurrency(), $responseDate, $rates);
    }

    public function isActive(): bool
    {
        return true;
    }

    public function getAvailableCurrencies(): array
    {
        return ['AUD', 'BRL', 'CAD', 'CHF', 'CNY', 'CZK', 'DKK', 'GBP', 'HKD', 'HUF', 'IDR', 'ILS', 'INR', 'ISK', 'JPY', 'KRW', 'MXN', 'MYR', 'NOK', 'NZD', 'PHP', 'PLN', 'RON', 'SEK', 'SGD', 'THB', 'TRY', 'USD', 'ZAR'];
    }

    private function getUrlForDate(\DateTimeImmutable $date): string
    {
        $now = new \DateTimeImmutable();
        $diff = $now->diff($date)->days;

        $baseUrl = rtrim($this->url, '/');

        if ($diff <= 1) {
            return $baseUrl.'/eurofxref-daily.xml';
        }

        if ($diff <= 90) {
            return $baseUrl.'/eurofxref-hist-90d.xml';
        }

        return $baseUrl.'/eurofxref-hist.xml';
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
