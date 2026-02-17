<?php

declare(strict_types=1);

namespace App\Provider;

use App\Contract\ProviderInterface;
use App\DTO\GetRatesResult;
use App\Enum\ProviderEnum;
use App\Util\BcMath;
use App\Util\RequestTrait;
use App\Util\UrlTemplateTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://www.bankofcanada.ca/valet/docs
 * TODO: getRatesByRangeDate()
 */
final readonly class BankOfCanadaProvider implements ProviderInterface
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
        return 'provider.bank_of_canada';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::BANK_OF_CANADA;
    }

    public function getBaseCurrency(): string
    {
        return 'CAD';
    }

    public function getHomePage(): string
    {
        return 'https://www.bankofcanada.ca';
    }

    public function getDescription(): string
    {
        return 'Official exchange rates from the Bank of Canada.';
    }

    public function getDaysLag(): int
    {
        return 0;
    }

    public function isActive(): bool
    {
        return true;
    }

    public function getAvailableCurrencies(): array
    {
        return ['AUD', 'BRL', 'CNY', 'EUR', 'HKD', 'INR', 'IDR', 'JPY', 'MXN', 'NZD', 'NOK', 'PEN', 'RUB', 'SAR', 'SGD', 'ZAR', 'KRW', 'SEK', 'CHF', 'TWD', 'TRY', 'GBP', 'USD'];
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
        return 1;
    }

    public function getRatesByDate(\DateTimeImmutable $date): GetRatesResult
    {
        $url = $this->prepareUrl($this->url, $date, $this->getBaseCurrency());

        $data = $this->jsonRequest($url);

        if (empty($data['observations'])) {
            return new GetRatesResult($this->getId(), $this->getBaseCurrency(), $date, []);
        }

        $observation = $data['observations'][0];
        $responseDate = \DateTimeImmutable::createFromFormat('Y-m-d', $observation['d']) ?: $date;
        $rates = [];

        foreach ($observation as $key => $value) {
            if ('d' === $key) {
                continue;
            }

            // Key is FX{CURRENCY}CAD
            if (str_starts_with($key, 'FX') && str_ends_with($key, 'CAD')) {
                $currencyCode = substr($key, 2, -3);
                $rates[$currencyCode] = BcMath::round((string) $value['v'], $this->currencyPrecision);
            }
        }

        return new GetRatesResult($this->getId(), $this->getBaseCurrency(), $responseDate, $rates);
    }

    /**
     * @return GetRatesResult[]
     */
    public function getRatesByRangeDate(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        throw new \App\Exception\NotAvailableMethod();
    }
}
