<?php

namespace App\Controller\Securities;

use App\Exception\ResourceNotFoundException;
use App\Service\ApiResponseFactory;
use App\Service\SecurityResponseBuilder;
use App\Service\SecurityService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/securities')]
#[OA\Tag(name: 'Securities')]
final class GetSecurityController extends AbstractController
{
    #[Route('/{id}', name: 'app_security_get', methods: ['GET'])]
    #[OA\Get(
        path: '/securities/{id}',
        summary: 'Détail d\'une sécurisation',
        description: 'Retourne les détails d\'une sécurisation.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Response(
        response: 200,
        description: 'Sécurisation trouvée',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Sécurisation récupérée avec succès.',
                'data' => [
                    'id' => 1,
                    'securityMode' => ['id' => 1, 'nom' => 'Physiquement', 'description' => 'Sécurisation physique'],
                    'dateSecurisation' => '2026-08-11',
                    'latitude' => 48.8566,
                    'longitude' => 2.3522,
                    'assets' => [
                        ['id' => 1, 'reference' => 'REF001', 'nom' => 'Ordinateur portable', 'code' => 'PC001']
                    ],
                    'documents' => [],
                    'createdAt' => '2026-08-11 10:00:00',
                    'updatedAt' => '2026-08-11 10:00:00',
                ]
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Sécurisation introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'La sécurisation demandée n\'existe pas.', 'data' => null]))]
    public function __invoke(
        int $id,
        SecurityService $securityService,
        SecurityResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        try {
            $security = $securityService->get($id);
        } catch (ResourceNotFoundException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_NOT_FOUND);
        }

        return $apiResponse->success(
            $responseBuilder->buildDetail($security),
            Response::HTTP_OK,
            'Sécurisation récupérée avec succès.'
        );
    }
}
