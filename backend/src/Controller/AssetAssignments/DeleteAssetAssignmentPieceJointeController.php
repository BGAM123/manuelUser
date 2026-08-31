<?php

namespace App\Controller\AssetAssignments;

use App\Entity\AssetAssignment;
use App\Entity\PieceJointe;
use App\Service\ApiResponseFactory;
use App\Service\AssetAssignmentService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/asset-assignments')]
#[OA\Tag(name: 'Asset Assignments')]
final class DeleteAssetAssignmentPieceJointeController extends AbstractController
{
    public function __construct(
        private readonly AssetAssignmentService $assignmentService
    ) {
    }

    #[Route('/{assignmentId}/pieces-jointes/{pieceJointeId}', name: 'app_asset_assignment_piece_jointe_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/asset-assignments/{assignmentId}/pieces-jointes/{pieceJointeId}',
        summary: 'Supprimer une pièce jointe d\'une affectation',
        description: 'Supprime le fichier physique, l\'enregistrement PieceJointe et la liaison avec l\'affectation.'
    )]
    #[OA\Parameter(name: 'assignmentId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Parameter(name: 'pieceJointeId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 21)]
    #[OA\Response(
        response: 200,
        description: 'Pièce jointe supprimée',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Pièce jointe supprimée avec succès.',
                'data' => null
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Affectation ou pièce jointe introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Affectation introuvable.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        int $assignmentId,
        int $pieceJointeId,
        Request $request,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        try {
            // Charger l'affectation et la pièce jointe manuellement car nous avons besoin des IDs
            $entityManager = $this->assignmentService->getEntityManager();
            $assignment = $entityManager->find(AssetAssignment::class, $assignmentId);
            $pieceJointe = $entityManager->find(PieceJointe::class, $pieceJointeId);

            if (!$assignment) {
                return $apiResponse->error('Affectation introuvable.', Response::HTTP_NOT_FOUND);
            }

            if (!$pieceJointe) {
                return $apiResponse->error('Pièce jointe introuvable.', Response::HTTP_NOT_FOUND);
            }

            if ($assignment->isDelete()) {
                return $apiResponse->error('Cette affectation est supprimée.', Response::HTTP_CONFLICT);
            }

            if (!$assignment->getPieceJointes()->contains($pieceJointe)) {
                return $apiResponse->error('Cette pièce jointe n\'est pas associée à cette affectation.', Response::HTTP_NOT_FOUND);
            }

            $this->assignmentService->deletePieceJointe($assignment, $pieceJointe);

            return $apiResponse->success(
                null,
                Response::HTTP_OK,
                'Pièce jointe supprimée avec succès.'
            );
        } catch (\Exception $e) {
            return $apiResponse->error(
                'Une erreur est survenue lors de la suppression de la pièce jointe.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
