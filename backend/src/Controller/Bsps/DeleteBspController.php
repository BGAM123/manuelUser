<?php

namespace App\Controller\Bsps;

use App\Entity\Bsp;
use App\Exception\ResourceInUseException;
use App\Service\ApiResponseFactory;
use App\Service\BspService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/bsps')]
#[OA\Tag(name: 'BSP')]
final class DeleteBspController extends AbstractController
{
    #[Route('/{id}', name: 'app_bsp_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/bsps/{id}',
        summary: 'Supprimer un BSP',
        description: 'Par défaut (soft delete) : la sortie (AssetExit) parente n\'est pas affectée, et si le bien est de type stock/consomptible, sa quantité servie est restituée au stock. Avec force=true, supprime définitivement la ligne en base (la restauration de stock est aussi appliquée si elle n\'avait pas déjà eu lieu).'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Parameter(
        name: 'force',
        in: 'query',
        schema: new OA\Schema(type: 'boolean', default: false),
        description: 'true = suppression définitive (hard delete) au lieu de la suppression logique par défaut.'
    )]
    #[OA\Response(response: 200, description: 'BSP supprimé', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'BSP supprimé avec succès.', 'data' => null]))]
    #[OA\Response(response: 400, description: 'Bad Request - Suppression définitive impossible car la ressource est liée à d\'autres données')]
    #[OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'BSP introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'BSP introuvable.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        Bsp $bsp,
        Request $request,
        BspService $bspService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $force = filter_var($request->query->get('force', false), FILTER_VALIDATE_BOOLEAN);

        if (!$force && $bsp->isDelete()) {
            return $apiResponse->error('Ce BSP est déjà supprimé.', Response::HTTP_NOT_FOUND);
        }

        if ($force) {
            try {
                $bspService->deleteForced($bsp);
            } catch (ResourceInUseException $e) {
                return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
            }

            return $apiResponse->success(null, Response::HTTP_OK, 'BSP supprimé définitivement avec succès.');
        }

        $bspService->delete($bsp);

        return $apiResponse->success(
            null,
            Response::HTTP_OK,
            'BSP supprimé avec succès.'
        );
    }
}
