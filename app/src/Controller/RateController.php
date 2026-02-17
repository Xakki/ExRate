<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\RateRequest;
use App\DTO\RateResponse;
use App\DTO\TimeseriesRequest;
use App\DTO\TimeseriesResponse;
use App\Exception\DisabledProviderException;
use App\Exception\RateNotFoundException;
use App\Service\ProviderManager;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

class RateController extends AbstractController
{
    public function __construct(private readonly ProviderManager $providerManager)
    {
    }

    #[Route('/', name: 'landing_page', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('landing/index.html.twig');
    }

    #[Route('/api/v1/rate', name: 'api_rate_get', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Returns the exchange rate and difference',
        content: new OA\JsonContent(ref: new Model(type: RateResponse::class))
    )]
    #[OA\Response(
        response: 202,
        description: 'Rate does not exist yet. Request has been queued for fetching. Try again later.',
        headers: [new OA\Header(header: 'Retry-After', schema: new OA\Schema(type: 'integer', example: 5))],
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'rate', type: 'string', example: ''),
                new OA\Property(property: 'date', type: 'string', example: ''),
                new OA\Property(property: 'diff', type: 'string', example: ''),
                new OA\Property(property: 'date_diff', type: 'string', example: ''),
                new OA\Property(property: 'timestamp', type: 'string', example: '2026-02-14T12:00:00+00:00'),
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid request parameters or provider disabled',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Rate provider cbr not found'),
            ]
        )
    )]
    public function getRate(
        #[MapQueryString(validationFailedStatusCode: 400)] RateRequest $request,
        Request $httpRequest,
        #[Autowire(service: 'limiter.api_rate')] RateLimiterFactory $apiRateLimiter,
        #[Autowire(env: 'RATE_LIMIT_BYPASS_PARAM')] string $bypassParam,
    ): JsonResponse {
        if (!$httpRequest->query->has($bypassParam)) {
            $limiter = $apiRateLimiter->create($httpRequest->getClientIp());
            if (false === $limiter->consume(1)->isAccepted()) {
                throw new TooManyRequestsHttpException();
            }
        }

        $headerRetry = ['Retry-After' => '5'];
        try {
            $status = 200;
            $response = $this->providerManager->getRate(
                $request->getDateImmutable(),
                $request->currency,
                $request->baseCurrency,
                $request->provider
            );
            $headers = [];
            if (!$response->isFullData()) {
                $status = 202;
                $headers = $headerRetry;
            }

            return $this->json($response, $status, $headers);
        } catch (RateNotFoundException) {
            return $this->json(
                new RateResponse('', '', null, null),
                202,
                $headerRetry
            );
        } catch (DisabledProviderException $e) {
            return $this->json(
                ['error' => $e->getMessage()],
                400
            );
        }
    }

    #[Route('/api/v1/timeseries', name: 'api_timeseries_get', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Returns the exchange rates for a specified period',
        content: new OA\JsonContent(ref: new Model(type: TimeseriesResponse::class))
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid request parameters',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Invalid date range'),
            ]
        )
    )]
    public function getTimeseries(
        #[MapQueryString(validationFailedStatusCode: 400)] TimeseriesRequest $request,
        Request $httpRequest,
        #[Autowire(service: 'limiter.api_timeseries')] RateLimiterFactory $apiTimeseriesLimiter,
        #[Autowire(env: 'RATE_LIMIT_BYPASS_PARAM')] string $bypassParam,
    ): JsonResponse {
        if (!$httpRequest->query->has($bypassParam)) {
            $limiter = $apiTimeseriesLimiter->create($httpRequest->getClientIp());
            if (false === $limiter->consume(1)->isAccepted()) {
                throw new TooManyRequestsHttpException();
            }
        }

        $response = $this->providerManager->getTimeseries(
            $request->getStartDateImmutable(),
            $request->getEndDateImmutable(),
            $request->currency,
            $request->baseCurrency,
            $request->provider
        );

        return $this->json($response);
    }
}
