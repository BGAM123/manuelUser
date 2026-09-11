<?php

namespace App\Controller\AssetReevaluations;

use App\Repository\AssetReevaluationRepository;
use App\Service\ApiResponseFactory;
use App\Service\AssetReevaluationResponseBuilder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/asset-reevaluations')]
#[OA\Tag(name: 'Asset Reevaluations')]
final class ListAssetReevaluationsController extends AbstractController
{
    #[Route('', name: 'app_asset_reevaluation_list', methods: ['GET'])]
    #[OA\Get(
        path: '/asset-reevaluations',
        summary: 'Liste des réévaluations de biens',
        description: 'Retourne la liste de toutes les réévaluations avec possibilité de filtrage et pagination.'
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', minimum: 1, default: 1),
        description: 'Numéro de la page'
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20),
        description: 'Nombre d\'éléments par page'
    )]
    #[OA\Parameter(
        name: 'asset_ids[]',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'integer')),
        description: 'Filtrer par IDs de bien (ex: asset_ids[]=1&asset_ids[]=2&asset_ids[]=3)',
        example: [1, 2, 3]
    )]
    #[OA\Parameter(
        name: 'service_ids[]',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'integer')),
        description: 'Filtrer par IDs de service (ex: service_ids[]=5&service_ids[]=6)',
        example: [5, 6]
    )]
    // #[OA\Parameter(
    //     name: 'date_from',
    //     in: 'query',
    //     required: false,
    //     schema: new OA\Schema(type: 'string', format: 'date'),
    //     description: 'Filtrer à partir d\'une date (YYYY-MM-DD)'
    // )]
    // #[OA\Parameter(
    //     name: 'date_to',
    //     in: 'query',
    //     required: false,
    //     schema: new OA\Schema(type: 'string', format: 'date'),
    //     description: 'Filtrer jusqu\'à une date (YYYY-MM-DD)'
    // )]
    // #[OA\Parameter(
    //     name: 'motif',
    //     in: 'query',
    //     required: false,
    //     schema: new OA\Schema(type: 'string'),
    //     description: 'Rechercher par motif (recherche partielle)'
    // )]
    // #[OA\Parameter(
    //     name: 'methode_evaluation',
    //     in: 'query',
    //     required: false,
    //     schema: new OA\Schema(type: 'string'),
    //     description: 'Filtrer par méthode d\'évaluation'
    // )]
    // #[OA\Parameter(
    //     name: 'sort_by',
    //     in: 'query',
    //     required: false,
    //     schema: new OA\Schema(type: 'string', enum: ['createdAt', 'dateReevaluation', 'nouvelleValeur', 'valeurActuelle']),
    //     description: 'Trier par champ'
    // )]
    #[OA\Parameter(
        name: 'sort_order',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'desc'),
        description: 'Ordre de tri'
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des réévaluations',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Liste des réévaluations.',
                'data' => [
                    'items' => [
                        [
                            'id' => 1,
                            'valeurActuelle' => '2000000.00',
                            'nouvelleValeur' => '2250000.00',
                            'methodeEvaluation' => 'Expertise',
                            'service' => ['id' => 6, 'nom' => 'Direction du Patrimoine'],
                            'dateReevaluation' => '2024-01-10',
                            'motif' => 'Réévaluation annuelle',
                            'observations' => null,
                            'piecesJointes' => [],
                            'createdAt' => '2026-08-01 11:00:00'
                        ]
                    ],
                    'pagination' => [
                        'page' => 1,
                        'limit' => 20,
                        'total' => 50,
                        'totalPages' => 3
                    ]
                ]
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    #[OA\Response(response: 500, description: 'Erreur serveur')]
    public function __invoke(
        Request $request,
        AssetReevaluationRepository $repository,
        AssetReevaluationResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        // Récupérer les paramètres de la requête
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        
        // Construire les critères de filtrage
        $criteria = [];
        
        // Gérer les IDs de bien (plusieurs possibles)
        $assetIds = $request->query->all('asset_ids');
        if (!empty($assetIds)) {
            $criteria['asset_ids'] = array_map('intval', $assetIds);
        } elseif ($request->query->has('asset_id') && $request->query->get('asset_id') !== '') {
            // Support de l'ancien format (single ID)
            $criteria['asset_id'] = (int) $request->query->get('asset_id');
        }
        
        // Gérer les IDs de service (plusieurs possibles)
        $serviceIds = $request->query->all('service_ids');
        if (!empty($serviceIds)) {
            $criteria['service_ids'] = array_map('intval', $serviceIds);
        } elseif ($request->query->has('service_id') && $request->query->get('service_id') !== '') {
            // Support de l'ancien format (single ID)
            $criteria['service_id'] = (int) $request->query->get('service_id');
        }
        
        // Autres filtres
        if ($request->query->has('date_from') && $request->query->get('date_from') !== '') {
            $criteria['date_from'] = $request->query->get('date_from');
        }
        
        if ($request->query->has('date_to') && $request->query->get('date_to') !== '') {
            $criteria['date_to'] = $request->query->get('date_to');
        }
        
        if ($request->query->has('motif') && $request->query->get('motif') !== '') {
            $criteria['motif'] = $request->query->get('motif');
        }
        
        if ($request->query->has('methode_evaluation') && $request->query->get('methode_evaluation') !== '') {
            $criteria['methode_evaluation'] = $request->query->get('methode_evaluation');
        }

        // Paramètres de tri
        $sortBy = $request->query->get('sort_by', 'createdAt');
        $sortOrder = $request->query->get('sort_order', 'desc');

        // Récupérer les données
        $result = $repository->findWithFilters(
            $criteria,
            $page,
            $limit,
            $sortBy,
            $sortOrder
        );

        // Formater la réponse
        $data = [
            'items' => $responseBuilder->buildList($result['items']),
            'pagination' => $result['pagination']
        ];

        return $apiResponse->success(
            $data,
            Response::HTTP_OK,
            'Liste des réévaluations.'
        );
    }
}