<?php

namespace App\Controller\Bsps;

use App\Entity\Bsp;
use App\Service\ApiResponseFactory;
use App\Service\BspResponseBuilder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/bsps')]
#[OA\Tag(name: 'BSP')]
final class GetBspController extends AbstractController
{
    #[Route('/{id}', name: 'app_bsp_get', methods: ['GET'])]
    #[OA\Get(
        path: '/bsps/{id}',
        summary: 'Détail d\'un BSP',
        description: 'Retourne le BSP complet avec la sortie associée, le bien, le service, le bénéficiaire et les pièces jointes.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'Détail du BSP récupéré avec succès.', 'data' => ['id' => 1, 'numero' => 'BSP-2026-00001']]))]
    #[OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'BSP introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'BSP introuvable.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        Bsp $bsp,
        BspResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($bsp->isDelete()) {
            return $apiResponse->error('Ce BSP est supprimé.', Response::HTTP_NOT_FOUND);
        }

        return $apiResponse->success(
            $responseBuilder->buildDetail($bsp),
            Response::HTTP_OK,
            'Détail du BSP récupéré avec succès.'
        );
    }
}
