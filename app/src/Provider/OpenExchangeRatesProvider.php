<?php

declare(strict_types=1);

namespace App\Provider;

use App\DTO\GetRatesResult;
use App\DTO\RateData;
use App\Enum\ProviderEnum;
use App\Exception\DisabledProviderException;
use App\Exception\FailedProviderException;
use App\Exception\NotAvailableMethod;
use App\Service\AbstractProviderRate;
use App\Util\BcMath;
use App\Util\CryptoCurrencies;
use App\Util\Currencies;
use App\Util\Date;
use App\Util\UrlTemplateTrait;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://docs.openexchangerates.org/
 */
final readonly class OpenExchangeRatesProvider extends AbstractProviderRate
{
    use UrlTemplateTrait;

    public function __construct(
        protected HttpClientInterface $httpClient,
        protected LoggerInterface $logger,
        protected int $id,
        private string $url,
        private int $currencyPrecision,
        private string $apiKey,
    ) {
        if (empty($this->apiKey)) {
            throw new DisabledProviderException('Provider disabled: Need API key');
        }
    }

    public static function getServiceName(): string
    {
        return 'provider.open_exchange_rates';
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::OPEN_EXCHANGE_RATES;
    }

    public function getBaseCurrency(): string
    {
        return Currencies::USD;
    }

    public function getHomePage(): string
    {
        return 'https://openexchangerates.org';
    }

    public function getDescription(): string
    {
        return 'Open Exchange Rates API';
    }

    public function getRatesToday(\DateTimeImmutable $date): GetRatesResult
    {
        throw new NotAvailableMethod();
        // $url = $baseUrl.'/latest.json?show_alternative=1'
    }

    public function getRatesByDate(\DateTimeImmutable $date): GetRatesResult
    {
        $url = $this->prepareUrl($this->url, $date, apiKey: $this->apiKey);
        $data = $this->jsonRequest($url);

        if (isset($data['error'])) {
            throw new FailedProviderException($data['description'] ?? 'Failed to parse Open Exchange Rates response');
        }

        $responseDate = Date::createFromFormat('U', (string) $data['timestamp']);
        $rates = [];

        foreach ($data['rates'] as $code => $value) {
            $rates[$code] = new RateData(BcMath::div(1, (string) $value, $this->currencyPrecision));
        }

        return new GetRatesResult($this, $this->getBaseCurrency(), $responseDate, $rates);
    }

    public function getAvailableCurrencies(): array
    {
        return [Currencies::AED, Currencies::AFN, Currencies::ALL, Currencies::AMD, Currencies::ANG, Currencies::AOA, Currencies::ARS, Currencies::AUD, Currencies::AWG, Currencies::AZN, Currencies::BAM, Currencies::BBD, Currencies::BDT, Currencies::BGN, Currencies::BHD, Currencies::BIF, Currencies::BMD, Currencies::BND, Currencies::BOB, Currencies::BRL, Currencies::BSD, CryptoCurrencies::BTC, Currencies::BTN, CryptoCurrencies::BTS, Currencies::BWP, Currencies::BYN, Currencies::BZD, Currencies::CAD, Currencies::CDF, Currencies::CHF, Currencies::CLF, Currencies::CLP, Currencies::CNH, Currencies::CNY, Currencies::COP, Currencies::CRC, Currencies::CUC, Currencies::CUP, Currencies::CVE, Currencies::CZK, CryptoCurrencies::DASH, Currencies::DJF, Currencies::DKK, CryptoCurrencies::DOGE, Currencies::DOP, Currencies::DZD, Currencies::EGP, Currencies::ERN, Currencies::ETB, CryptoCurrencies::ETH, Currencies::EUR, Currencies::FJD, Currencies::FKP, Currencies::GBP, Currencies::GEL, Currencies::GGP, Currencies::GHS, Currencies::GIP, Currencies::GMD, Currencies::GNF, Currencies::GTQ, Currencies::GYD, Currencies::HKD, Currencies::HNL, Currencies::HRK, Currencies::HTG, Currencies::HUF, Currencies::IDR, Currencies::ILS, Currencies::IMP, Currencies::INR, Currencies::IQD, Currencies::IRR, Currencies::ISK, Currencies::JEP, Currencies::JMD, Currencies::JOD, Currencies::JPY, Currencies::KES, Currencies::KGS, Currencies::KHR, Currencies::KMF, Currencies::KPW, Currencies::KRW, Currencies::KWD, Currencies::KYD, Currencies::KZT, Currencies::LAK, Currencies::LBP, Currencies::LD, Currencies::LKR, Currencies::LRD, Currencies::LSL, CryptoCurrencies::LTC, Currencies::LYD, Currencies::MAD, Currencies::MDL, Currencies::MGA, Currencies::MKD, Currencies::MMK, Currencies::MNT, Currencies::MOP, Currencies::MRU, Currencies::MUR, Currencies::MVR, Currencies::MWK, Currencies::MXN, Currencies::MYR, Currencies::MZN, Currencies::NAD, Currencies::NGN, Currencies::NIO, Currencies::NOK, Currencies::NPR, CryptoCurrencies::NXT, Currencies::NZD, Currencies::OMR, Currencies::PAB, Currencies::PEN, Currencies::PGK, Currencies::PHP, Currencies::PKR, Currencies::PLN, Currencies::PYG, Currencies::QAR, Currencies::RON, Currencies::RSD, Currencies::RUB, Currencies::RWF, Currencies::SAR, Currencies::SBD, Currencies::SCR, Currencies::SDG, Currencies::SEK, Currencies::SGD, Currencies::SHP, Currencies::SLE, Currencies::SLL, Currencies::SOS, Currencies::SRD, Currencies::SSP, Currencies::STD, Currencies::STN, CryptoCurrencies::STR, Currencies::SVC, Currencies::SYP, Currencies::SZL, Currencies::THB, Currencies::TJS, Currencies::TMT, Currencies::TND, Currencies::TOP, Currencies::TRY, Currencies::TTD, Currencies::TWD, Currencies::TZS, Currencies::UAH, Currencies::UGX, Currencies::USD, Currencies::UYU, Currencies::UZS, Currencies::VEF_BLKMKT, Currencies::VEF_DICOM, Currencies::VEF_DIPRO, Currencies::VES, Currencies::VND, Currencies::VUV, Currencies::WST, Currencies::XAF, Currencies::XAG, Currencies::XAU, Currencies::XCD, Currencies::XCG, Currencies::XDR, CryptoCurrencies::XMR, Currencies::XOF, Currencies::XPD, Currencies::XPF, Currencies::XPT, CryptoCurrencies::XRP, Currencies::YER, Currencies::ZAR, Currencies::ZMW, Currencies::ZWG, Currencies::ZWL];
    }

    public function getRequestLimit(): int
    {
        return 30;
    }

    public function getRequestLimitPeriod(): int
    {
        return 86400;
    }

    /**
     * @return GetRatesResult[]
     */
    public function getRatesByRangeDate(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        throw new NotAvailableMethod();
    }
}
