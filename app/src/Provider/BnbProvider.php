<?php

declare(strict_types=1);

namespace App\Provider;

use App\Contract\ProviderInterface;
use App\DTO\GetRatesResult;
use App\Enum\ProviderEnum;
use App\Exception\FailedProviderException;
use App\Util\BcMath;
use App\Util\RequestTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://www.bnb.bg
 */
final readonly class BnbProvider implements ProviderInterface
{
    use RequestTrait;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $url,
        private int $id,
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

    public function getDaysLag(): int
    {
        return 0;
    }

    public function getRatesByDate(\DateTimeImmutable $date): GetRatesResult
    {
        $day = $date->format('d');
        $month = $date->format('m');
        $year = $date->format('Y');

        $url = $this->url.sprintf('?lang=EN&downloadOper=true&group1=first&firstDays=%s&firstMonths=%s&firstYear=%s&search=true&showChart=false&showChartButton=false&type=XML', $day, $month, $year);

        try {
            $xml = $this->xmlRequest($url);
        } catch (FailedProviderException) {
            return new GetRatesResult($this->getId(), $this->getBaseCurrency(), $date, []);
        }

        $rates = [];
        $responseDate = null;

        foreach ($xml->ROW as $row) {
            if (isset($row->TITLE)) {
                continue;
            }
            $code = (string) $row->CODE;
            $rate = (string) $row->RATE;

            if ($rate) {
                $rates[$code] = BcMath::normalize($rate);
            }

            if (!$responseDate) {
                $responseDate = \DateTimeImmutable::createFromFormat('d.m.Y', (string) $row->CURR_DATE) ?: $date;
            }
        }

        return new GetRatesResult($this->getId(), $this->getBaseCurrency(), $responseDate, $rates);
    }

    public function isActive(): bool
    {
        return true;
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

    /**
     * @return GetRatesResult[]
     */
    public function getRatesByRangeDate(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        throw new \App\Exception\NotAvailableMethod();
    }
}
