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
 * @see https://www.cnb.cz/en/financial-markets/foreign-exchange-market/central-bank-exchange-rate-fixing/central-bank-exchange-rate-fixing/
 */
final readonly class CnbProvider extends AbstractProviderRate
{
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
        return 'provider.cnb';
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::CNB;
    }

    public function getBaseCurrency(): string
    {
        return Currencies::CZK;
    }

    public function getHomePage(): string
    {
        return 'https://www.cnb.cz';
    }

    public function getDescription(): string
    {
        return 'Czech National Bank';
    }

    public function getRatesByDate(\DateTimeImmutable $date): GetRatesResult
    {
        $response = $this->request($this->url, options: [
            'query' => [
                'date' => $date->format('d.m.Y'),
            ],
        ]);
        $content = $response->getContent();
        $lines = explode(PHP_EOL, $content);

        // First line: 20.02.2025 #36
        $firstLine = $lines[0];
        $dateParts = explode(' ', $firstLine);
        try {
            $responseDate = Date::createFromFormat('d.m.Y', $dateParts[0]);
        } catch (\App\Exception\BadDateException) {
            // TODO: log notice
            $responseDate = $date;
        }

        $rates = [];
        // Skip first two lines (header)
        foreach (array_slice($lines, 2) as $line) {
            $line = trim($line);
            if (!$line) {
                continue;
            }

            $parts = explode('|', $line);
            if (count($parts) < 5) {
                continue;
            }

            // země|měna|množství|kód|kurz
            $amount = $parts[2];
            $code = $parts[3];
            $rate = $parts[4];

            $rates[$code] = new RateData(BcMath::div($rate, $amount, $this->currencyPrecision));
        }

        return new GetRatesResult($this, $this->getBaseCurrency(), $responseDate, $rates);
    }

    public function getAvailableCurrencies(): array
    {
        return [Currencies::AUD, Currencies::BRL, Currencies::CAD, Currencies::CHF, Currencies::CNY, Currencies::DKK, Currencies::EUR, Currencies::GBP, Currencies::HKD, Currencies::HUF, Currencies::IDR, Currencies::ILS, Currencies::INR, Currencies::ISK, Currencies::JPY, Currencies::KRW, Currencies::MXN, Currencies::MYR, Currencies::NOK, Currencies::NZD, Currencies::PHP, Currencies::PLN, Currencies::RON, Currencies::SEK, Currencies::SGD, Currencies::THB, Currencies::TRY, Currencies::USD, Currencies::XDR, Currencies::ZAR];
    }

    /**
     * @return GetRatesResult[]
     */
    public function getRatesByRangeDate(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        throw new \App\Exception\NotAvailableMethod();
    }
}
