<?php

namespace App\Controller\ExitTypes;

use App\Entity\ExitType;
use App\Service\ApiResponseFactory;
use App\Service\ExitTypeService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/exit-types')]
#[OA\Tag(name: 'Exit Types')]
final class DeleteExitTypeController extends AbstractController
{
    #[Route('/{id}', name: 'app_exit_type_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/exit-types/{id}',
        summary: 'Supprimer un type de sortie',
        description: 'Supprime un type de sortie (soft delete).'
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
        description: 'Type de sortie supprimé',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Type de sortie supprimé avec succès.',
                'data' => null
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    #[OA\Response(response: 404, description: 'Type de sortie introuvable')]
    #[OA\Response(response: 500, description: 'Erreur serveur')]
    public function __invoke(
        ExitType $exitType,
        ExitTypeService $exitTypeService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($exitType->isDelete()) {
            return $apiResponse->error('Ce type de sortie est déjà supprimé.', Response::HTTP_NOT_FOUND);
        }

        $exitTypeService->delete($exitType);

        return $apiResponse->success(
            null,
            Response::HTTP_OK,
            'Type de sortie supprimé avec succès.'
        );
    }
}