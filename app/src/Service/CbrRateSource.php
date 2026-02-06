<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class CbrRateSource
{
    private const CBR_URL = 'https://cbr.ru/scripts/XML_daily.asp';

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    public function getRate(\DateTimeImmutable $date, string $currency): ?float
    {
        // CBR expects date in format dd/mm/yyyy
        $dateStr = $date->format('d/m/Y');

        $response = $this->httpClient->request('GET', self::CBR_URL, [
            'query' => [
                'date_req' => $dateStr,
            ],
        ]);

        $content = $response->getContent();

        // Simple XML parsing
        // The XML structure is like:
        // <ValCurs Date="04.02.2026" name="Foreign Currency Market">
        //   <Valute ID="R01235">
        //     <NumCode>840</NumCode>
        //     <CharCode>USD</CharCode>
        //     <Nominal>1</Nominal>
        //     <Name>Доллар США</Name>
        //     <Value>95,1234</Value>
        //   </Valute>
        // ...

        $xml = simplexml_load_string($content);

        if (false === $xml) {
            throw new \RuntimeException('Failed to parse CBR XML response');
        }

        foreach ($xml->Valute as $valute) {
            if ((string) $valute->CharCode === $currency) {
                // Value is like "95,1234", we need to replace comma with dot
                $valueStr = str_replace(',', '.', (string) $valute->Value);
                $nominal = (int) $valute->Nominal;

                return ((float) $valueStr) / $nominal;
            }
        }

        return null;
    }
}
