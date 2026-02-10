<?php

declare(strict_types=1);

namespace App\Provider;

use App\Contract\ProviderInterface;
use App\DTO\GetRatesResult;
use App\Enum\ProviderEnum;
use App\Util\BcMath;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://www.bnb.bg
 *
 * @todo URL returns HTML instead of XML. Needs update or fix.
 */
final readonly class BnbProvider implements ProviderInterface
{
    public const string URL = 'https://www.bnb.bg/Statistics/StExternalSector/StExchangeRates/StERForeignCurrencies/index.htm?lang=EN&downloadOper=true&group1=first&firstDays=%s&firstMonths=%s&firstYear=%s&search=true&showChart=false&showChartButton=false&type=XML';

    public function __construct(
        private HttpClientInterface $httpClient,
        private int $id,
        private int $currencyPrecision,
    ) {
    }

    public static function getServiceName(): string
    {
        return 'provider.bnb';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::BNB;
    }

    public function getBaseCurrency(): string
    {
        return 'BGN';
    }

    public function getHomePage(): string
    {
        return 'https://www.bnb.bg';
    }

    public function getDescription(): string
    {
        return 'Bulgarian National Bank';
    }

    public function getRates(\DateTimeImmutable $date): GetRatesResult
    {
        $day = $date->format('d');
        $month = $date->format('m');
        $year = $date->format('Y');

        $url = sprintf(self::URL, $day, $month, $year);

        $response = $this->httpClient->request('GET', $url);
        $content = $response->getContent();

        if ('<ROWSET>' !== mb_substr($content, 0, 8)) {
            return new GetRatesResult($this->getId(), $this->getBaseCurrency(), $date, []);
        }
        $xml = simplexml_load_string($content);

        if (false === $xml) {
            throw new \RuntimeException('Failed to parse BNB XML response');
        }

        $rates = [];
        $responseDate = $date;

        foreach ($xml->ROW as $row) {
            $code = (string) $row->CODE;
            if (!$code) {
                continue;
            }
            $rate = (string) $row->RATE;
            $ratio = (string) $row->RATIO;

            if ($rate && $ratio) {
                $rates[$code] = BcMath::div($rate, $ratio, $this->currencyPrecision);
            }

            if (isset($row->CURR_DATE)) {
                $responseDate = \DateTimeImmutable::createFromFormat('d.m.Y', (string) $row->CURR_DATE) ?: $responseDate;
            }
        }

        return new GetRatesResult($this->getId(), $this->getBaseCurrency(), $responseDate, $rates);
    }

    public function isActive(): bool
    {
        return false;
    }

    public function getAvailableCurrencies(): array
    {
        return ['AUD', 'BRL', 'CAD', 'CHF', 'CNY', 'CZK', 'DKK', 'GBP', 'HKD', 'HUF', 'IDR', 'ILS', 'INR', 'ISK', 'JPY', 'KRW', 'MXN', 'MYR', 'NOK', 'NZD', 'PHP', 'PLN', 'RON', 'SEK', 'SGD', 'THB', 'TRY', 'USD', 'ZAR'];
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
