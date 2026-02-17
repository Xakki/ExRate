<?php

declare(strict_types=1);

namespace App\Provider;

use App\Contract\ProviderInterface;
use App\DTO\GetRatesResult;
use App\Enum\ProviderEnum;
use App\Util\BcMath;
use App\Util\RequestTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://dadosabertos.bcb.gov.br/dataset/dolar-americano-usd-todos-os-boletins-diarios
 */
final readonly class BcbProvider implements ProviderInterface
{
    use RequestTrait;
    private const array CURRENCIES = ['AUD', 'CAD', 'CHF', 'DKK', 'EUR', 'GBP', 'JPY', 'NOK', 'SEK', 'USD'];

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $url,
        private int $id,
        private int $currencyPrecision,
    ) {
    }

    public static function getServiceName(): string
    {
        return 'provider.bcb';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::BCB;
    }

    public function getBaseCurrency(): string
    {
        return 'BRL';
    }

    public function getHomePage(): string
    {
        return 'https://www.bcb.gov.br';
    }

    public function getDescription(): string
    {
        return 'Central Bank of Brazil (BCB) provides official exchange rates for Brazilian Real.';
    }

    public function getDaysLag(): int
    {
        return 0;
    }

    public function getRatesByDate(\DateTimeImmutable $date): GetRatesResult
    {
        $dateStr = $date->format('m-d-Y');
        $rates = [];

        foreach (self::CURRENCIES as $currency) {
            try {
                if ('USD' === $currency) {
                    $endpoint = sprintf("DollarRateDate(dataCotacao=@dataCotacao)?@dataCotacao='%s'&\$format=json", $dateStr);
                } else {
                    $endpoint = sprintf("ExchangeRateDate(moeda=@moeda,dataCotacao=@dataCotacao)?@moeda='%s'&@dataCotacao='%s'&\$format=json", $currency, $dateStr);
                }

                $url = sprintf('%s/%s', rtrim($this->url, '/'), $endpoint);
                $data = $this->jsonRequest($url);

                if (!empty($data['value'])) {
                    $value = null;
                    if ('USD' === $currency) {
                        $value = $data['value'][0]['cotacaoVenda'] ?? null;
                    } else {
                        // Find "Fechamento PTAX"
                        foreach ($data['value'] as $item) {
                            if (isset($item['tipoBoletim']) && 'Fechamento PTAX' === $item['tipoBoletim']) {
                                $value = $item['cotacaoVenda'];
                                break;
                            }
                        }
                        // Fallback to last item if not found
                        if (null === $value) {
                            $value = end($data['value'])['cotacaoVenda'] ?? null;
                        }
                    }

                    if (null !== $value) {
                        $rates[$currency] = BcMath::round((string) $value, $this->currencyPrecision);
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return new GetRatesResult($this->getId(), $this->getBaseCurrency(), $date, $rates);
    }

    public function isActive(): bool
    {
        return true;
    }

    public function getAvailableCurrencies(): array
    {
        return self::CURRENCIES;
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

    /**
     * @return GetRatesResult[]
     */
    public function getRatesByRangeDate(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        throw new \App\Exception\NotAvailableMethod();
    }
}
