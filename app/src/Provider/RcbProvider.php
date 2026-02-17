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
use App\Util\UrlTemplateTrait;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://cbu.uz/en/arkhiv-kursov-valyut/
 */
final readonly class RcbProvider extends AbstractProviderRate
{
    use UrlTemplateTrait;

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
        return 'provider.rcb';
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::RCB;
    }

    public function getBaseCurrency(): string
    {
        return Currencies::UZS;
    }

    public function getHomePage(): string
    {
        return 'https://cbu.uz';
    }

    public function getDescription(): string
    {
        return 'Central Bank of the Republic of Uzbekistan';
    }

    public function getRatesByDate(\DateTimeImmutable $date): GetRatesResult
    {
        $url = $this->prepareUrl($this->url, $date, $this->getBaseCurrency());

        $data = $this->jsonRequest($url);

        $rates = [];
        $responseDate = $date;

        foreach ($data as $item) {
            $code = $item['Ccy'];
            $rateStr = $item['Rate'];
            $nominal = $item['Nominal'];

            $rates[$code] = new RateData(BcMath::div($rateStr, $nominal, $this->currencyPrecision));

            if (isset($item['Date'])) {
                try {
                    $responseDate = Date::createFromFormat('d.m.Y', $item['Date']);
                } catch (\App\Exception\BadDateException) {
                    // TODO: log notice
                    // keep previous responseDate
                }
            }
        }

        return new GetRatesResult($this, $this->getBaseCurrency(), $responseDate, $rates);
    }

    public function getAvailableCurrencies(): array
    {
        return [Currencies::AED, Currencies::AFN, Currencies::AMD, Currencies::ARS, Currencies::AUD, Currencies::AZN, Currencies::BDT, Currencies::BGN, Currencies::BHD, Currencies::BND, Currencies::BRL, Currencies::BYN, Currencies::CAD, Currencies::CHF, Currencies::CNY, Currencies::CUP, Currencies::CZK, Currencies::DKK, Currencies::DZD, Currencies::EGP, Currencies::EUR, Currencies::GBP, Currencies::GEL, Currencies::HKD, Currencies::HUF, Currencies::IDR, Currencies::ILS, Currencies::INR, Currencies::IQD, Currencies::IRR, Currencies::ISK, Currencies::JOD, Currencies::JPY, Currencies::KHR, Currencies::KGS, Currencies::KRW, Currencies::KWD, Currencies::KZT, Currencies::LAK, Currencies::LBP, Currencies::LYD, Currencies::MAD, Currencies::MDL, Currencies::MMK, Currencies::MNT, Currencies::MXN, Currencies::MYR, Currencies::NOK, Currencies::NZD, Currencies::OMR, Currencies::PHP, Currencies::PKR, Currencies::PLN, Currencies::QAR, Currencies::RON, Currencies::RSD, Currencies::RUB, Currencies::SAR, Currencies::SDG, Currencies::SEK, Currencies::SGD, Currencies::SYP, Currencies::THB, Currencies::TJS, Currencies::TMT, Currencies::TND, Currencies::TRY, Currencies::UAH, Currencies::USD, Currencies::UYU, Currencies::VES, Currencies::VND, Currencies::XDR, Currencies::YER, Currencies::ZAR];
    }

    /**
     * @return GetRatesResult[]
     */
    public function getRatesByRangeDate(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        throw new \App\Exception\NotAvailableMethod();
    }
}
