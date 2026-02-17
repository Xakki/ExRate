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
 * @see https://www.bnr.ro/Exchange-rates-1224.aspx
 */
final readonly class NbrProvider implements ProviderInterface
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
        return 'provider.nbr';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::NBR;
    }

    public function getBaseCurrency(): string
    {
        return 'RON';
    }

    public function getHomePage(): string
    {
        return 'https://bnr.ro';
    }

    public function getDescription(): string
    {
        return 'National Bank of Romania';
    }

    public function getDaysLag(): int
    {
        return 0;
    }

    public function getRatesByDate(\DateTimeImmutable $date): GetRatesResult
    {
        $year = $date->format('Y');
        $isToday = $date->format('Y-m-d') === (new \DateTimeImmutable())->format('Y-m-d');

        $baseUrl = rtrim($this->url, '/');
        $url = $isToday ? $baseUrl.'/nbrfxrates.xml' : $baseUrl."/files/xml/years/nbrfxrates{$year}.xml";

        $xml = $this->xmlRequest($url);

        $xml->registerXPathNamespace('ns', 'http://www.bnr.ro/xsd');

        $targetDateStr = $date->format('Y-m-d');
        // NBR historical XML contains multiple cubes for each date
        $cube = $xml->xpath("//ns:Cube[@date='{$targetDateStr}']");

        if (!$cube) {
            // Try to find the latest available date <= requested date
            $cubes = $xml->xpath('//ns:Cube[@date]');
            $bestCube = null;
            $bestDateStr = null;

            if (is_array($cubes)) {
                foreach ($cubes as $c) {
                    $cDateStr = (string) $c['date'];
                    if ($cDateStr <= $targetDateStr) {
                        if (null === $bestDateStr || $cDateStr > $bestDateStr) {
                            $bestDateStr = $cDateStr;
                            $bestCube = $c;
                        }
                    }
                }
            }

            if (!$bestCube && $isToday) {
                // For latest, it might not have the date attribute in a Cube tag if it's the main URL
                $cube = $xml->xpath('//ns:Cube');
            } elseif ($bestCube) {
                $cube = [$bestCube];
            } else {
                return new GetRatesResult($this->getId(), $this->getBaseCurrency(), $date, []);
            }
        }

        if (!$cube || !isset($cube[0])) {
            throw new FailedProviderException(sprintf('No NBR rates found for date %s', $targetDateStr));
        }

        $responseDateStr = (string) ($cube[0]['date'] ?? $xml->xpath('//ns:PublishingDate')[0] ?? $targetDateStr);
        $responseDate = new \DateTimeImmutable($responseDateStr);

        $rates = [];
        foreach ($cube[0]->Rate as $rateNode) {
            $code = (string) $rateNode['currency'];
            $value = (string) $rateNode;
            $multiplier = isset($rateNode['multiplier']) ? (string) $rateNode['multiplier'] : '1';

            $rates[$code] = BcMath::div($value, $multiplier, $this->currencyPrecision);
        }

        return new GetRatesResult($this->getId(), $this->getBaseCurrency(), $responseDate, $rates);
    }

    public function isActive(): bool
    {
        return true;
    }

    public function getAvailableCurrencies(): array
    {
        return ['AED', 'AUD', 'BRL', 'CAD', 'CHF', 'CNY', 'CZK', 'DKK', 'EGP', 'EUR', 'GBP', 'HKD', 'HUF', 'IDR', 'ILS', 'INR', 'ISK', 'JPY', 'KRW', 'MDL', 'MXN', 'MYR', 'NOK', 'NZD', 'PHP', 'PLN', 'RSD', 'RUB', 'SEK', 'SGD', 'THB', 'TRY', 'UAH', 'USD', 'XAU', 'XDR', 'ZAR'];
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
