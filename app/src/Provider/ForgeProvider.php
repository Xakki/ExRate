<?php

declare(strict_types=1);

namespace App\Provider;

use App\DTO\GetRatesResult;
use App\DTO\RateData;
use App\Enum\ProviderEnum;
use App\Exception\DisabledProviderException;
use App\Service\AbstractProviderRate;
use App\Util\BcMath;
use App\Util\CryptoCurrencies;
use App\Util\Currencies;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://1forge.com/forex-data-api/api-documentation
 */
final readonly class ForgeProvider extends AbstractProviderRate
{
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
        return 'provider.forge';
    }

    public function isActive(): bool
    {
        return !empty($this->apiKey);
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::FORGE;
    }

    public function getBaseCurrency(): string
    {
        return Currencies::USD;
    }

    public function getHomePage(): string
    {
        return 'https://1forge.com';
    }

    public function getDescription(): string
    {
        return 'Real-time Forex and Crypto API';
    }

    public function getAvailableCurrencies(): array
    {
        return [Currencies::AED, Currencies::AFN, Currencies::ALL, Currencies::AMD, Currencies::ANG, Currencies::AOA, Currencies::ARS, Currencies::AUD, Currencies::AWG, Currencies::AZN, Currencies::BAM, Currencies::BBD, Currencies::BDT, Currencies::BGN, Currencies::BHD, Currencies::BIF, Currencies::BMD, Currencies::BND, Currencies::BOB, Currencies::BRL, Currencies::BSD, CryptoCurrencies::BTC, Currencies::BTN, Currencies::BWP, Currencies::BYN, Currencies::BZD, Currencies::CAD, Currencies::CDF, Currencies::CHF, Currencies::CLF, Currencies::CLP, Currencies::CNH, Currencies::CNY, Currencies::COP, Currencies::CRC, Currencies::CUC, Currencies::CUP, Currencies::CVE, Currencies::CZK, Currencies::DJF, Currencies::DKK, Currencies::DOP, Currencies::DZD, Currencies::EGP, Currencies::ERN, Currencies::ETB, Currencies::EUR, Currencies::FJD, Currencies::FKP, Currencies::GBP, Currencies::GEL, Currencies::GGP, Currencies::GHS, Currencies::GIP, Currencies::GMD, Currencies::GNF, Currencies::GTQ, Currencies::GYD, Currencies::HKD, Currencies::HNL, Currencies::HRK, Currencies::HTG, Currencies::HUF, Currencies::IDR, Currencies::ILS, Currencies::IMP, Currencies::INR, Currencies::IQD, Currencies::IRR, Currencies::ISK, Currencies::JEP, Currencies::JMD, Currencies::JOD, Currencies::JPY, Currencies::KES, Currencies::KGS, Currencies::KHR, Currencies::KMF, Currencies::KPW, Currencies::KRW, Currencies::KWD, Currencies::KYD, Currencies::KZT, Currencies::LAK, Currencies::LBP, Currencies::LKR, Currencies::LRD, Currencies::LSL, Currencies::LYD, Currencies::MAD, Currencies::MDL, Currencies::MGA, Currencies::MKD, Currencies::MMK, Currencies::MNT, Currencies::MOP, Currencies::MRU, Currencies::MUR, Currencies::MVR, Currencies::MWK, Currencies::MXN, Currencies::MYR, Currencies::MZN, Currencies::NAD, Currencies::NGN, Currencies::NIO, Currencies::NOK, Currencies::NPR, Currencies::NZD, Currencies::OMR, Currencies::PAB, Currencies::PEN, Currencies::PGK, Currencies::PHP, Currencies::PKR, Currencies::PLN, Currencies::PYG, Currencies::QAR, Currencies::RON, Currencies::RSD, Currencies::RUB, Currencies::RWF, Currencies::SAR, Currencies::SBD, Currencies::SCR, Currencies::SDG, Currencies::SEK, Currencies::SGD, Currencies::SHP, Currencies::SLE, Currencies::SLL, Currencies::SOS, Currencies::SRD, Currencies::SSP, Currencies::STD, Currencies::STN, Currencies::SVC, Currencies::SYP, Currencies::SZL, Currencies::THB, Currencies::TJS, Currencies::TMT, Currencies::TND, Currencies::TOP, Currencies::TRY, Currencies::TTD, Currencies::TWD, Currencies::TZS, Currencies::UAH, Currencies::UGX, Currencies::USD, Currencies::UYU, Currencies::UZS, Currencies::VEF, Currencies::VES, Currencies::VND, Currencies::VUV, Currencies::WST, Currencies::XAF, Currencies::XAG, Currencies::XAU, Currencies::XCD, Currencies::XCG, Currencies::XDR, Currencies::XOF, Currencies::XPD, Currencies::XPF, Currencies::XPT, Currencies::YER, Currencies::ZAR, Currencies::ZMW, Currencies::ZWG, Currencies::ZWL];
    }

    public function getRequestLimit(): int
    {
        return 100;
    }

    public function getRequestLimitPeriod(): int
    {
        return 86400;
    }

    public function getRatesByDate(\DateTimeImmutable $date): GetRatesResult
    {
        // Forge API requires specific pairs. This is just an example implementation.
        $url = rtrim($this->url, '/').'/quotes?pairs=USD/EUR,USD/GBP,USD/JPY,USD/RUB';
        $data = $this->jsonRequest($url, options: [
            'query' => [
                'api_key' => $this->apiKey,
            ],
        ]);

        $rates = [];
        $responseDate = $date;

        foreach ($data as $item) {
            $pair = $item['s']; // e.g. USDEUR
            $code = substr($pair, 3);
            $rates[$code] = new RateData(BcMath::round((string) $item['p'], $this->currencyPrecision));
            if (isset($item['t'])) {
                $responseDate = (new \DateTimeImmutable(timezone: new \DateTimeZone('UTC')))->setTimestamp($item['t']);
            }
        }

        return new GetRatesResult($this, $this->getBaseCurrency(), $responseDate, $rates);
    }

    /**
     * @return GetRatesResult[]
     */
    public function getRatesByRangeDate(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        throw new \App\Exception\NotAvailableMethod();
    }
}
