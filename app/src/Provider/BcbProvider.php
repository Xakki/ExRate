<?php

declare(strict_types=1);

namespace App\Provider;

use App\Contract\ProviderRateExtendInterface;
use App\DTO\GetRatesResult;
use App\DTO\RateExtendData;
use App\Enum\ProviderEnum;
use App\Exception\FailedProviderException;
use App\Service\AbstractProviderRate;
use App\Util\BcMath;
use App\Util\Currencies;
use App\Util\Date;
use App\Util\UrlTemplateTrait;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://dadosabertos.bcb.gov.br/dataset/dolar-americano-usd-todos-os-boletins-diarios
 * API doc  https://olinda.bcb.gov.br/olinda/servico/PTAX/versao/v1/swagger-ui3#/
 * Example  https://olinda.bcb.gov.br/olinda/servico/PTAX/versao/v1/odata/CotacaoMoedaPeriodo(moeda=@moeda,dataInicial=@dataInicial,dataFinalCotacao=@dataFinalCotacao)?@moeda=%27EUR%27&@dataInicial=%2701-03-2026%27&@dataFinalCotacao=%2702-19-2026%27&$format=json
 * Example  https://olinda.bcb.gov.br/olinda/servico/PTAX/versao/v1/odata/CotacaoDolarPeriodo(dataInicial=@dataInicial,dataFinalCotacao=@dataFinalCotacao)?@dataInicial=%2701-03-2026%27&@dataFinalCotacao=%2702-19-2026%27&$format=json
 */
final readonly class BcbProvider extends AbstractProviderRate implements ProviderRateExtendInterface
{
    use UrlTemplateTrait;

    public function __construct(
        protected HttpClientInterface $httpClient,
        protected LoggerInterface $logger,
        protected int $id,
        private string $url,
        private string $pathRange,
        private int $currencyPrecision,
    ) {
    }

    public static function getServiceName(): string
    {
        return 'provider.bcb';
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::BCB;
    }

    public function getBaseCurrency(): string
    {
        return Currencies::BRL;
    }

    public function getHomePage(): string
    {
        return 'https://www.bcb.gov.br';
    }

    public function getDescription(): string
    {
        return 'Central Bank of Brazil (BCB)';
    }

    public function getAvailableCurrencies(): array
    {
        return [Currencies::AUD, Currencies::CAD, Currencies::CHF, Currencies::DKK, Currencies::EUR, Currencies::GBP, Currencies::JPY, Currencies::NOK, Currencies::SEK, Currencies::USD];
    }

    public function getRequestDelay(): int
    {
        return 1;
    }

    public function getPeriodDays(): int
    {
        return 120;
    }

    /**
     * @return GetRatesResult[]
     */
    public function getRatesByRangeDate(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $byDate = [];
        $i = '%27';
        foreach ($this->getAvailableCurrencies() as $currency) {
            $url = $this->url.$this->prepareUrl($this->pathRange, date: $end, currencies: $i.$currency.$i, dateStart: $start, quota: $i);
            $data = $this->jsonRequest($url);
            usleep(500000);
            if (!empty($data['value'])) {
                // For $item['tipoBoletim']
                // Fechamento - Close
                // Intermediário - Intermediate
                // Abertura -  Opening

                foreach ($data['value'] as $r) {
                    $dateDay = explode(' ', $r['dataHoraCotacao'])[0];
                    if (empty($r['tipoBoletim'])) {
                        throw new FailedProviderException('Wrong response: missed tipoBoletim ['.$url.']');
                    }
                    if ('Fechamento' === $r['tipoBoletim']) {
                        $byDate[$dateDay][$currency]['close'] = $r['cotacaoVenda'];
                    } elseif ('Abertura' === $r['tipoBoletim']) {
                        $byDate[$dateDay][$currency]['open'] = $r['cotacaoVenda'];
                    }

                    if (!isset($byDate[$dateDay][$currency]['low']) || $byDate[$dateDay][$currency]['low'] > $r['cotacaoVenda']) {
                        $byDate[$dateDay][$currency]['low'] = $r['cotacaoVenda'];
                    }
                    if (!isset($byDate[$dateDay][$currency]['low']) || $byDate[$dateDay][$currency]['low'] > $r['cotacaoCompra']) {
                        $byDate[$dateDay][$currency]['low'] = $r['cotacaoCompra'];
                    }

                    if (!isset($byDate[$dateDay][$currency]['high']) || $byDate[$dateDay][$currency]['high'] < $r['cotacaoVenda']) {
                        $byDate[$dateDay][$currency]['high'] = $r['cotacaoVenda'];
                    }
                    if (!isset($byDate[$dateDay][$currency]['high']) || $byDate[$dateDay][$currency]['high'] < $r['cotacaoCompra']) {
                        $byDate[$dateDay][$currency]['high'] = $r['cotacaoCompra'];
                    }
                }
            }
        }

        $result = [];
        foreach ($byDate as $date => $rates) {
            foreach ($rates as &$rate) {
                $rate = new RateExtendData(
                    isset($rate['open']) ? BcMath::round((string) $rate['open'], $this->currencyPrecision) : '',
                    isset($rate['low']) ? BcMath::round((string) $rate['low'], $this->currencyPrecision) : '',
                    isset($rate['high']) ? BcMath::round((string) $rate['high'], $this->currencyPrecision) : '',
                    isset($rate['close']) ? BcMath::round((string) $rate['close'], $this->currencyPrecision) : '',
                    ''
                );
            }
            // @phpstan-ignore argument.type
            $result[] = new GetRatesResult($this, $this->getBaseCurrency(), Date::createFromFormat(Date::FORMAT, $date), $rates);
        }

        return $result;
    }

    #[\Deprecated]
    public function getRatesByDate(\DateTimeImmutable $date): GetRatesResult
    {
        throw new \App\Exception\NotAvailableMethod();
    }
}
