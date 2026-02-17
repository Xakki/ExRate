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
 * @see https://binance-docs.github.io/apidocs/spot/en/
 */
final readonly class BinanceProvider implements ProviderInterface
{
    use RequestTrait;
    private const array SYMBOLS = ['BTC', 'ETH', 'BNB', 'SOL', 'XRP', 'ADA', 'DOT', 'DOGE', 'LTC', 'LINK', 'TRX', 'MATIC', 'BCH', 'ETC'];

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $url,
        private int $id,
        private int $currencyPrecision,
    ) {
    }

    public static function getServiceName(): string
    {
        return 'provider.binance';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::BINANCE;
    }

    public function getBaseCurrency(): string
    {
        return 'USDT';
    }

    public function getHomePage(): string
    {
        return 'https://www.binance.com';
    }

    public function getDescription(): string
    {
        return 'Binance is a cryptocurrency exchange that provides a platform for trading various cryptocurrencies.';
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
        return self::SYMBOLS;
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

    public function getRatesByDate(\DateTimeImmutable $date): GetRatesResult
    {
        $now = new \DateTimeImmutable();
        $isToday = $date->format('Y-m-d') === $now->format('Y-m-d');

        if ($isToday) {
            return $this->getLatestRates($date);
        }

        return $this->getHistoricalRates($date);
    }

    private function getLatestRates(\DateTimeImmutable $date): GetRatesResult
    {
        $symbols = [];
        foreach (self::SYMBOLS as $s) {
            $symbols[] = $s.$this->getBaseCurrency();
        }

        $data = $this->jsonRequest(sprintf('%s/api/v3/ticker/price', rtrim($this->url, '/')), options: [
            'query' => [
                'symbols' => json_encode($symbols),
            ],
        ]);
        $rates = [];

        foreach ($data as $item) {
            $symbol = $item['symbol'];
            $currencyCode = substr($symbol, 0, -strlen($this->getBaseCurrency()));
            $rates[$currencyCode] = BcMath::round($item['price'], $this->currencyPrecision);
        }

        return new GetRatesResult($this->getId(), $this->getBaseCurrency(), $date, $rates);
    }

    private function getHistoricalRates(\DateTimeImmutable $date): GetRatesResult
    {
        $rates = [];
        $startTime = $date->setTime(0, 0)->getTimestamp() * 1000;

        foreach (self::SYMBOLS as $s) {
            $symbol = $s.$this->getBaseCurrency();
            try {
                $data = $this->jsonRequest(sprintf('%s/api/v3/klines', rtrim($this->url, '/')), options: [
                    'query' => [
                        'symbol' => $symbol,
                        'interval' => '1d',
                        'startTime' => $startTime,
                        'limit' => 1,
                    ],
                ]);

                if (!empty($data)) {
                    /*
                        [
                            1499040000000,         // Kline open time
                            '0.01634790',          // Open price
                            '0.80000000',          // High price
                            '0.01575800',          // Low price
                            '0.01577100',          // Close price
                            '148976.11427815',     // Volume
                            1499644799999,         // Kline Close time
                            '2434.19055334',       // Quote asset volume
                            308,                   // Number of trades
                            '1756.87402397',       // Taker buy base asset volume
                            '28.46694368',         // Taker buy quote asset volume
                            '0'                    // Unused field, ignore.
                        ]
                     */
                    $rates[$s] = BcMath::round((string) $data[0][4], $this->currencyPrecision);
                }
            } catch (\Throwable) {
                // Skip if error for one symbol
                continue;
            }
        }

        return new GetRatesResult($this->getId(), $this->getBaseCurrency(), $date, $rates);
    }
}
