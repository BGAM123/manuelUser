<?php

namespace App\Controller\Inputs;

use App\Exception\ResourceNotFoundException;
use App\Service\ApiResponseFactory;
use App\Service\InputService;
use App\Service\InputResponseBuilder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/inputs')]
#[OA\Tag(name: 'Inputs')]
final class GetInputController extends AbstractController
{
    #[Route('/{id}', name: 'app_input_get', methods: ['GET'])]
    #[OA\Get(
        path: '/inputs/{id}',
        summary: 'Détail d\'une valeur de champ',
        description: 'Retourne les détails d\'une valeur de champ (input).'
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
        description: 'ID de la valeur',
        example: 1
    )]
    #[OA\Response(
        response: 200,
        description: 'Valeur trouvée',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Valeur récupérée avec succès.',
                'data' => [
                    'id' => 1,
                    'valeur' => 'Plat',
                    'champ' => [
                        'id' => 1,
                        'nom' => 'Type de toit',
                    ],
                    'createdAt' => '2026-08-13 10:00:00',
                    'updatedAt' => '2026-08-13 10:00:00',
                ]
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Valeur introuvable',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 404,
                'message' => 'La valeur demandée n\'existe pas.',
                'data' => null
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Non authentifié',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 401,
                'message' => 'Authentification requise.',
                'data' => null
            ]
        )
    )]
    #[OA\Response(
        response: 500,
        description: 'Erreur serveur',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 500,
                'message' => 'Une erreur interne du serveur est survenue.',
                'data' => null
            ]
        )
    )]
    public function __invoke(
        int $id,
        InputService $inputService,
        InputResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        try {
            $input = $inputService->get($id);
        } catch (ResourceNotFoundException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_NOT_FOUND);
        }

        return $apiResponse->success(
            $responseBuilder->buildDetail($input),
            Response::HTTP_OK,
            'Valeur récupérée avec succès.'
        );
    }
}