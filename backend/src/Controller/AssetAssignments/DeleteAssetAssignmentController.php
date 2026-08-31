<?php

namespace App\Controller\AssetAssignments;

use App\Entity\AssetAssignment;
use App\Service\ApiResponseFactory;
use App\Service\AssetAssignmentService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/asset-assignments')]
#[OA\Tag(name: 'Asset Assignments')]
final class DeleteAssetAssignmentController extends AbstractController
{
    #[Route('/{id}', name: 'app_asset_assignment_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/asset-assignments/{id}',
        summary: 'Supprimer une affectation de bien',
        description: 'Effectue un soft delete de l\'affectation. L\'enregistrement reste en base mais est marqué comme supprimé.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Response(
        response: 200,
        description: 'Affectation supprimée',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Affectation supprimée avec succès.',
                'data' => null
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Affectation introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Affectation introuvable.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        AssetAssignment $assignment,
        AssetAssignmentService $assignmentService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($assignment->isDelete()) {
            return $apiResponse->error('Cette affectation est déjà supprimée.', Response::HTTP_CONFLICT);
        }

        $assignmentService->delete($assignment);

        return $apiResponse->success(
            null,
            Response::HTTP_OK,
            'Affectation supprimée avec succès.'
        );
    }
}
