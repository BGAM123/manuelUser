<?php

namespace App\Controller\Assets;

use App\Entity\Asset;
use App\Entity\PieceJointe;
use App\Exception\ResourceNotFoundException;
use App\Service\ApiResponseFactory;
use App\Service\FileUploadService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/assets')]
#[OA\Tag(name: 'Assets')]
final class DeleteAssetPieceJointeController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FileUploadService $fileUploadService
    ) {
    }

    #[Route('/pieces-jointes/{id}', name: 'app_asset_piece_jointe_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/assets/pieces-jointes/{id}',
        summary: 'Supprimer une pièce jointe d\'un bien',
        description: 'Supprime le fichier physique, l\'enregistrement PieceJointe et la liaison avec le bien.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 21)]
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
    #[OA\Response(response: 404, description: 'Bien ou pièce jointe introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Bien introuvable.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        PieceJointe $pieceJointe,
        Request $request,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        try {
            // Delete the physical file only after the database operation succeeds.
            // This prevents stale attachments when a foreign-key constraint rejects deletion.
            $this->entityManager->remove($pieceJointe);
            $this->entityManager->flush();

            $this->fileUploadService->deleteFile($pieceJointe->getChemin());

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
