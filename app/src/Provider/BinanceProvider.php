<?php

declare(strict_types=1);

namespace App\Provider;

use App\Contract\ProviderRateExtendInterface;
use App\DTO\GetRatesResult;
use App\DTO\RateExtendData;
use App\Enum\ProviderEnum;
use App\Service\AbstractProviderRate;
use App\Util\BcMath;
use App\Util\CryptoCurrencies;
use App\Util\Date;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://binance-docs.github.io/apidocs/spot/en/
 */
final readonly class BinanceProvider extends AbstractProviderRate implements ProviderRateExtendInterface
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
        return 'provider.binance';
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::BINANCE;
    }

    public function getBaseCurrency(): string
    {
        return CryptoCurrencies::USDT;
    }

    public function getHomePage(): string
    {
        return 'https://www.binance.com';
    }

    public function getDescription(): string
    {
        return 'Binance';
    }

    public function getAvailableCurrencies(): array
    {
        return [
            CryptoCurrencies::BTC,
            CryptoCurrencies::ETH,
            CryptoCurrencies::BNB,
            CryptoCurrencies::SOL,
            CryptoCurrencies::XRP,
            CryptoCurrencies::ADA,
            CryptoCurrencies::DOT,
            CryptoCurrencies::DOGE,
            CryptoCurrencies::LTC,
            CryptoCurrencies::LINK,
            CryptoCurrencies::TRX,
            CryptoCurrencies::MATIC,
            CryptoCurrencies::BCH,
            CryptoCurrencies::ETC,
        ];
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
        $startTime = $start->setTime(0, 0)->getTimestamp() * 1000;
        $endTime = $end->setTime(23, 59, 59)->getTimestamp() * 1000;

        $allRates = []; // [date_str => [symbol => rate]]

        foreach ($this->getAvailableCurrencies() as $s) {
            $symbol = $s.$this->getBaseCurrency();
            try {
                $data = $this->jsonRequest(sprintf('%s/api/v3/klines', rtrim($this->url, '/')), options: [
                    'query' => [
                        'symbol' => $symbol,
                        'interval' => '1d',
                        'startTime' => $startTime,
                        'endTime' => $endTime,
                        'limit' => 3000,
                    ],
                ]);
                usleep(500000);
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
                foreach ($data as $kline) {
                    $timestamp = (int) ($kline[0] / 1000);
                    $dateStr = (new \DateTimeImmutable())->setTimestamp($timestamp)->format(Date::FORMAT);
                    $allRates[$dateStr][$s] = new RateExtendData(
                        BcMath::round((string) $kline[1], $this->currencyPrecision),
                        BcMath::round((string) $kline[3], $this->currencyPrecision),
                        BcMath::round((string) $kline[2], $this->currencyPrecision),
                        BcMath::round((string) $kline[4], $this->currencyPrecision),
                        BcMath::round((string) $kline[5], $this->currencyPrecision),
                    );
                }
            } catch (\Throwable) {
                continue;
            }
        }

        $results = [];
        foreach ($allRates as $dateStr => $rates) {
            $date = Date::createFromFormat(Date::FORMAT, $dateStr);
            $results[] = new GetRatesResult($this, $this->getBaseCurrency(), $date, $rates);
        }

        return $results;
    }

    #[\Deprecated]
    public function getRatesByDate(\DateTimeImmutable $date): GetRatesResult
    {
        throw new \App\Exception\NotAvailableMethod();
    }
}
