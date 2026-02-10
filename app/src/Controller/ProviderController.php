<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\ProviderDTO;
use App\Service\ProviderRegistry;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class ProviderController extends AbstractController
{
    public function __construct(
        private readonly ProviderRegistry $providerRegistry,
    ) {
    }

    #[Route('/api/v1/providers', name: 'get_providers', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Returns the list of all available exchange rate providers',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: ProviderDTO::class))
        )
    )]
    public function getProviders(): JsonResponse
    {
        return $this->json($this->providerRegistry->getAll());
    }
}
