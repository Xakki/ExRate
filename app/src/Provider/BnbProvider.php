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
 * @see https://www.bnb.bg
 */
final readonly class BnbProvider extends AbstractProviderRate
{
    public function __construct(
        protected HttpClientInterface $httpClient,
        protected LoggerInterface $logger,
        protected int $id,
        private string $url,
        private int $currencyPrecision = 8,
    ) {
    }

    public static function getServiceName(): string
    {
        return 'provider.bnb';
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::BNB;
    }

    public function getBaseCurrency(): string
    {
        return Currencies::EUR;
    }

    public function getHomePage(): string
    {
        return 'https://www.bnb.bg';
    }

    public function getDescription(): string
    {
        return 'Bulgarian National Bank';
    }

    public function getRatesByDate(\DateTimeImmutable $date): GetRatesResult
    {
        $day = $date->format('d');
        $month = $date->format('m');
        $year = $date->format('Y');

        $url = $this->url.sprintf('?lang=EN&downloadOper=true&group1=first&firstDays=%s&firstMonths=%s&firstYear=%s&search=true&showChart=false&showChartButton=false&type=XML', $day, $month, $year);

        try {
            $xml = $this->xmlRequest($url);
        } catch (FailedProviderException) {
            return new GetRatesResult($this, $this->getBaseCurrency(), $date, []);
        }

        $rates = [];
        $responseDate = null;

        foreach ($xml as $row) {
            if (isset($row->TITLE)) {
                continue;
            }
            $code = (string) $row->CODE;
            $rate = (string) $row->RATE;

            if ($rate && is_numeric($rate)) {
                $rates[$code] = new RateData(BcMath::div(1, $rate, $this->currencyPrecision));
            }

            if (!$responseDate) {
                try {
                    $responseDate = Date::createFromFormat('d.m.Y', (string) $row->CURR_DATE);
                } catch (\App\Exception\BadDateException) {
                    // TODO: log notice
                    $responseDate = $date;
                }
            }
        }

        return new GetRatesResult($this, $this->getBaseCurrency(), $responseDate, $rates);
    }

    public function getAvailableCurrencies(): array
    {
        return [Currencies::XAU, Currencies::LVL, Currencies::LTL, Currencies::CYP, Currencies::EEK, Currencies::MTL, Currencies::ROL, Currencies::SIT, Currencies::SKK, Currencies::TRL, Currencies::AUD, Currencies::BRL, Currencies::CAD, Currencies::CHF, Currencies::CNY, Currencies::CZK, Currencies::DKK, Currencies::GBP, Currencies::HKD, Currencies::HUF, Currencies::IDR, Currencies::ILS, Currencies::INR, Currencies::ISK, Currencies::JPY, Currencies::KRW, Currencies::MXN, Currencies::MYR, Currencies::NOK, Currencies::NZD, Currencies::PHP, Currencies::PLN, Currencies::RON, Currencies::SEK, Currencies::SGD, Currencies::THB, Currencies::TRY, Currencies::USD, Currencies::ZAR];
    }

    /**
     * @return GetRatesResult[]
     */
    public function getRatesByRangeDate(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        throw new \App\Exception\NotAvailableMethod();
    }

    /**
     * @return array<string, string>
     */
    protected function getDnsResolveOptions(int $attempt = 0): array
    {
        if (1 == $attempt) {
            return ['www.bnb.bg' => '91.209.146.25'];
        }

        return parent::getDnsResolveOptions($attempt);
    }
}
