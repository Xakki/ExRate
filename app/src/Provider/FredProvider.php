<?php

declare(strict_types=1);

namespace App\Provider;

use App\Contract\ProviderInterface;
use App\DTO\GetRatesResult;
use App\Enum\ProviderEnum;
use App\Exception\DisabledProviderException;
use App\Util\BcMath;
use App\Util\RequestTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://fred.stlouisfed.org/docs/api/fred/
 */
final readonly class FredProvider implements ProviderInterface
{
    use RequestTrait;

    // Get from  https://api.stlouisfed.org/fred/release/series?release_id=17&api_key={apiKey}
    private const array SERIES_MAP = [
        'EUR' => ['id' => 'DEXUSEU', 'invert' => true, 'observationStart' => '1999-01-04'],
        'JPY' => ['id' => 'DEXJPUS', 'invert' => false, 'observationStart' => '1971-01-04'],
        'CNY' => ['id' => 'DEXCHUS', 'invert' => false, 'observationStart' => '1981-01-02'],
        'GBP' => ['id' => 'DEXUSUK', 'invert' => true, 'observationStart' => '1971-01-04'],
        'CAD' => ['id' => 'DEXCAUS', 'invert' => false, 'observationStart' => '1971-01-04'],
        'AUD' => ['id' => 'DEXUSAL', 'invert' => true, 'observationStart' => '1971-01-04'],
        'NZD' => ['id' => 'DEXUSNZ', 'invert' => true, 'observationStart' => '1971-01-04'],
        'BRL' => ['id' => 'DEXBZUS', 'invert' => false, 'observationStart' => '1995-01-02'],
        'MXN' => ['id' => 'DEXMXUS', 'invert' => false, 'observationStart' => '1993-11-08'],
        'CHF' => ['id' => 'DEXSZUS', 'invert' => false, 'observationStart' => '1971-01-04'],
        'INR' => ['id' => 'DEXINUS', 'invert' => false, 'observationStart' => '1973-01-02'],
        'ZAR' => ['id' => 'DEXSFUS', 'invert' => false, 'observationStart' => '1980-01-02'],
        'HKD' => ['id' => 'DEXHKUS', 'invert' => false, 'observationStart' => '1981-01-02'],
        'KRW' => ['id' => 'DEXKOUS', 'invert' => false, 'observationStart' => '1981-04-13'],
        'MYR' => ['id' => 'DEXMAUS', 'invert' => false, 'observationStart' => '1971-01-04'],
        'NOK' => ['id' => 'DEXNOUS', 'invert' => false, 'observationStart' => '1971-01-04'],
        'SGD' => ['id' => 'DEXSIUS', 'invert' => false, 'observationStart' => '1981-01-02'],
        'THB' => ['id' => 'DEXTHUS', 'invert' => false, 'observationStart' => '1981-01-02'],
        'DKK' => ['id' => 'DEXDNUS', 'invert' => false, 'observationStart' => '1971-01-04'],
        'SEK' => ['id' => 'DEXSDUS', 'invert' => false, 'observationStart' => '1971-01-04'],
        'LKR' => ['id' => 'DEXSLUS', 'invert' => false, 'observationStart' => '1973-01-02'],
        'TWD' => ['id' => 'DEXTAUS', 'invert' => false, 'observationStart' => '1983-10-03'],
        'VES' => ['id' => 'DEXVZUS', 'invert' => false, 'observationStart' => '2000-01-03'],
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $url,
        private string $apiKey,
        private int $id,
        private int $currencyPrecision,
    ) {
        if (empty($this->apiKey)) {
            throw new DisabledProviderException('Provider disabled: Need API key');
        }
    }

    public static function getServiceName(): string
    {
        return 'provider.fred';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::FRED;
    }

    public function getBaseCurrency(): string
    {
        return 'USD';
    }

    public function getHomePage(): string
    {
        return 'https://fred.stlouisfed.org';
    }

    public function getDescription(): string
    {
        return 'Federal Reserve Economic Data (FRED) is a database maintained by the Research division of the Federal Reserve Bank of St. Louis.';
    }

    public function getDaysLag(): int
    {
        return 11;
    }

    public function isActive(): bool
    {
        return true;
    }

    public function getAvailableCurrencies(): array
    {
        return array_keys(self::SERIES_MAP);
    }

    public function getRequestLimit(): int
    {
        return 120; // 120 requests per minute
    }

    public function getRequestLimitPeriod(): int
    {
        return 60;
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
        // TODO:  доступно получение данных за период
        throw new \App\Exception\NotAvailableMethod();
    }

    public function getRatesByDate(\DateTimeImmutable $date): GetRatesResult
    {
        $dateStr = $date->format('Y-m-d');
        $rates = [];
        $baseUrl = rtrim($this->url, '/').'/series/observations';

        foreach ($this->getAvailableCurrencies() as $currency) {
            if (!isset(self::SERIES_MAP[$currency])) {
                throw new \Error('FRED: missed currency for SERIES_MAP: '.$currency);
            }
            $config = self::SERIES_MAP[$currency];
            try {
                if ($config['observationStart'] > $dateStr) {
                    continue;
                }
                $data = $this->jsonRequest($baseUrl, options: [
                    'query' => [
                        'series_id' => $config['id'],
                        'api_key' => $this->apiKey,
                        'file_type' => 'json',
                        'observation_start' => $dateStr,
                        'observation_end' => $dateStr,
                    ],
                ]);

                if (!empty($data['observations'])) {
                    $value = $data['observations'][0]['value'];
                    if ('.' === $value || !is_numeric($value)) {
                        continue;
                    }

                    if ($config['invert']) {
                        if (BcMath::comp($value, '0', $this->currencyPrecision) > 0) {
                            $rates[$currency] = BcMath::div('1', $value, $this->currencyPrecision);
                        }
                    } else {
                        $rates[$currency] = BcMath::round($value, $this->currencyPrecision);
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return new GetRatesResult($this->getId(), $this->getBaseCurrency(), $date, $rates);
    }
}
