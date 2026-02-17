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
use App\Util\Currencies;
use App\Util\Date;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://fred.stlouisfed.org/docs/api/fred/
 */
final readonly class FredProvider extends AbstractProviderRate
{
    // Get from  https://api.stlouisfed.org/fred/release/series?release_id=17&api_key={apiKey}
    private const array SERIES_MAP = [
        Currencies::EUR => ['id' => 'DEXUSEU', 'invert' => true, 'observationStart' => '1999-01-04'],
        Currencies::JPY => ['id' => 'DEXJPUS', 'invert' => false, 'observationStart' => '1971-01-04'],
        Currencies::CNY => ['id' => 'DEXCHUS', 'invert' => false, 'observationStart' => '1981-01-02'],
        Currencies::GBP => ['id' => 'DEXUSUK', 'invert' => true, 'observationStart' => '1971-01-04'],
        Currencies::CAD => ['id' => 'DEXCAUS', 'invert' => false, 'observationStart' => '1971-01-04'],
        Currencies::AUD => ['id' => 'DEXUSAL', 'invert' => true, 'observationStart' => '1971-01-04'],
        Currencies::NZD => ['id' => 'DEXUSNZ', 'invert' => true, 'observationStart' => '1971-01-04'],
        Currencies::BRL => ['id' => 'DEXBZUS', 'invert' => false, 'observationStart' => '1995-01-02'],
        Currencies::MXN => ['id' => 'DEXMXUS', 'invert' => false, 'observationStart' => '1993-11-08'],
        Currencies::CHF => ['id' => 'DEXSZUS', 'invert' => false, 'observationStart' => '1971-01-04'],
        Currencies::INR => ['id' => 'DEXINUS', 'invert' => false, 'observationStart' => '1973-01-02'],
        Currencies::ZAR => ['id' => 'DEXSFUS', 'invert' => false, 'observationStart' => '1980-01-02'],
        Currencies::HKD => ['id' => 'DEXHKUS', 'invert' => false, 'observationStart' => '1981-01-02'],
        Currencies::KRW => ['id' => 'DEXKOUS', 'invert' => false, 'observationStart' => '1981-04-13'],
        Currencies::MYR => ['id' => 'DEXMAUS', 'invert' => false, 'observationStart' => '1971-01-04'],
        Currencies::NOK => ['id' => 'DEXNOUS', 'invert' => false, 'observationStart' => '1971-01-04'],
        Currencies::SGD => ['id' => 'DEXSIUS', 'invert' => false, 'observationStart' => '1981-01-02'],
        Currencies::THB => ['id' => 'DEXTHUS', 'invert' => false, 'observationStart' => '1981-01-02'],
        Currencies::DKK => ['id' => 'DEXDNUS', 'invert' => false, 'observationStart' => '1971-01-04'],
        Currencies::SEK => ['id' => 'DEXSDUS', 'invert' => false, 'observationStart' => '1971-01-04'],
        Currencies::LKR => ['id' => 'DEXSLUS', 'invert' => false, 'observationStart' => '1973-01-02'],
        Currencies::TWD => ['id' => 'DEXTAUS', 'invert' => false, 'observationStart' => '1983-10-03'],
        Currencies::VES => ['id' => 'DEXVZUS', 'invert' => false, 'observationStart' => '2000-01-03'],
    ];

    public function __construct(
        protected HttpClientInterface $httpClient,
        protected LoggerInterface $logger,
        protected int $id,
        private string $url,
        private int $currencyPrecision,
        private string $apiKey,
        private int $periodDays = 90,
    ) {
        if (empty($this->apiKey)) {
            throw new DisabledProviderException('Provider disabled: Need API key');
        }
    }

    public static function getServiceName(): string
    {
        return 'provider.fred';
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::FRED;
    }

    public function getBaseCurrency(): string
    {
        return Currencies::USD;
    }

    public function getHomePage(): string
    {
        return 'https://fred.stlouisfed.org';
    }

    public function getDescription(): string
    {
        return 'Federal Reserve Bank USA (FRED)';
    }

    public function getHistoryDaysLag(): int
    {
        return 11;
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

    public function getPeriodDays(): int
    {
        return $this->periodDays;
    }

    #[\Deprecated]
    public function getRatesByDate(\DateTimeImmutable $date): GetRatesResult
    {
        throw new \App\Exception\NotAvailableMethod();
    }

    /**
     * @return GetRatesResult[]
     */
    public function getRatesByRangeDate(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $dateStart = $start->format(Date::FORMAT);
        $dateEnd = $end->format(Date::FORMAT);
        $ratesByDate = [];
        $baseUrl = rtrim($this->url, '/').'/series/observations';

        foreach ($this->getAvailableCurrencies() as $currency) {
            if (!isset(self::SERIES_MAP[$currency])) {
                throw new \Error('FRED: missed currency for SERIES_MAP: '.$currency);
            }
            $config = self::SERIES_MAP[$currency];
            try {
                $observationStart = Date::createFromFormat(Date::FORMAT, $config['observationStart']);
                if ($observationStart->diff($start)->invert) {
                    $dateStart = $config['observationStart'];
                }
                $data = $this->jsonRequest($baseUrl, options: [
                    'query' => [
                        'series_id' => $config['id'],
                        'api_key' => $this->apiKey,
                        'file_type' => 'json',
                        'observation_start' => $dateStart,
                        'observation_end' => $dateEnd,
                    ],
                ]);
                usleep(500000);

                foreach ($data['observations'] as $row) {
                    if ('.' === $row['value'] || !is_numeric($row['value'])) {
                        continue;
                    }

                    if ($config['invert']) {
                        if (BcMath::comp($row['value'], '0', $this->currencyPrecision) > 0) {
                            $ratesByDate[$row['date']][$currency] = new RateData(BcMath::round($row['value'], $this->currencyPrecision));
                        } else {
                            throw new FailedProviderException('Wrong value: '.json_encode($row).'; currency: '.$currency);
                        }
                    } else {
                        $ratesByDate[$row['date']][$currency] = new RateData(BcMath::div(1, $row['value'], $this->currencyPrecision));
                    }
                }
            } catch (\Throwable $e) {
                $this->logger->error($e->getMessage(), ['exception' => $e->getTrace()]);
                continue;
            }
        }

        $res = [];
        foreach ($ratesByDate as $date => $rates) {
            $res[] = new GetRatesResult($this, $this->getBaseCurrency(), Date::createFromFormat(Date::FORMAT, $date), $rates);
        }

        return $res;
    }
}
