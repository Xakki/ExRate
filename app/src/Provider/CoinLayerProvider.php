<?php

declare(strict_types=1);

namespace App\Provider;

use App\DTO\GetRatesResult;
use App\DTO\RateData;
use App\Enum\ProviderEnum;
use App\Exception\DisabledProviderException;
use App\Exception\FailedProviderException;
use App\Service\AbstractProviderRate;
use App\Util\BcMath;
use App\Util\CryptoCurrencies;
use App\Util\Currencies;
use App\Util\Date;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://docs.apilayer.com/coinlayer/
 */
final readonly class CoinLayerProvider extends AbstractProviderRate
{
    public function __construct(
        protected HttpClientInterface $httpClient,
        protected LoggerInterface $logger,
        protected int $id,
        private string $url,
        private int $currencyPrecision,
        private string $apiKey,
    ) {
        if (empty($this->apiKey)) {
            throw new DisabledProviderException('Provider disabled: Need API key');
        }
    }

    public static function getServiceName(): string
    {
        return 'provider.coin_layer';
    }

    public function isActive(): bool
    {
        return !empty($this->apiKey);
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::COIN_LAYER;
    }

    public function getBaseCurrency(): string
    {
        return Currencies::USD;
    }

    public function getHomePage(): string
    {
        return 'https://coinlayer.com';
    }

    public function getDescription(): string
    {
        return 'Access real-time and historical crypto data with Coinlayer’s powerful Crypto Currency API. Built for speed, simplicity, and performance—20ms response time, easy integration, and extensive documentation.';
    }

    public function getRatesByDate(\DateTimeImmutable $date): GetRatesResult
    {
        $isToday = 0 === Date::getDayDiff($date);
        $baseUrl = rtrim($this->url, '/');
        $url = $isToday ? $baseUrl.'/live' : sprintf($baseUrl.'/%s', $date->format(Date::FORMAT));

        $data = $this->jsonRequest($url, options: [
            'query' => [
                'access_key' => $this->apiKey,
                'target' => Currencies::USD,
            ],
        ]);

        if (!isset($data['success']) || !$data['success']) {
            throw new FailedProviderException($data['error']['info'] ?? 'Failed to parse Coin Layer response');
        }

        $responseDate = (new \DateTimeImmutable(timezone: new \DateTimeZone('UTC')))->setTimestamp($data['timestamp']);
        $rates = [];

        foreach ($data['rates'] as $code => $value) {
            $rates[$code] = new RateData(BcMath::round((string) $value, $this->currencyPrecision));
        }

        return new GetRatesResult($this, $this->getBaseCurrency(), $responseDate, $rates);
    }

    public function getAvailableCurrencies(): array
    {
        return [
            CryptoCurrencies::BTC,
            CryptoCurrencies::ETH,
            CryptoCurrencies::XRP,
            CryptoCurrencies::LTC,
            CryptoCurrencies::BCH,
            CryptoCurrencies::ADA,
            CryptoCurrencies::DOT,
            CryptoCurrencies::LINK,
            CryptoCurrencies::BNB,
            CryptoCurrencies::XLM,
            CryptoCurrencies::USDT,
            CryptoCurrencies::USDC,
            CryptoCurrencies::DOGE,
            CryptoCurrencies::UNI,
            CryptoCurrencies::EOS,
            CryptoCurrencies::TRX,
            CryptoCurrencies::NEO,
            CryptoCurrencies::IOTA,
            CryptoCurrencies::DASH,
            CryptoCurrencies::ETC,
            CryptoCurrencies::VEN,
            CryptoCurrencies::XEM,
            CryptoCurrencies::OKB,
            CryptoCurrencies::ATOM,
            CryptoCurrencies::XMR,
            CryptoCurrencies::CRO,
            CryptoCurrencies::ALGO,
            CryptoCurrencies::XTZ,
            CryptoCurrencies::AVAX,
            CryptoCurrencies::SOL,
            CryptoCurrencies::MATIC,
            CryptoCurrencies::SHIB,
            CryptoCurrencies::LUNA,
            CryptoCurrencies::HEX,
            CryptoCurrencies::FIL,
            CryptoCurrencies::VET,
            CryptoCurrencies::ICP,
            CryptoCurrencies::THETA,
            CryptoCurrencies::DAI,
            CryptoCurrencies::FTT,
            CryptoCurrencies::AXS,
            CryptoCurrencies::EGLD,
            CryptoCurrencies::MANA,
            CryptoCurrencies::NEAR,
            CryptoCurrencies::SAND,
            CryptoCurrencies::AAVE,
            CryptoCurrencies::CAKE,
            CryptoCurrencies::GRT,
            CryptoCurrencies::KLAY,
            CryptoCurrencies::HBAR,
            CryptoCurrencies::BSV,
            CryptoCurrencies::MIOTA,
            CryptoCurrencies::MKR,
            CryptoCurrencies::STX,
            CryptoCurrencies::FLOW,
            CryptoCurrencies::QNT,
            CryptoCurrencies::RUNE,
            CryptoCurrencies::ZEC,
            CryptoCurrencies::HNT,
            CryptoCurrencies::TFUEL,
            CryptoCurrencies::ONE,
            CryptoCurrencies::ENJ,
            CryptoCurrencies::HOT,
            CryptoCurrencies::SUSHI,
            CryptoCurrencies::CELO,
            CryptoCurrencies::CHZ,
            CryptoCurrencies::COMP,
            CryptoCurrencies::SNX,
            CryptoCurrencies::YFI,
            CryptoCurrencies::ZIL,
            CryptoCurrencies::QTUM,
            CryptoCurrencies::BAT,
            CryptoCurrencies::BTG,
            CryptoCurrencies::DCR,
            CryptoCurrencies::RVN,
            CryptoCurrencies::WAVES,
            CryptoCurrencies::ICX,
            CryptoCurrencies::ONT,
            CryptoCurrencies::ZRX,
            CryptoCurrencies::OMG,
            CryptoCurrencies::ANKR,
            CryptoCurrencies::ZEN,
            CryptoCurrencies::IOST,
            CryptoCurrencies::SC,
            CryptoCurrencies::DGB,
            CryptoCurrencies::XVG,
            CryptoCurrencies::BTT,
            CryptoCurrencies::NANO,
            CryptoCurrencies::LSK,
        ];
    }

    public function getRequestLimit(): int
    {
        return 100;
    }

    public function getRequestLimitPeriod(): int
    {
        return 86400;
    }

    /**
     * @return GetRatesResult[]
     */
    public function getRatesByRangeDate(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        throw new \App\Exception\NotAvailableMethod();
    }
}
