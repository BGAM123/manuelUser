<?php

namespace App\Controller\AssetReevaluations;

use App\Entity\AssetReevaluation;
use App\Entity\PieceJointe;
use App\Service\ApiResponseFactory;
use App\Service\AssetReevaluationService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/asset-reevaluations')]
#[OA\Tag(name: 'Asset Reevaluations')]
final class DeleteAssetReevaluationPieceJointeController extends AbstractController
{
    public function __construct(
        private readonly AssetReevaluationService $reevaluationService
    ) {
    }

    #[Route('/{reevaluationId}/pieces-jointes/{pieceJointeId}', name: 'app_asset_reevaluation_piece_jointe_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/asset-reevaluations/{reevaluationId}/pieces-jointes/{pieceJointeId}',
        summary: 'Supprimer une pièce jointe d\'une réévaluation',
        description: 'Supprime le fichier physique, l\'enregistrement PieceJointe et la liaison avec la réévaluation.'
    )]
    #[OA\Parameter(name: 'reevaluationId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Parameter(name: 'pieceJointeId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 5)]
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
    #[OA\Response(response: 404, description: 'Réévaluation ou pièce jointe introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Réévaluation introuvable.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        int $reevaluationId,
        int $pieceJointeId,
        Request $request,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        try {
            $entityManager = $this->reevaluationService->getEntityManager();
            $reevaluation = $entityManager->find(AssetReevaluation::class, $reevaluationId);
            $pieceJointe = $entityManager->find(PieceJointe::class, $pieceJointeId);

            if (!$reevaluation) {
                return $apiResponse->error('Réévaluation introuvable.', Response::HTTP_NOT_FOUND);
            }

            if (!$pieceJointe) {
                return $apiResponse->error('Pièce jointe introuvable.', Response::HTTP_NOT_FOUND);
            }

            if (!$reevaluation->getPieceJointes()->contains($pieceJointe)) {
                return $apiResponse->error('Cette pièce jointe n\'est pas associée à cette réévaluation.', Response::HTTP_NOT_FOUND);
            }

            $this->reevaluationService->deletePieceJointe($reevaluation, $pieceJointe);

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
