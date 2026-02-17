<?php

declare(strict_types=1);

namespace App\Provider;

use App\Contract\ProviderInterface;
use App\DTO\GetRatesResult;
use App\Enum\ProviderEnum;
use App\Exception\FailedProviderException;
use App\Util\BcMath;
use App\Util\RequestTrait;
use App\Util\UrlTemplateTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://nbg.gov.ge/en/monetary-policy/currency
 */
final readonly class NbgProvider implements ProviderInterface
{
    use UrlTemplateTrait;
    use RequestTrait;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $url,
        private int $id,
        private int $currencyPrecision,
    ) {
    }

    public static function getServiceName(): string
    {
        return 'provider.nbg';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::NBG;
    }

    public function getBaseCurrency(): string
    {
        return 'GEL';
    }

    public function getHomePage(): string
    {
        return 'https://nbg.gov.ge';
    }

    public function getDescription(): string
    {
        return 'National Bank of Georgia';
    }

    public function getDaysLag(): int
    {
        return 0;
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

            $rates[$code] = BcMath::div($rateStr, $quantity, $this->currencyPrecision);
        }

        return new GetRatesResult($this->getId(), $this->getBaseCurrency(), $responseDate, $rates);
    }

    public function isActive(): bool
    {
        return true;
    }

    public function getAvailableCurrencies(): array
    {
        return ['AED', 'AMD', 'AUD', 'AZN', 'BRL', 'BYN', 'CAD', 'CHF', 'CNY', 'CZK', 'DKK', 'EGP', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'INR', 'IRR', 'ISK', 'JPY', 'KGS', 'KRW', 'KWD', 'KZT', 'MDL', 'NOK', 'NZD', 'PLN', 'QAR', 'RON', 'RSD', 'RUB', 'SEK', 'SGD', 'TJS', 'TMT', 'TRY', 'UAH', 'USD', 'UZS', 'ZAR'];
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
