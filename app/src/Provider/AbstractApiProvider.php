<?php

declare(strict_types=1);

namespace App\Provider;

use App\DTO\GetRatesResult;
use App\DTO\RateData;
use App\Enum\ProviderEnum;
use App\Exception\DisabledProviderException;
use App\Exception\FailedProviderException;
use App\Service\AbstractProviderRate;
use App\Util\BcMath;
use App\Util\CryptoCurrencies;
use App\Util\Currencies;
use App\Util\UrlTemplateTrait;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://docs.abstractapi.com/
 *
 * @internal this provider returns 422 if the base currency is not supported or API key is invalid for the requested operation
 */
final readonly class AbstractApiProvider extends AbstractProviderRate
{
    use UrlTemplateTrait;

    public function __construct(
        protected HttpClientInterface $httpClient,
        protected LoggerInterface $logger,
        protected int $id,
        protected string $url,
        protected int $currencyPrecision,
        protected string $apiKey,
    ) {
        if (empty($this->apiKey)) {
            throw new DisabledProviderException('Provider disabled: Need API key');
        }
    }

    public static function getServiceName(): string
    {
        return 'provider.abstract_api';
    }

    public function isActive(): bool
    {
        // Превышен лимит
        return false;
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::ABSTRACT_API;
    }

    public function getBaseCurrency(): string
    {
        return Currencies::USD;
    }

    public function getHomePage(): string
    {
        return 'https://abstractapi.com';
    }

    public function getDescription(): string
    {
        return 'Abstract provides powerful APIs to help you enrich any user experience or automate any workflow. Used by 10,000+ developers worldwide.';
    }

    public function getRatesByDate(\DateTimeImmutable $date): GetRatesResult
    {
        $url = $this->prepareUrl($this->url, $date, $this->getBaseCurrency(), apiKey: $this->apiKey);
        $data = $this->jsonRequest($url);

        if (!isset($data['exchange_rates'])) {
            throw new FailedProviderException('Failed to parse AbstractApi response');
        }

        $responseDate = (new \DateTimeImmutable(timezone: new \DateTimeZone('UTC')))->setTimestamp($data['last_updated'] ?? time());
        $rates = [];

        foreach ($data['exchange_rates'] as $code => $value) {
            $rates[$code] = new RateData(BcMath::round((string) $value, $this->currencyPrecision));
        }

        return new GetRatesResult($this, $this->getBaseCurrency(), $responseDate, $rates);
    }

    public function getAvailableCurrencies(): array
    {
        return [Currencies::AED, Currencies::AFN, Currencies::ALL, Currencies::AMD, Currencies::ANG, Currencies::AOA, Currencies::ARS, Currencies::AUD, Currencies::AWG, Currencies::AZN, Currencies::BAM, Currencies::BBD, Currencies::BDT, Currencies::BGN, Currencies::BHD, Currencies::BIF, Currencies::BMD, Currencies::BND, Currencies::BOB, Currencies::BRL, Currencies::BSD, CryptoCurrencies::BTC, Currencies::BTN, Currencies::BWP, Currencies::BYN, Currencies::BZD, Currencies::CAD, Currencies::CDF, Currencies::CHF, Currencies::CLF, Currencies::CLP, Currencies::CNH, Currencies::CNY, Currencies::COP, Currencies::CRC, Currencies::CUC, Currencies::CUP, Currencies::CVE, Currencies::CZK, Currencies::DJF, Currencies::DKK, Currencies::DOP, Currencies::DZD, Currencies::EGP, Currencies::ERN, Currencies::ETB, Currencies::EUR, Currencies::FJD, Currencies::FKP, Currencies::GBP, Currencies::GEL, Currencies::GGP, Currencies::GHS, Currencies::GIP, Currencies::GMD, Currencies::GNF, Currencies::GTQ, Currencies::GYD, Currencies::HKD, Currencies::HNL, Currencies::HRK, Currencies::HTG, Currencies::HUF, Currencies::IDR, Currencies::ILS, Currencies::IMP, Currencies::INR, Currencies::IQD, Currencies::IRR, Currencies::ISK, Currencies::JEP, Currencies::JMD, Currencies::JOD, Currencies::JPY, Currencies::KES, Currencies::KGS, Currencies::KHR, Currencies::KMF, Currencies::KPW, Currencies::KRW, Currencies::KWD, Currencies::KYD, Currencies::KZT, Currencies::LAK, Currencies::LBP, Currencies::LKR, Currencies::LRD, Currencies::LSL, Currencies::LYD, Currencies::MAD, Currencies::MDL, Currencies::MGA, Currencies::MKD, Currencies::MMK, Currencies::MNT, Currencies::MOP, Currencies::MRU, Currencies::MUR, Currencies::MVR, Currencies::MWK, Currencies::MXN, Currencies::MYR, Currencies::MZN, Currencies::NAD, Currencies::NGN, Currencies::NIO, Currencies::NOK, Currencies::NPR, Currencies::NZD, Currencies::OMR, Currencies::PAB, Currencies::PEN, Currencies::PGK, Currencies::PHP, Currencies::PKR, Currencies::PLN, Currencies::PYG, Currencies::QAR, Currencies::RON, Currencies::RSD, Currencies::RUB, Currencies::RWF, Currencies::SAR, Currencies::SBD, Currencies::SCR, Currencies::SDG, Currencies::SEK, Currencies::SGD, Currencies::SHP, Currencies::SLE, Currencies::SLL, Currencies::SOS, Currencies::SRD, Currencies::SSP, Currencies::STD, Currencies::STN, Currencies::SVC, Currencies::SYP, Currencies::SZL, Currencies::THB, Currencies::TJS, Currencies::TMT, Currencies::TND, Currencies::TOP, Currencies::TRY, Currencies::TTD, Currencies::TWD, Currencies::TZS, Currencies::UAH, Currencies::UGX, Currencies::USD, Currencies::UYU, Currencies::UZS, Currencies::VEF, Currencies::VES, Currencies::VND, Currencies::VUV, Currencies::WST, Currencies::XAF, Currencies::XAG, Currencies::XAU, Currencies::XCD, Currencies::XCG, Currencies::XDR, Currencies::XOF, Currencies::XPD, Currencies::XPF, Currencies::XPT, Currencies::YER, Currencies::ZAR, Currencies::ZMW, Currencies::ZWG, Currencies::ZWL];
    }

    public function getRequestLimit(): int
    {
        return 500; // Лимит на Бесплатном плане
    }

    public function getRequestLimitPeriod(): int
    {
        return 86400 * 31;
    }

    /**
     * @return GetRatesResult[]
     */
    public function getRatesByRangeDate(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        throw new \App\Exception\NotAvailableMethod();
    }
}
