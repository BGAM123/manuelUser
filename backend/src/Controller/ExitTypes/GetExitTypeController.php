<?php

namespace App\Controller\ExitTypes;

use App\Entity\ExitType;
use App\Service\ApiResponseFactory;
use App\Service\ExitTypeResponseBuilder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/exit-types')]
#[OA\Tag(name: 'Exit Types')]
final class GetExitTypeController extends AbstractController
{
    #[Route('/{id}', name: 'app_exit_type_get', methods: ['GET'])]
    #[OA\Get(
        path: '/exit-types/{id}',
        summary: 'Détail d\'un type de sortie',
        description: 'Retourne les détails d\'un type de sortie.'
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
        example: 1
    )]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Type de sortie récupéré avec succès.',
                'data' => [
                    'id' => 1,
                    'nom' => 'Réforme',
                    'code' => 'REFORME',
                    'description' => 'Bien réformé',
                    'isActive' => true,
                    'beneficiaire' => false,
                    'createdAt' => '2026-08-12 10:00:00',
                    'updatedAt' => '2026-08-12 10:00:00'
                ]
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    #[OA\Response(response: 404, description: 'Type de sortie introuvable')]
    #[OA\Response(response: 500, description: 'Erreur serveur')]
    public function __invoke(
        ExitType $exitType,
        ExitTypeResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($exitType->isDelete()) {
            return $apiResponse->error('Ce type de sortie est supprimé.', Response::HTTP_NOT_FOUND);
        }

        return $apiResponse->success(
            $responseBuilder->buildDetail($exitType),
            Response::HTTP_OK,
            'Type de sortie récupéré avec succès.'
        );
    }
}