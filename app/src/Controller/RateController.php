<?php

namespace App\Controller;

use App\DTO\RateRequest;
use App\DTO\RateResponse;
use App\Service\ExchangeRateService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1')]
class RateController extends AbstractController
{
    public function __construct(private ExchangeRateService $service)
    {
    }

    #[Route('/rate', name: 'api_rate_get', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Returns the exchange rate and difference',
        content: new OA\JsonContent(ref: new Model(type: RateResponse::class))
    )]
    public function getRate(
        #[MapQueryString] RateRequest $request,
    ): JsonResponse {
        $data = $this->service->getRate(
            $request->getDateImmutable(),
            $request->currency,
            $request->baseCurrency
        );

        return $this->json(new RateResponse(
            $data['rate'],
            $data['diff'],
            $data['date']
        ));
    }
}
