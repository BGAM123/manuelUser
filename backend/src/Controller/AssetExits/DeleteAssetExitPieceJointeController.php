<?php

namespace App\Controller\AssetExits;

use App\Entity\AssetExit;
use App\Entity\PieceJointe;
use App\Exception\ResourceNotFoundException;
use App\Service\ApiResponseFactory;
use App\Service\AssetExitService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/asset-exits')]
#[OA\Tag(name: 'Asset Exits')]
final class DeleteAssetExitPieceJointeController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AssetExitService $exitService
    ) {
    }

    #[Route('/{exitId}/attachments/{attachmentId}', name: 'app_asset_exit_piece_jointe_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/asset-exits/{exitId}/attachments/{attachmentId}',
        summary: 'Supprimer une pièce jointe d\'une sortie',
        description: 'Supprime le fichier physique, l\'enregistrement PieceJointe et la liaison avec la sortie.'
    )]
    #[OA\Parameter(name: 'exitId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Parameter(name: 'attachmentId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 21)]
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
    #[OA\Response(response: 404, description: 'Sortie ou pièce jointe introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Sortie introuvable.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        int $exitId,
        int $attachmentId,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $exit = $this->entityManager->getRepository(AssetExit::class)->find($exitId);
        if (!$exit || $exit->isDelete()) {
            return $apiResponse->error('Sortie introuvable.', Response::HTTP_NOT_FOUND);
        }

        $pieceJointe = $this->entityManager->getRepository(PieceJointe::class)->find($attachmentId);
        if (!$pieceJointe) {
            return $apiResponse->error('Pièce jointe introuvable.', Response::HTTP_NOT_FOUND);
        }

        if (!$exit->getPieceJointes()->contains($pieceJointe)) {
            return $apiResponse->error('Cette pièce jointe n\'est pas associée à cette sortie.', Response::HTTP_NOT_FOUND);
        }

        try {
            $this->exitService->deletePieceJointe($exit, $pieceJointe);
        } catch (\Exception $e) {
            return $apiResponse->error('Erreur lors de la suppression de la pièce jointe.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $apiResponse->success(
            null,
            Response::HTTP_OK,
            'Pièce jointe supprimée avec succès.'
        );
    }
}
