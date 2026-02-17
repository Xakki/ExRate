<?php

declare(strict_types=1);

namespace App\Controller;

use App\Response\CryptoCurrencyResponse;
use App\Response\CurrencyResponse;
use App\Util\CryptoCurrencies;
use App\Util\Currencies;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class CurrencyController extends AbstractController
{
    #[Route('/api/currencies', name: 'get_currencies', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Returns the list of all supported fiat currencies',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: CurrencyResponse::class))
        )
    )]
    public function getCurrencies(): JsonResponse
    {
        $currencies = [];
        foreach (Currencies::$list as $code => $data) {
            $currencies[] = new CurrencyResponse(
                $code,
                $data[0],
                $data[1],
                $data[2]
            );
        }

        return $this->json($currencies);
    }

    #[Route('/api/crypto_currencies', name: 'get_crypto_currencies', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Returns the list of all supported cryptocurrencies',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: CryptoCurrencyResponse::class))
        )
    )]
    public function getCryptoCurrencies(): JsonResponse
    {
        $cryptoCurrencies = [];
        foreach (CryptoCurrencies::$list as $code => $data) {
            $cryptoCurrencies[] = new CryptoCurrencyResponse(
                $code,
                $data[0],
                $data[1]
            );
        }

        return $this->json($cryptoCurrencies);
    }
}
