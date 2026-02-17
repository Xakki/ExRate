<?php

declare(strict_types=1);

namespace App\Provider;

use App\DTO\GetRatesResult;
use App\DTO\RateData;
use App\Enum\ProviderEnum;
use App\Exception\DisabledProviderException;
use App\Exception\FailedProviderException;
use App\Exception\LimitException;
use App\Service\AbstractProviderRate;
use App\Util\BcMath;
use App\Util\Currencies;
use App\Util\Date;
use App\Util\UrlTemplateTrait;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://www.banxico.org.mx/SieAPIRest/service/v1/doc/catalogoSeries
 * TODO: Доступна реализация метода getRatesByRangeDate
 */
final readonly class BanxicoProvider extends AbstractProviderRate
{
    use UrlTemplateTrait;

    private const array SERIES_MAP = [
        Currencies::USD => 'SF43718',
        Currencies::EUR => 'SF46410',
        Currencies::JPY => 'SF46406',
        Currencies::GBP => 'SF46407',
        Currencies::CAD => 'SF60632',
    ];

    public function __construct(
        protected HttpClientInterface $httpClient,
        protected LoggerInterface $logger,
        protected int $id,
        private string $url,
        private int $currencyPrecision,
        private string $apiKey,
        private int $periodDays = 60,
    ) {
        if (empty($this->apiKey)) {
            throw new DisabledProviderException('Provider disabled: Need token');
        }
    }

    public function getPeriodDays(): int
    {
        return $this->periodDays;
    }

    public static function getServiceName(): string
    {
        return 'provider.banxico';
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::BANXICO;
    }

    public function getBaseCurrency(): string
    {
        return Currencies::MXN;
    }

    public function getAvailableCurrencies(): array
    {
        return array_keys(self::SERIES_MAP);
    }

    public function getHomePage(): string
    {
        return 'https://www.banxico.org.mx';
    }

    public function getDescription(): string
    {
        return 'Banco de México (Banxico)';
    }

    /**
     * Limit rate https://www.banxico.org.mx/SieAPIRest/service/v1/doc/limiteConsultas.
     */
    public function getRequestLimit(): int
    {
        return 10000;
    }

    public function getRequestLimitPeriod(): int
    {
        return 86500;
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
        $currencies = implode(',', array_values(self::SERIES_MAP));
        $url = $this->prepareUrl($this->url, date: $end, baseCurrency: $this->getBaseCurrency(), currencies: $currencies, dateStart: $start);

        $data = $this->jsonRequest($url, [
            'Bmx-Token' => $this->apiKey,
        ]);

        if (isset($data['error'])) {
            if (isset($data['error']['secondsToReset'])) {
                throw new LimitException($data['error']['secondsToReset']);
            }
            throw new FailedProviderException(json_encode($data) ?: 'Unknown error');
        }

        $ratesByDate = [];
        if (isset($data['bmx']['series'])) {
            $idToCurrency = array_flip(self::SERIES_MAP);
            foreach ($data['bmx']['series'] as $series) {
                $id = $series['idSerie'];
                if (isset($idToCurrency[$id]) && !empty($series['datos'])) {
                    $currency = $idToCurrency[$id];
                    foreach ($series['datos'] as $dato) {
                        $value = $dato['dato'];
                        $timestamp = Date::createFromFormat('d/m/Y', $dato['fecha'])
                            ->getTimestamp();
                        if (is_numeric($value)) {
                            $ratesByDate[$timestamp][$currency] = new RateData(BcMath::round($value, $this->currencyPrecision));
                        } else {
                            $ratesByDate[$timestamp][$currency] = new RateData('');
                        }
                    }
                }
            }
        }
        $results = [];
        foreach ($ratesByDate as $timestamp => $rates) {
            $results[] = new GetRatesResult($this, $this->getBaseCurrency(), \DateTimeImmutable::createFromTimestamp($timestamp), $rates);
        }

        return $results;
    }

    /**
     * @return array<string, string>
     */
    protected function getDnsResolveOptions(int $attempt = 0): array
    {
        if (1 === $attempt) {
            return ['www.banxico.org.mx' => '200.57.155.222'];
        } elseif (2 === $attempt) {
            return ['www.banxico.org.mx' => '170.70.115.76'];
        }

        return parent::getDnsResolveOptions($attempt);
    }
}
