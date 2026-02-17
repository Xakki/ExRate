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

use function PHPUnit\Framework\assertNotEmpty;

/**
 * @see https://cbr.ru/development/SXML/
 */
final readonly class CbrProvider extends AbstractProviderRate
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
        return 'provider.cbr';
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::CBR;
    }

    public function getBaseCurrency(): string
    {
        return Currencies::RUB;
    }

    public function getHomePage(): string
    {
        return 'https://cbr.ru';
    }

    public function getDescription(): string
    {
        return 'Central Bank of the Russian Federation';
    }

    public function getAvailableCurrencies(): array
    {
        return [Currencies::AED, Currencies::AMD, Currencies::AUD, Currencies::AZN, Currencies::BDT, Currencies::BHD, Currencies::BOB, Currencies::BRL, Currencies::BYN, Currencies::CAD, Currencies::CHF, Currencies::CNY, Currencies::CUP, Currencies::CZK, Currencies::DKK, Currencies::DZD, Currencies::EGP, Currencies::ETB, Currencies::EUR, Currencies::GBP, Currencies::GEL, Currencies::HKD, Currencies::HUF, Currencies::IDR, Currencies::INR, Currencies::IRR, Currencies::JPY, Currencies::KGS, Currencies::KRW, Currencies::KZT, Currencies::MDL, Currencies::MMK, Currencies::MNT, Currencies::NGN, Currencies::NOK, Currencies::NZD, Currencies::OMR, Currencies::PLN, Currencies::QAR, Currencies::RON, Currencies::RSD, Currencies::SAR, Currencies::SEK, Currencies::SGD, Currencies::THB, Currencies::TJS, Currencies::TMT, Currencies::TRY, Currencies::UAH, Currencies::USD, Currencies::UZS, Currencies::VND, Currencies::XDR, Currencies::ZAR];
    }

    public function getRatesByDate(\DateTimeImmutable $date): GetRatesResult
    {
        $url = $this->prepareUrl($this->url, $date, $this->getBaseCurrency());

        $xml = $this->xmlRequest($url);

        $rates = [];
        if (isset($xml->Valute)) {
            foreach ($xml->Valute as $valute) {
                assertNotEmpty($valute->CharCode);
                assertNotEmpty($valute->VunitRate);
                $code = (string) $valute->CharCode;
                $value = (string) $valute->VunitRate;

                $rates[$code] = new RateData(BcMath::round($value, $this->currencyPrecision));
            }
        }

        try {
            $responseDate = Date::createFromFormat('d.m.Y', (string) $xml['Date']);
        } catch (\App\Exception\BadDateException) {
            // TODO: log notice
            $responseDate = $date;
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
