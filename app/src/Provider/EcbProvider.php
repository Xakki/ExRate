<?php

declare(strict_types=1);

namespace App\Provider;

use App\Contract\ProviderInterface;
use App\DTO\GetRatesResult;
use App\Enum\ProviderEnum;
use App\Util\BcMath;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://www.ecb.europa.eu/stats/policy_and_exchange_rates/euro_reference_exchange_rates/html/index.en.html
 */
final readonly class EcbProvider implements ProviderInterface
{
    public const string DAILY_URL = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';
    public const string HISTORICAL_URL_90_DAYS = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-hist-90d.xml';
    public const string HISTORICAL_URL_ALL = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-hist.xml';

    public function __construct(
        private HttpClientInterface $httpClient,
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

    public function getRates(\DateTimeImmutable $date): GetRatesResult
    {
        $url = $this->getUrlForDate($date);

        $response = $this->httpClient->request('GET', $url);
        $content = $response->getContent();

        $xml = simplexml_load_string($content);

        if (false === $xml) {
            throw new \RuntimeException('Failed to parse ECB XML response');
        }

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
                throw new \RuntimeException(sprintf('No ECB rates found for date %s', $targetDateStr));
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

        if ($diff <= 1) {
            return self::DAILY_URL;
        }

        if ($diff <= 90) {
            return self::HISTORICAL_URL_90_DAYS;
        }

        return self::HISTORICAL_URL_ALL;
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
