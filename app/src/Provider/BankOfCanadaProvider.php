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
 * @see https://www.bankofcanada.ca/valet/docs
 * TODO: getRatesByRangeDate()
 */
final readonly class BankOfCanadaProvider extends AbstractProviderRate
{
    use UrlTemplateTrait;

    public function __construct(
        protected HttpClientInterface $httpClient,
        protected LoggerInterface $logger,
        protected int $id,
        private string $url,
        private int $currencyPrecision,
        private int $periodDays = 60,
    ) {
    }

    public function getPeriodDays(): int
    {
        return $this->periodDays;
    }

    public static function getServiceName(): string
    {
        return 'provider.bank_of_canada';
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::BANK_OF_CANADA;
    }

    public function getBaseCurrency(): string
    {
        return Currencies::CAD;
    }

    public function getHomePage(): string
    {
        return 'https://www.bankofcanada.ca';
    }

    public function getDescription(): string
    {
        return 'The Bank of Canada';
    }

    public function getAvailableCurrencies(): array
    {
        return [Currencies::AUD, Currencies::BRL, Currencies::CNY, Currencies::EUR, Currencies::HKD, Currencies::INR, Currencies::IDR, Currencies::JPY, Currencies::MXN, Currencies::NZD, Currencies::NOK, Currencies::PEN, Currencies::RUB, Currencies::SAR, Currencies::SGD, Currencies::ZAR, Currencies::KRW, Currencies::SEK, Currencies::CHF, Currencies::TWD, Currencies::TRY, Currencies::GBP, Currencies::USD];
    }

    public function getRequestDelay(): int
    {
        return 1;
    }

    #[\Deprecated]
    public function getRatesByDate(\DateTimeImmutable $date): GetRatesResult
    {
        throw new \App\Exception\NotAvailableMethod();
    }

    /**
     * @return GetRatesResult[]
     */
    public function getRatesByRangeDate(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $url = $this->prepareUrl($this->url, date: $end, baseCurrency: $this->getBaseCurrency(), dateStart: $start);

        $data = $this->jsonRequest($url);

        if (empty($data['observations'])) {
            return [new GetRatesResult($this, $this->getBaseCurrency(), $start, [])];
        }

        $results = [];
        foreach ($data['observations'] as $observation) {
            $responseDate = Date::createFromFormat(Date::FORMAT, $observation['d']);
            $rates = [];
            foreach ($observation as $key => $value) {
                if (str_starts_with($key, 'FX') && str_ends_with($key, Currencies::CAD)) {
                    $currencyCode = substr($key, 2, -3);
                    $rates[$currencyCode] = new RateData(BcMath::round((string) $value['v'], $this->currencyPrecision));
                }
            }
            $results[] = new GetRatesResult($this, $this->getBaseCurrency(), $responseDate, $rates);
        }

        return $results;
    }
}
