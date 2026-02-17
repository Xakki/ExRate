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
use App\Util\UrlTemplateTrait;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://nbg.gov.ge/en/monetary-policy/currency
 */
final readonly class NbgProvider extends AbstractProviderRate
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
        return 'provider.nbg';
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::NBG;
    }

    public function getBaseCurrency(): string
    {
        return Currencies::GEL;
    }

    public function getHomePage(): string
    {
        return 'https://nbg.gov.ge';
    }

    public function getDescription(): string
    {
        return 'National Bank of Georgia';
    }

    public function getRatesByDate(\DateTimeImmutable $date): GetRatesResult
    {
        $url = $this->prepareUrl($this->url, $date, $this->getBaseCurrency());

        $data = $this->jsonRequest($url);

        if (!isset($data[0])) {
            throw new FailedProviderException('Failed to parse NBG JSON response');
        }

        $element = $data[0];
        $responseDate = new \DateTimeImmutable((string) $element['date']);
        $rates = [];

        foreach ($element['currencies'] as $currency) {
            $code = $currency['code'];
            $rateStr = (string) $currency['rate'];
            $quantity = (string) $currency['quantity'];

            $rates[$code] = new RateData(BcMath::div($rateStr, $quantity, $this->currencyPrecision));
        }

        return new GetRatesResult($this, $this->getBaseCurrency(), $responseDate, $rates);
    }

    public function getAvailableCurrencies(): array
    {
        return [Currencies::AED, Currencies::AMD, Currencies::AUD, Currencies::AZN, Currencies::BRL, Currencies::BYN, Currencies::CAD, Currencies::CHF, Currencies::CNY, Currencies::CZK, Currencies::DKK, Currencies::EGP, Currencies::EUR, Currencies::GBP, Currencies::HKD, Currencies::HUF, Currencies::ILS, Currencies::INR, Currencies::IRR, Currencies::ISK, Currencies::JPY, Currencies::KGS, Currencies::KRW, Currencies::KWD, Currencies::KZT, Currencies::MDL, Currencies::NOK, Currencies::NZD, Currencies::PLN, Currencies::QAR, Currencies::RON, Currencies::RSD, Currencies::RUB, Currencies::SEK, Currencies::SGD, Currencies::TJS, Currencies::TMT, Currencies::TRY, Currencies::UAH, Currencies::USD, Currencies::UZS, Currencies::ZAR];
    }

    /**
     * @return GetRatesResult[]
     */
    public function getRatesByRangeDate(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        throw new \App\Exception\NotAvailableMethod();
    }
}
