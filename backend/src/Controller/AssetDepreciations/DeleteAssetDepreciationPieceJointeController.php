<?php

namespace App\Controller\AssetDepreciations;

use App\Entity\AssetDepreciation;
use App\Entity\PieceJointe;
use App\Service\ApiResponseFactory;
use App\Service\AssetDepreciationService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/asset-depreciations')]
#[OA\Tag(name: 'Asset Depreciations')]
final class DeleteAssetDepreciationPieceJointeController extends AbstractController
{
    public function __construct(
        private readonly AssetDepreciationService $depreciationService
    ) {
    }

    #[Route('/{depreciationId}/pieces-jointes/{pieceJointeId}', name: 'app_asset_depreciation_piece_jointe_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/asset-depreciations/{depreciationId}/pieces-jointes/{pieceJointeId}',
        summary: 'Supprimer une pièce jointe d\'une dépréciation',
        description: 'Supprime le fichier physique, l\'enregistrement PieceJointe et la liaison avec la dépréciation.'
    )]
    #[OA\Parameter(name: 'depreciationId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 3)]
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
    #[OA\Response(response: 404, description: 'Dépréciation ou pièce jointe introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Dépréciation introuvable.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        int $depreciationId,
        int $pieceJointeId,
        Request $request,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        try {
            $entityManager = $this->depreciationService->getEntityManager();
            $depreciation = $entityManager->find(AssetDepreciation::class, $depreciationId);
            $pieceJointe = $entityManager->find(PieceJointe::class, $pieceJointeId);

            if (!$depreciation) {
                return $apiResponse->error('Dépréciation introuvable.', Response::HTTP_NOT_FOUND);
            }

            if (!$pieceJointe) {
                return $apiResponse->error('Pièce jointe introuvable.', Response::HTTP_NOT_FOUND);
            }

            if (!$depreciation->getPieceJointes()->contains($pieceJointe)) {
                return $apiResponse->error('Cette pièce jointe n\'est pas associée à cette dépréciation.', Response::HTTP_NOT_FOUND);
            }

            $this->depreciationService->deletePieceJointe($depreciation, $pieceJointe);

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
