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
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://www.frankfurter.app/docs/
 */
final readonly class FrankfurterProvider extends AbstractProviderRate
{
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
        return 'provider.frankfurter';
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::FRANKFURTER;
    }

    public function getBaseCurrency(): string
    {
        return Currencies::EUR;
    }

    public function getHomePage(): string
    {
        return 'https://www.frankfurter.app';
    }

    public function getDescription(): string
    {
        return 'Frankfurter API by the European Central Bank.';
    }

    public function getAvailableCurrencies(): array
    {
        return [Currencies::AUD, Currencies::BRL, Currencies::CAD, Currencies::CHF, Currencies::CNY, Currencies::CZK, Currencies::DKK, Currencies::GBP, Currencies::HKD, Currencies::HUF, Currencies::IDR, Currencies::ILS, Currencies::INR, Currencies::ISK, Currencies::JPY, Currencies::KRW, Currencies::MXN, Currencies::MYR, Currencies::NOK, Currencies::NZD, Currencies::PHP, Currencies::PLN, Currencies::RON, Currencies::SEK, Currencies::SGD, Currencies::THB, Currencies::TRY, Currencies::USD, Currencies::ZAR];
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
        $url = sprintf('%s/%s..%s', rtrim($this->url, '/'), $start->format(Date::FORMAT), $end->format(Date::FORMAT));

        $data = $this->jsonRequest($url);

        assert($data['base'] === $this->getBaseCurrency());
        assert($data['start_date']);
        assert($data['end_date']);
        assert($data['rates']);
        $results = [];
        foreach ($data['rates'] as $dateStr => $dayRates) {
            $responseDate = Date::createFromFormat(Date::FORMAT, $dateStr);
            $rates = [];
            foreach ($dayRates as $code => $value) {
                $rates[$code] = new RateData(BcMath::div(1, (string) $value, $this->currencyPrecision));
            }
            $results[] = new GetRatesResult($this, $this->getBaseCurrency(), $responseDate, $rates);
        }

        return $results;
    }
}
