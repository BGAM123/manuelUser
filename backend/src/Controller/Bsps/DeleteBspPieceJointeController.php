<?php

namespace App\Controller\Bsps;

use App\Entity\Bsp;
use App\Entity\PieceJointe;
use App\Service\ApiResponseFactory;
use App\Service\BspService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/bsps')]
#[OA\Tag(name: 'BSP')]
final class DeleteBspPieceJointeController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BspService $bspService
    ) {
    }

    #[Route('/{bspId}/attachments/{attachmentId}', name: 'app_bsp_piece_jointe_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/bsps/{bspId}/attachments/{attachmentId}',
        summary: 'Supprimer une pièce jointe d\'un BSP',
        description: 'Supprime le fichier physique, l\'enregistrement PieceJointe et la liaison avec le BSP.'
    )]
    #[OA\Parameter(name: 'bspId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Parameter(name: 'attachmentId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 21)]
    #[OA\Response(response: 200, description: 'Pièce jointe supprimée', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Pièce jointe supprimée avec succès.', 'data' => null]))]
    #[OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'BSP ou pièce jointe introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'BSP introuvable.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        int $bspId,
        int $attachmentId,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $bsp = $this->entityManager->getRepository(Bsp::class)->find($bspId);
        if (!$bsp || $bsp->isDelete()) {
            return $apiResponse->error('BSP introuvable.', Response::HTTP_NOT_FOUND);
        }

        $pieceJointe = $this->entityManager->getRepository(PieceJointe::class)->find($attachmentId);
        if (!$pieceJointe) {
            return $apiResponse->error('Pièce jointe introuvable.', Response::HTTP_NOT_FOUND);
        }

        if (!$bsp->getPieceJointes()->contains($pieceJointe)) {
            return $apiResponse->error('Cette pièce jointe n\'est pas associée à ce BSP.', Response::HTTP_NOT_FOUND);
        }

        try {
            $this->bspService->deletePieceJointe($bsp, $pieceJointe);
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
