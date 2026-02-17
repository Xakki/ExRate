<?php

declare(strict_types=1);

namespace App\Provider;

use App\Contract\ProviderInterface;
use App\DTO\GetRatesResult;
use App\Enum\ProviderEnum;
use App\Exception\DisabledProviderException;
use App\Exception\FailedProviderException;
use App\Exception\LimitException;
use App\Util\BcMath;
use App\Util\RequestTrait;
use App\Util\UrlTemplateTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://www.banxico.org.mx/SieAPIRest/service/v1/doc/catalogoSeries
 * TODO: Доступна реализация метода getRatesByRangeDate
 */
final readonly class BanxicoProvider implements ProviderInterface
{
    use UrlTemplateTrait;
    use RequestTrait;
    private const array SERIES_MAP = [
        'USD' => 'SF43718',
        'EUR' => 'SF46410',
        'JPY' => 'SF46406',
        'GBP' => 'SF46407',
        'CAD' => 'SF60632',
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $url,
        private string $token,
        private int $id,
        private int $currencyPrecision,
    ) {
        if (empty($this->token)) {
            throw new DisabledProviderException('Provider disabled: Need token');
        }
    }

    public static function getServiceName(): string
    {
        return 'provider.banxico';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::BANXICO;
    }

    public function getBaseCurrency(): string
    {
        return 'MXN';
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
        return 'Banco de México (Banxico) provides official economic indicators and exchange rates for Mexico.';
    }

    public function getDaysLag(): int
    {
        return 0;
    }

    public function isActive(): bool
    {
        return true;
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

    public function getRequestDelay(): int
    {
        return 2;
    }

    public function getRatesByDate(\DateTimeImmutable $date): GetRatesResult
    {
        $currencies = implode(',', array_values(self::SERIES_MAP));
        $url = $this->prepareUrl($this->url, $date, $this->getBaseCurrency(), $currencies);

        $data = $this->jsonRequest($url, [
            'Bmx-Token' => $this->token,
        ]);

        if (isset($data['error'])) {
            if (isset($data['error']['secondsToReset'])) {
                throw new LimitException($data['error']['secondsToReset']);
            }
            throw new FailedProviderException(json_encode($data) ?: 'Unknown error');
        }

        $rates = [];
        if (isset($data['bmx']['series'])) {
            $idToCurrency = array_flip(self::SERIES_MAP);
            foreach ($data['bmx']['series'] as $series) {
                $id = $series['idSerie'];
                if (isset($idToCurrency[$id]) && !empty($series['datos'])) {
                    $currency = $idToCurrency[$id];
                    $value = $series['datos'][0]['dato'];
                    if (is_numeric($value)) {
                        $rates[$currency] = BcMath::round($value, $this->currencyPrecision);
                    }

                    // TODO logs notice
                }
                // TODO logs notice
            }
        }

        return new GetRatesResult($this->getId(), $this->getBaseCurrency(), $date, $rates);
    }

    /**
     * @return GetRatesResult[]
     */
    public function getRatesByRangeDate(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        throw new \App\Exception\NotAvailableMethod();
    }
}
