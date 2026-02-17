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
 * @see https://www.bnr.ro/Exchange-rates-1224.aspx
 */
final readonly class NbrProvider extends AbstractProviderRate
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
        return 'provider.nbr';
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::NBR;
    }

    public function getBaseCurrency(): string
    {
        return Currencies::RON;
    }

    public function getHomePage(): string
    {
        return 'https://bnr.ro';
    }

    public function getDescription(): string
    {
        return 'National Bank of Romania';
    }

    #[\Deprecated]
    public function getRatesByDate(\DateTimeImmutable $date): GetRatesResult
    {
        throw new \App\Exception\NotAvailableMethod();
    }

    public function getAvailableCurrencies(): array
    {
        return [Currencies::AED, Currencies::AUD, Currencies::BRL, Currencies::CAD, Currencies::CHF, Currencies::CNY, Currencies::CZK, Currencies::DKK, Currencies::EGP, Currencies::EUR, Currencies::GBP, Currencies::HKD, Currencies::HUF, Currencies::IDR, Currencies::ILS, Currencies::INR, Currencies::ISK, Currencies::JPY, Currencies::KRW, Currencies::MDL, Currencies::MXN, Currencies::MYR, Currencies::NOK, Currencies::NZD, Currencies::PHP, Currencies::PLN, Currencies::RSD, Currencies::RUB, Currencies::SEK, Currencies::SGD, Currencies::THB, Currencies::TRY, Currencies::UAH, Currencies::USD, Currencies::XAU, Currencies::XDR, Currencies::ZAR];
    }

    /**
     * @return GetRatesResult[]
     */
    public function getRatesByRangeDate(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $years = [];
        $currentYear = (int) $start->format('Y');
        $endYear = (int) $end->format('Y');
        for ($y = $currentYear; $y <= $endYear; ++$y) {
            $years[] = $y;
        }

        $isToday = 0 === Date::getDayDiff($end);
        $baseUrl = rtrim($this->url, '/');
        $start = $start->setTime(0, 0);
        $end = $end->setTime(23, 59, 59);

        $results = [];
        foreach ($years as $year) {
            $url = ($isToday && $year === (int) date('Y')) ? $baseUrl.'/nbrfxrates.xml' : $baseUrl."/files/xml/years/nbrfxrates{$year}.xml";
            try {
                $xml = $this->xmlRequest($url);
            } catch (\Throwable) {
                continue;
            }

            $xml->registerXPathNamespace('ns', 'http://www.bnr.ro/xsd');
            $cubes = $xml->xpath('//ns:Cube[@date]');

            if (is_array($cubes)) {
                foreach ($cubes as $cube) {
                    $cDateStr = (string) $cube['date'];
                    $cDate = new \DateTimeImmutable($cDateStr);

                    if (Date::getDayDiff($cDate, $start) <= 0 && Date::getDayDiff($cDate, $end) >= 0) {
                        $rates = [];
                        foreach ($cube->Rate as $rateNode) {
                            $code = (string) $rateNode['currency'];
                            $value = (string) $rateNode;
                            $multiplier = isset($rateNode['multiplier']) ? (string) $rateNode['multiplier'] : '1';

                            $rates[$code] = new RateData(BcMath::div($value, $multiplier, $this->currencyPrecision));
                        }
                        $results[$cDateStr] = new GetRatesResult($this, $this->getBaseCurrency(), $cDate, $rates);
                    }
                }
            }
        }

        ksort($results);

        return array_values($results);
    }
}
