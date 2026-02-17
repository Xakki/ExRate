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
use App\Util\Currencies;
use App\Util\Date;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://www.quodd.com/financial-data-apis
 */
final readonly class XigniteProvider extends AbstractProviderRate
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
        return 'provider.xignite';
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::XIGNITE;
    }

    public function getBaseCurrency(): string
    {
        return Currencies::USD;
    }

    public function getHomePage(): string
    {
        return 'https://xignite.com';
    }

    public function getDescription(): string
    {
        return 'QUODD is a global market data provider delivering tailor-made data products on demand. Access anytime, anywhere with flexible formats and pricing models.';
    }

    public function getAvailableCurrencies(): array
    {
        return [Currencies::AED, Currencies::AFN, Currencies::ALL, Currencies::AMD, Currencies::ANG, Currencies::AOA, Currencies::ARS, Currencies::AUD, Currencies::AWG, Currencies::AZN, Currencies::BAM, Currencies::BBD, Currencies::BDT, Currencies::BGN, Currencies::BHD, Currencies::BIF, Currencies::BMD, Currencies::BND, Currencies::BOB, Currencies::BRL, Currencies::BSD, Currencies::BTN, Currencies::BWP, Currencies::BYN, Currencies::BZD, Currencies::CAD, Currencies::CDF, Currencies::CHF, Currencies::CLF, Currencies::CLP, Currencies::CNH, Currencies::CNY, Currencies::COP, Currencies::CRC, Currencies::CUC, Currencies::CUP, Currencies::CVE, Currencies::CZK, Currencies::DJF, Currencies::DKK, Currencies::DOP, Currencies::DZD, Currencies::EGP, Currencies::ERN, Currencies::ETB, Currencies::EUR, Currencies::FJD, Currencies::FKP, Currencies::GBP, Currencies::GEL, Currencies::GGP, Currencies::GHS, Currencies::GIP, Currencies::GMD, Currencies::GNF, Currencies::GTQ, Currencies::GYD, Currencies::HKD, Currencies::HNL, Currencies::HRK, Currencies::HTG, Currencies::HUF, Currencies::IDR, Currencies::ILS, Currencies::IMP, Currencies::INR, Currencies::IQD, Currencies::IRR, Currencies::ISK, Currencies::JEP, Currencies::JMD, Currencies::JOD, Currencies::JPY, Currencies::KES, Currencies::KGS, Currencies::KHR, Currencies::KMF, Currencies::KPW, Currencies::KRW, Currencies::KWD, Currencies::KYD, Currencies::KZT, Currencies::LAK, Currencies::LBP, Currencies::LKR, Currencies::LRD, Currencies::LSL, Currencies::LYD, Currencies::MAD, Currencies::MDL, Currencies::MGA, Currencies::MKD, Currencies::MMK, Currencies::MNT, Currencies::MOP, Currencies::MRU, Currencies::MUR, Currencies::MVR, Currencies::MWK, Currencies::MXN, Currencies::MYR, Currencies::MZN, Currencies::NAD, Currencies::NGN, Currencies::NIO, Currencies::NOK, Currencies::NPR, Currencies::NZD, Currencies::OMR, Currencies::PAB, Currencies::PEN, Currencies::PGK, Currencies::PHP, Currencies::PKR, Currencies::PLN, Currencies::PYG, Currencies::QAR, Currencies::RON, Currencies::RSD, Currencies::RUB, Currencies::RWF, Currencies::SAR, Currencies::SBD, Currencies::SCR, Currencies::SDG, Currencies::SEK, Currencies::SGD, Currencies::SHP, Currencies::SLE, Currencies::SLL, Currencies::SOS, Currencies::SRD, Currencies::SSP, Currencies::STD, Currencies::STN, Currencies::SVC, Currencies::SYP, Currencies::SZL, Currencies::THB, Currencies::TJS, Currencies::TMT, Currencies::TND, Currencies::TOP, Currencies::TRY, Currencies::TTD, Currencies::TWD, Currencies::TZS, Currencies::UAH, Currencies::UGX, Currencies::USD, Currencies::UYU, Currencies::UZS, Currencies::VEF, Currencies::VES, Currencies::VND, Currencies::VUV, Currencies::WST, Currencies::XAF, Currencies::XAG, Currencies::XAU, Currencies::XCD, Currencies::XCG, Currencies::XDR, Currencies::XOF, Currencies::XPD, Currencies::XPF, Currencies::XPT, Currencies::YER, Currencies::ZAR, Currencies::ZMW, Currencies::ZWG, Currencies::ZWL];
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
        $isHistorical = 0 !== Date::getDayDiff($date);

        $base = $this->getBaseCurrency();
        $currencies = $this->getAvailableCurrencies();

        $symbols = [];
        foreach ($currencies as $currency) {
            if ($currency === $base) {
                continue;
            }
            $symbols[] = $base.$currency;
        }

        $rates = [];
        $responseDate = $date;
        $chunks = array_chunk($symbols, 40);

        foreach ($chunks as $chunk) {
            $symbolsStr = implode(',', $chunk);
            if ($isHistorical) {
                $url = rtrim($this->url, '/').'/GetHistoricalCrossRatesAsOf';
                $params = [
                    'BaseCurrency' => $base,
                    'QuoteCurrencies' => implode(',', array_map(fn ($s) => substr($s, 3), $chunk)),
                    'AsOfDate' => $date->format('m/d/Y'),
                    '_Token' => $this->apiKey,
                ];
            } else {
                $url = rtrim($this->url, '/').'/GetRealTimeRates';
                $params = [
                    'Symbols' => $symbolsStr,
                    '_fields' => 'Outcome,Message,Symbol,Date,Time,Bid',
                    '_Token' => $this->apiKey,
                ];
            }

            $data = $this->jsonRequest($url, options: [
                'query' => $params,
            ]);

            if (empty($data)) {
                continue;
            }

            // Check for global error in the first item
            if (isset($data[0]['Outcome']) && 'Success' !== $data[0]['Outcome']) {
                if (!in_array($data[0]['Outcome'], ['SymbolNotFound', 'RequestError'])) {
                    throw new FailedProviderException($data[0]['Message'] ?? 'Failed to parse Xignite response');
                }
            }

            foreach ($data as $item) {
                if (isset($item['Outcome']) && 'Success' !== $item['Outcome']) {
                    continue;
                }

                $symbol = $item['Symbol'] ?? null;
                // Historical response might have QuoteCurrency instead of Symbol
                if (!$symbol && isset($item['QuoteCurrency'])) {
                    $symbol = $base.$item['QuoteCurrency'];
                }

                if (!$symbol) {
                    continue;
                }

                $code = substr($symbol, 3);
                $value = $item['Bid'] ?? $item['Value'] ?? $item['Close'] ?? $item['Last'] ?? null;

                if (null !== $value) {
                    $rates[$code] = new RateData(BcMath::round((string) $value, $this->currencyPrecision));
                }

                if (!$isHistorical && isset($item['Date'], $item['Time'])) {
                    $dateStr = $item['Date'].' '.$item['Time'];
                    try {
                        $responseDate = Date::createFromFormat('m/d/Y H:i:s A', $dateStr);
                    } catch (\App\Exception\BadDateException) {
                        // ignore
                    }
                }
            }
        }

        return new GetRatesResult($this, $base, $responseDate, $rates);
    }

    /**
     * @return GetRatesResult[]
     */
    public function getRatesByRangeDate(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        throw new \App\Exception\NotAvailableMethod();
    }
}
