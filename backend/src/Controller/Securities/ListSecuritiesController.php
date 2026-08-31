<?php

namespace App\Controller\Securities;

use App\Service\ApiResponseFactory;
use App\Service\SecurityResponseBuilder;
use App\Service\SecurityService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/securities')]
#[OA\Tag(name: 'Securities')]
final class ListSecuritiesController extends AbstractController
{
    #[Route('', name: 'app_security_list', methods: ['GET'])]
    #[OA\Get(
        path: '/securities',
        summary: 'Lister les sécurisations',
        description: 'Retourne la liste paginée des sécurisations avec filtres et tri.'
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 1),
        description: 'Numéro de la page'
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 20),
        description: "Nombre d'éléments par page (max 100)"
    )]
    #[OA\Parameter(
        name: 'search',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string'),
        description: 'Recherche par mode de sécurisation ou nom de bien'
    )]
    #[OA\Parameter(
        name: 'security_mode', // ✅ Changé
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string'),
        description: 'Filtrer par mode de sécurisation (texte)'
    )]
    #[OA\Parameter(
        name: 'asset_id',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer'),
        description: 'Filtrer par ID de bien'
    )]
    #[OA\Parameter(
        name: 'order_dir',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['ASC', 'DESC']),
        description: 'Direction du tri',
        example: 'DESC'
    )]
    #[OA\Parameter(
        name: 'is_delete',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['false', 'true', 'all'], default: 'false'),
        description: 'false (défaut) = sécurisations actives uniquement. true = sécurisations supprimées (corbeille) uniquement. all = toutes.'
    )]

    #[OA\Response(
        response: 200,
        description: 'Liste des sécurisations',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Sécurisations récupérées avec succès.',
                'data' => [
                    'items' => [
                        [
                            'id' => 1,
                            "securityMode" => "Physique", 
                            'dateSecurisation' => '2026-08-11',
                            'location' => [
                                'latitude' => 48.8566,
                                'longitude' => 2.3522,
                            ],
                            'assetsCount' => 3,
                            'createdAt' => '2026-08-11 10:00:00'
                        ]
                    ],
                    'total' => 1,
                    'page' => 1,
                    'limit' => 20,
                    'pages' => 1,
                    // 'filters' => [
                    //     'search' => null,
                    //     'security_mode_id' => null,
                    //     'asset_id' => null,
                    //     'date_from' => null,
                    //     'date_to' => null,
                    //     'order_by' => 'createdAt',
                    //     'order_dir' => 'DESC'
                    // ]
                ]
            ]
        )
    )]

    public function __invoke(
        Request $request,
        SecurityService $securityService,
        SecurityResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(100, (int) $request->query->get('limit', 20)));

        $search = $request->query->get('search');
        $securityMode = $request->query->get('security_mode'); // ✅ Filtre par texte
        $assetId = $request->query->get('asset_id') ? (int) $request->query->get('asset_id') : null;
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');

        $orderBy = $request->query->get('order_by', 'createdAt');
        $orderDir = strtoupper($request->query->get('order_dir', 'DESC'));
        $isDelete = $request->query->get('is_delete', 'false');

        $allowedOrderBy = ['createdAt', 'dateSecurisation', 'id', 'securityMode'];
        if (!in_array($orderBy, $allowedOrderBy)) {
            return $apiResponse->error(
                "Le champ de tri '{$orderBy}' n'est pas autorisé. Utilisez : " . implode(', ', $allowedOrderBy),
                Response::HTTP_BAD_REQUEST
            );
        }

        $allowedOrderDir = ['ASC', 'DESC'];
        if (!in_array($orderDir, $allowedOrderDir)) {
            return $apiResponse->error(
                "La direction de tri '{$orderDir}' n'est pas autorisée. Utilisez : ASC ou DESC",
                Response::HTTP_BAD_REQUEST
            );
        }

        $result = $securityService->list(
            $page,
            $limit,
            $securityMode, // ✅ Passage du filtre texte
            $dateFrom,
            $dateTo,
            $assetId,
            $search,
            $orderBy,
            $orderDir,
            $isDelete
        );

        return $apiResponse->success([
            'items' => $responseBuilder->buildList($result['items']),
            'total' => $result['total'],
            'page' => $result['page'],
            'limit' => $result['limit'],
            'pages' => $result['pages'],
        ], Response::HTTP_OK, 'Sécurisations récupérées avec succès.');
    }
}