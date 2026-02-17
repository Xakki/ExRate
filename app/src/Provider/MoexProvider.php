<?php

declare(strict_types=1);

namespace App\Provider;

use App\Contract\ProviderRateExtendInterface;
use App\DTO\GetRatesResult;
use App\DTO\RateExtendData;
use App\Enum\ProviderEnum;
use App\Exception\BadDateException;
use App\Service\AbstractProviderRate;
use App\Util\BcMath;
use App\Util\Currencies;
use App\Util\Date;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://iss.moex.com/iss/reference/
 * List securities https://iss.moex.com/iss/securities.json
 * List engines https://iss.moex.com/iss/engines.json
 * List markets https://iss.moex.com/iss/engines/stock/markets.json
 */
final readonly class MoexProvider extends AbstractProviderRate implements ProviderRateExtendInterface
{
    private const array SECID_MAP = [
        'CNYRUB_TOM' => [Currencies::CNY, 1],
        'KZTRUB_TOM' => [Currencies::KZT, 100],
        'BYNRUB_TOM' => [Currencies::BYN, 1],
        'TRYRUB_TOM' => [Currencies::TRY, 1],
        'AMDRUB_TOM' => [Currencies::AMD, 100],
        'KGSRUB_TOM' => [Currencies::KGS, 100],
        'UZSRUB_TOM' => [Currencies::UZS, 10000],
        'GLDRUB_TOM' => [Currencies::XAU, 1],
        'SLVRUB_TOM' => [Currencies::XAG, 1],
        'PLTRUB_TOM' => [Currencies::XPT, 1],
        'PLDRUB_TOM' => [Currencies::XPD, 1],
        'USD000UTSTOM' => [Currencies::USD, 1],
        'EUR_RUB__TOM' => [Currencies::EUR, 1],
        'HKDRUB_TOM' => [Currencies::HKD, 1],
        'AEDRUB_TOM' => [Currencies::AED, 1],
        'AZNRUB_TOM' => [Currencies::AZN, 1],
        'TJSRUB_TOM' => [Currencies::TJS, 10],
        'ZARRUB_TOM' => [Currencies::ZAR, 10],
        'GBPRUB_TOM' => [Currencies::GBP, 1],
        'CHFRUB_TOM' => [Currencies::CHF, 1],
        'JPYRUB_TOM' => [Currencies::JPY, 100],
        'INR_RUB_TOM' => [Currencies::INR, 10],
        'KWD_RUB_TOM' => [Currencies::KWD, 1],
        'QAR_RUB_TOM' => [Currencies::QAR, 1],
        'SAR_RUB_TOM' => [Currencies::SAR, 1],
        'THB_RUB_TOM' => [Currencies::THB, 10],
        'VND_RUB_TOM' => [Currencies::VND, 1000],
        'GELRUB_TOM' => [Currencies::GEL, 1],
        'MDL_RUB_TOM' => [Currencies::MDL, 10],
        'RSDRUB_TOM' => [Currencies::RSD, 100],
        'EGPRUB_TOM' => [Currencies::EGP, 10],
        'IDR_RUB_TOM' => [Currencies::IDR, 1000],
        'MYRRUB_TOM' => [Currencies::MYR, 10],
    ];

    public function __construct(
        protected HttpClientInterface $httpClient,
        protected LoggerInterface $logger,
        protected int $id,
        private string $url,
        private int $currencyPrecision,
    ) {
    }

    public static function getServiceName(): string
    {
        return 'provider.moex';
    }

    public function getEnum(): ProviderEnum
    {
        return ProviderEnum::MOEX;
    }

    public function getBaseCurrency(): string
    {
        return Currencies::RUB;
    }

    public function getHomePage(): string
    {
        return 'https://www.moex.com';
    }

    public function getDescription(): string
    {
        return 'Moscow Exchange (MOEX)';
    }

    public function getAvailableCurrencies(): array
    {
        return array_values(array_map(fn ($item) => $item[0], self::SECID_MAP));
    }

    public function getRequestDelay(): int
    {
        return 1;
    }

    public function getPeriodDays(): int
    {
        // MAx allow 366
        return 120;
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
        if ($start > $end) {
            throw new BadDateException('Start date must be before end date');
        }

        // Limit range to 1 year to avoid huge requests
        if (Date::getDayDiff($start, $end) > 366) {
            throw new BadDateException('Date range too big. Max 365 days.');
        }

        $ratesByDate = [];
        foreach (self::SECID_MAP as $secid => [$currency, $faceValue]) {
            $startAt = 0;
            while (true) {
                $url = sprintf('%s/history/engines/currency/markets/selt/boards/CETS/securities/%s.json', rtrim($this->url, '/'), $secid);
                $data = $this->jsonRequest($url, options: [
                    'query' => [
                        'from' => $start->format(Date::FORMAT),
                        'till' => $end->format(Date::FORMAT),
                        'start' => $startAt,
                    ],
                ]);
                usleep(500000);

                if (!isset($data['history']) || empty($data['history']['data'])) {
                    break;
                }

                $columns = array_flip($data['history']['columns']);
                foreach ($data['history']['data'] as $row) {
                    $date = $row[$columns['TRADEDATE']];
                    $close = $row[$columns['CLOSE']];

                    if (null === $close || '' === (string) $close || 0 == $close) {
                        if ($row[$columns['VOLRUR']] > 0) {
                            $this->logger->warning('MOEX Provider: Zero CLOSE price with positive volume', [
                                'secid' => $secid,
                                'date' => $date,
                                'row' => $row,
                            ]);
                        }
                        continue;
                    }

                    $price = (string) $close;
                    $open = (string) ($row[$columns['OPEN']] ?? $price);
                    $low = (string) ($row[$columns['LOW']] ?? $price);
                    $high = (string) ($row[$columns['HIGH']] ?? $price);
                    $volume = (string) ($row[$columns['VOLRUR']] ?? 0);

                    if ($faceValue > 1) {
                        $price = BcMath::div($price, (string) $faceValue, 10);
                        $open = BcMath::div($open, (string) $faceValue, 10);
                        $low = BcMath::div($low, (string) $faceValue, 10);
                        $high = BcMath::div($high, (string) $faceValue, 10);
                    }

                    $ratesByDate[$date][$currency] = new RateExtendData(
                        BcMath::round($open, $this->currencyPrecision),
                        BcMath::round($low, $this->currencyPrecision),
                        BcMath::round($high, $this->currencyPrecision),
                        BcMath::round($price, $this->currencyPrecision),
                        BcMath::round($volume, $this->currencyPrecision),
                    );
                }

                $cursor = $data['history.cursor']['data'][0] ?? null;
                if ($cursor) {
                    $cursorColumns = array_flip($data['history.cursor']['columns']);
                    $total = $cursor[$cursorColumns['TOTAL']];
                    $pageSize = $cursor[$cursorColumns['PAGESIZE']];
                    $startAt += $pageSize;
                    if ($startAt >= $total) {
                        break;
                    }
                } else {
                    break;
                }
                usleep(100000); // 0.1s
            }
            usleep(200000); // 0.2s
        }

        $res = [];
        ksort($ratesByDate);
        foreach ($ratesByDate as $date => $rates) {
            $res[] = new GetRatesResult($this, $this->getBaseCurrency(), Date::createFromFormat(Date::FORMAT, $date), $rates);
        }

        return $res;
    }
}
