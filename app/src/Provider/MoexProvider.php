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
 * @see https://iss.moex.com/iss/reference/
 */
final readonly class MoexProvider implements ProviderInterface
{
    use RequestTrait;
    private const array SECID_MAP = [
        'USD000UTSTOM' => 'USD',
        'EUR_RUB__TOM' => 'EUR',
        'CNYRUB_TOM' => 'CNY',
        'KZTRUB_TOM' => 'KZT',
        'BYNRUB_TOM' => 'BYN',
        'TRYRUB_TOM' => 'TRY',
        'HKDRUB_TOM' => 'HKD',
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $url,
        private int $id,
        private int $currencyPrecision,
    ) {
    }

    public static function getServiceName(): string
    {
        return 'provider.moex';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::MOEX;
    }

    public function getBaseCurrency(): string
    {
        return 'RUB';
    }

    public function getHomePage(): string
    {
        return 'https://www.moex.com';
    }

    public function getDescription(): string
    {
        return 'Moscow Exchange (MOEX) market data.';
    }

    public function getDaysLag(): int
    {
        return 0;
    }

    public function getRatesByDate(\DateTimeImmutable $date): GetRatesResult
    {
        $now = new \DateTimeImmutable();
        if ($date->format('Y-m-d') === $now->format('Y-m-d')) {
            return $this->getLatestRates($date);
        }

        return $this->getHistoricalRates($date);
    }

    private function getLatestRates(\DateTimeImmutable $date): GetRatesResult
    {
        $data = $this->jsonRequest(sprintf('%s/statistics/engines/currency/markets/selt/rates.json', rtrim($this->url, '/')));

        $rates = [];
        if (isset($data['wap_rates'])) {
            $columns = array_flip($data['wap_rates']['columns']);
            foreach ($data['wap_rates']['data'] as $row) {
                $secid = $row[$columns['secid']];
                if (isset(self::SECID_MAP[$secid])) {
                    $currency = self::SECID_MAP[$secid];
                    $price = (string) $row[$columns['price']];
                    $rates[$currency] = BcMath::round($price, $this->currencyPrecision);
                }
            }
        }

        return new GetRatesResult($this->getId(), $this->getBaseCurrency(), $date, $rates);
    }

    private function getHistoricalRates(\DateTimeImmutable $date): GetRatesResult
    {
        $dateStr = $date->format('Y-m-d');
        $rates = [];

        foreach (self::SECID_MAP as $secid => $currency) {
            try {
                $url = sprintf('%s/history/engines/currency/markets/selt/boards/CETS/securities/%s.json', rtrim($this->url, '/'), $secid);
                $data = $this->jsonRequest($url, options: [
                    'query' => [
                        'from' => $dateStr,
                        'till' => $dateStr,
                    ],
                ]);

                if (isset($data['history']) && !empty($data['history']['data'])) {
                    $columns = array_flip($data['history']['columns']);
                    $row = $data['history']['data'][0];
                    // Prefer WAPRICE, fallback to CLOSE
                    $price = (string) ($row[$columns['WAPRICE']] ?? $row[$columns['CLOSE']]);
                    if ('' !== $price && '0' !== $price) {
                        $rates[$currency] = BcMath::round($price, $this->currencyPrecision);
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
        return array_values(self::SECID_MAP);
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
