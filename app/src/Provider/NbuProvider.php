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
 * @see https://bank.gov.ua/en/markets/exchangerates
 */
final readonly class NbuProvider extends AbstractProviderRate
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
        return 'provider.nbu';
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::NBU;
    }

    public function getBaseCurrency(): string
    {
        return Currencies::UAH;
    }

    public function getHomePage(): string
    {
        return 'https://bank.gov.ua';
    }

    public function getDescription(): string
    {
        return 'National Bank of Ukraine';
    }

    public function getRatesByDate(\DateTimeImmutable $date): GetRatesResult
    {
        $url = $this->prepareUrl($this->url, $date, $this->getBaseCurrency());

        $xml = $this->xmlRequest($url);

        $rates = [];
        $responseDate = $date;

        foreach ($xml->currency as $item) {
            $code = (string) $item->cc;
            $rate = (string) $item->rate;

            if ($code && $rate) {
                $rates[$code] = new RateData(BcMath::round($rate, $this->currencyPrecision));
            }

            if (isset($item->exchangedate)) {
                try {
                    $responseDate = Date::createFromFormat('d.m.Y', (string) $item->exchangedate);
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
        return [Currencies::AED, Currencies::AUD, Currencies::AZN, Currencies::BDT, Currencies::CAD, Currencies::CHF, Currencies::CNY, Currencies::CZK, Currencies::DKK, Currencies::DZD, Currencies::EGP, Currencies::EUR, Currencies::GBP, Currencies::GEL, Currencies::HKD, Currencies::HUF, Currencies::IDR, Currencies::ILS, Currencies::INR, Currencies::JPY, Currencies::KRW, Currencies::KZT, Currencies::LBP, Currencies::MDL, Currencies::MXN, Currencies::MYR, Currencies::NOK, Currencies::NZD, Currencies::PLN, Currencies::RON, Currencies::RSD, Currencies::SAR, Currencies::SEK, Currencies::SGD, Currencies::THB, Currencies::TND, Currencies::TRY, Currencies::USD, Currencies::VND, Currencies::XAG, Currencies::XAU, Currencies::XPD, Currencies::XPT, Currencies::XDR, Currencies::ZAR];
    }

    /**
     * @return GetRatesResult[]
     */
    public function getRatesByRangeDate(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        throw new \App\Exception\NotAvailableMethod();
    }
}
