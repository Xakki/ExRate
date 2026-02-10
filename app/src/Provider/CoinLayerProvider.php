<?php

declare(strict_types=1);

namespace App\Provider;

use App\Contract\ProviderInterface;
use App\DTO\GetRatesResult;
use App\Enum\ProviderEnum;
use App\Exception\DisabledProviderException;
use App\Util\BcMath;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://docs.apilayer.com/coinlayer/
 */
final readonly class CoinLayerProvider implements ProviderInterface
{
    public const string LATEST_URL = 'http://api.coinlayer.com/api/live';
    public const string HISTORICAL_URL = 'http://api.coinlayer.com/api/%s';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $accessKey,
        private int $id,
        private int $currencyPrecision,
    ) {
        if (empty($this->accessKey)) {
            throw new DisabledProviderException('Provider disabled: Need API key');
        }
    }

    public static function getServiceName(): string
    {
        return 'provider.coin_layer';
    }

    public function isActive(): bool
    {
        return !empty($this->accessKey);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::COIN_LAYER;
    }

    public function getBaseCurrency(): string
    {
        return 'USD';
    }

    public function getHomePage(): string
    {
        return 'https://coinlayer.com';
    }

    public function getDescription(): string
    {
        return 'Access real-time and historical crypto data with Coinlayer’s powerful Crypto Currency API. Built for speed, simplicity, and performance—20ms response time, easy integration, and extensive documentation.';
    }

    public function getRates(\DateTimeImmutable $date): GetRatesResult
    {
        $isToday = $date->format('Y-m-d') === (new \DateTimeImmutable())->format('Y-m-d');
        $url = $isToday ? self::LATEST_URL : sprintf(self::HISTORICAL_URL, $date->format('Y-m-d'));

        $response = $this->httpClient->request('GET', $url, [
            'query' => [
                'access_key' => $this->accessKey,
                'target' => 'USD',
            ],
        ]);

        $content = $response->getContent();
        $data = json_decode($content, true);

        if (!is_array($data) || !isset($data['success']) || !$data['success']) {
            throw new \RuntimeException($data['error']['info'] ?? 'Failed to parse Coin Layer response');
        }

        $responseDate = (new \DateTimeImmutable(timezone: new \DateTimeZone('UTC')))->setTimestamp($data['timestamp']);
        $rates = [];

        foreach ($data['rates'] as $code => $value) {
            $rates[$code] = BcMath::round((string) $value, $this->currencyPrecision);
        }

        return new GetRatesResult($this->getId(), $this->getBaseCurrency(), $responseDate, $rates);
    }

    public function getAvailableCurrencies(): array
    {
        return ['BTC', 'ETH', 'XRP', 'LTC', 'BCH', 'ADA', 'DOT', 'LINK', 'BNB', 'XLM', 'USDT', 'USDC', 'DOGE', 'UNI', 'EOS', 'TRX', 'NEO', 'IOTA', 'DASH', 'ETC', 'VEN', 'XEM', 'OKB', 'ATOM', 'XMR', 'CRO', 'ALGO', 'XTZ', 'AVAX', 'SOL', 'MATIC', 'SHIB', 'LUNA', 'HEX', 'FIL', 'VET', 'ICP', 'THETA', 'DAI', 'FTT', 'AXS', 'EGLD', 'MANA', 'NEAR', 'SAND', 'AAVE', 'CAKE', 'GRT', 'KLAY', 'HBAR', 'BSV', 'MIOTA', 'MKR', 'STX', 'FLOW', 'QNT', 'RUNE', 'ZEC', 'HNT', 'TFUEL', 'ONE', 'ENJ', 'HOT', 'SUSHI', 'CELO', 'CHZ', 'COMP', 'SNX', 'YFI', 'ZIL', 'QTUM', 'BAT', 'BTG', 'DCR', 'RVN', 'WAVES', 'ICX', 'ONT', 'ZRX', 'OMG', 'ANKR', 'ZEN', 'IOST', 'SC', 'DGB', 'XVG', 'BTT', 'NANO', 'LSK'];
    }

    public function getRequestLimit(): int
    {
        return 100;
    }

    public function getRequestLimitPeriod(): int
    {
        return 86400;
    }

    public function getRequestDelay(): int
    {
        return 2;
    }
}
