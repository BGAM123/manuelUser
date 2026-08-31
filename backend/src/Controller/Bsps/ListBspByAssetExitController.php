<?php

namespace App\Controller\Bsps;

use App\Entity\AssetExit;
use App\Repository\BspRepository;
use App\Service\ApiResponseFactory;
use App\Service\BspResponseBuilder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/asset-exits')]
#[OA\Tag(name: 'BSP')]
final class ListBspByAssetExitController extends AbstractController
{
    #[Route('/{id}/bsps', name: 'app_bsp_list_by_asset_exit', methods: ['GET'])]
    #[OA\Get(
        path: '/asset-exits/{id}/bsps',
        summary: 'Lister les BSP d\'une sortie de bien',
        description: 'Retourne tous les BSP actifs rattachés à une sortie (un par bénéficiaire).'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, description: 'Identifiant de l\'AssetExit', schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Parameter(name: 'service_id', in: 'query', required: false, description: 'Filtrer par service rattaché au BSP', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'BSP de la sortie récupérés avec succès.',
                'data' => [
                    ['id' => 1, 'numero' => 'BSP-2026-00001', 'quantiteServie' => 30],
                    ['id' => 2, 'numero' => 'BSP-2026-00002', 'quantiteServie' => 40],
                ]
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Sortie introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Sortie introuvable.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        AssetExit $assetExit,
        Request $request,
        BspRepository $bspRepository,
        BspResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($assetExit->isDelete()) {
            return $apiResponse->error('Cette sortie est supprimée.', Response::HTTP_NOT_FOUND);
        }

        $serviceId = $request->query->has('service_id') ? $request->query->getInt('service_id') : null;
        $bsps = $bspRepository->findActiveByAssetExitId($assetExit->getId(), $serviceId);

        return $apiResponse->success(
            $responseBuilder->buildList($bsps),
            Response::HTTP_OK,
            'BSP de la sortie récupérés avec succès.'
        );
    }
}
