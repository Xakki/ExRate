<?php

declare(strict_types=1);

namespace App\Provider;

use App\Contract\ProviderInterface;
use App\DTO\GetRatesResult;
use App\Enum\ProviderEnum;
use App\Util\BcMath;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://www.cnb.cz/en/financial-markets/foreign-exchange-market/central-bank-exchange-rate-fixing/central-bank-exchange-rate-fixing/
 */
final readonly class CnbProvider implements ProviderInterface
{
    public const URL = 'https://www.cnb.cz/cs/financni-trhy/devizovy-trh/kurzy-devizoveho-trhu/kurzy-devizoveho-trhu/denni_kurz.txt';

    public function __construct(
        private HttpClientInterface $httpClient,
        private int $id,
        private int $currencyPrecision,
    ) {
    }

    public static function getServiceName(): string
    {
        return 'provider.cnb';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::CNB;
    }

    public function getBaseCurrency(): string
    {
        return 'CZK';
    }

    public function getHomePage(): string
    {
        return 'https://www.cnb.cz';
    }

    public function getDescription(): string
    {
        return 'Czech National Bank';
    }

    public function getRates(\DateTimeImmutable $date): GetRatesResult
    {
        $response = $this->httpClient->request('GET', self::URL, [
            'query' => [
                'date' => $date->format('d.m.Y'),
            ],
        ]);

        $content = $response->getContent();
        $lines = explode('
', $content);

        // First line: 20.02.2025 #36
        $firstLine = $lines[0];
        $dateParts = explode(' ', $firstLine);
        $responseDate = \DateTimeImmutable::createFromFormat('d.m.Y', $dateParts[0]) ?: $date;

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

            $rates[$code] = BcMath::div($rate, $amount, $this->currencyPrecision);
        }

        return new GetRatesResult($this->getId(), $this->getBaseCurrency(), $responseDate, $rates);
    }

    public function isActive(): bool
    {
        return true;
    }

    public function getAvailableCurrencies(): array
    {
        return ['AUD', 'BRL', 'CAD', 'CHF', 'CNY', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'IDR', 'ILS', 'INR', 'ISK', 'JPY', 'KRW', 'MXN', 'MYR', 'NOK', 'NZD', 'PHP', 'PLN', 'RON', 'SEK', 'SGD', 'THB', 'TRY', 'USD', 'XDR', 'ZAR'];
    }

    public function getRequestLimit(): int
    {
        return 0;
    }

    public function getRequestLimitPeriod(): int
    {
        return 0;
    }

    public function getRequestDelay(): int
    {
        return 2;
    }
}
