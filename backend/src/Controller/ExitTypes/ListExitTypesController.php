<?php

namespace App\Controller\ExitTypes;

use App\Repository\ExitTypeRepository;
use App\Service\ApiResponseFactory;
use App\Service\ExitTypeResponseBuilder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/exit-types')]
#[OA\Tag(name: 'Exit Types')]
final class ListExitTypesController extends AbstractController
{
    #[Route('', name: 'app_exit_type_list', methods: ['GET'])]
    #[OA\Get(
        path: '/exit-types',
        summary: 'Liste des types de sortie',
        description: 'Retourne la liste paginée de tous les types de sortie avec possibilité de recherche et filtrage.'
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
        schema: new OA\Schema(type: 'integer', default: 10),
        description: 'Nombre d\'éléments par page'
    )]
    #[OA\Parameter(
        name: 'include_inactive',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'boolean'),
        description: 'Inclure les types inactifs'
    )]
    #[OA\Parameter(
        name: 'search',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string'),
        description: 'Recherche par nom ou code (ex: "reforme" ou "REF")'
    )]
    #[OA\Parameter(
        name: 'order_by',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['nom', 'code', 'createdAt']),
        description: 'Champ de tri'
    )]
    #[OA\Parameter(
        name: 'order_dir',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['ASC', 'DESC']),
        description: 'Direction du tri'
    )]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Liste des types de sortie récupérée avec succès.',
                'data' => [
                    'items' => [
                        [
                            'id' => 1,
                            'nom' => 'Réforme',
                            'code' => 'REFORME',
                            'description' => 'Bien réformé',
                            'isActive' => true,
                            'beneficiaire' => false,
                            'createdAt' => '2026-08-12 10:00:00',
                            'updatedAt' => '2026-08-12 10:00:00'
                        ],
                        [
                            'id' => 2,
                            'nom' => 'Don',
                            'code' => 'DON',
                            'description' => 'Donation du bien',
                            'isActive' => true,
                            'beneficiaire' => true,
                            'createdAt' => '2026-08-12 10:00:00',
                            'updatedAt' => '2026-08-12 10:00:00'
                        ]
                    ],
                    'pagination' => [
                        'page' => 1,
                        'limit' => 10,
                        'total' => 2,
                        'pages' => 1
                    ],
                    'filters' => [
                        'search' => null,
                        'include_inactive' => false,
                        'order_by' => 'nom',
                        'order_dir' => 'ASC'
                    ]
                ]
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    #[OA\Response(response: 500, description: 'Erreur serveur')]
    public function __invoke(
        Request $request,
        ExitTypeRepository $exitTypeRepository,
        ExitTypeResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        // Récupérer les paramètres de pagination
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(100, (int) $request->query->get('limit', 10)));
        $includeInactive = filter_var($request->query->get('include_inactive', false), FILTER_VALIDATE_BOOLEAN);
        $search = $request->query->get('search');
        $orderBy = $request->query->get('order_by', 'nom');
        $orderDir = $request->query->get('order_dir', 'ASC');

        // Valider les paramètres de tri
        $allowedOrderBy = ['nom', 'code', 'createdAt', 'updatedAt'];
        if (!in_array($orderBy, $allowedOrderBy)) {
            $orderBy = 'nom';
        }

        $allowedOrderDir = ['ASC', 'DESC'];
        if (!in_array(strtoupper($orderDir), $allowedOrderDir)) {
            $orderDir = 'ASC';
        }

        // Récupérer les données paginées
        $result = $exitTypeRepository->findPaginated(
            $page, 
            $limit, 
            $includeInactive, 
            $search,
            $orderBy,
            $orderDir
        );

        // Construire la réponse
        $data = [
            'items' => $responseBuilder->buildList($result['items']),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $result['total'],
                'pages' => (int) ceil($result['total'] / $limit)
            ],
            'filters' => [
                'search' => $search,
                'include_inactive' => $includeInactive,
                'order_by' => $orderBy,
                'order_dir' => $orderDir
            ]
        ];

        return $apiResponse->success(
            $data,
            Response::HTTP_OK,
            'Liste des types de sortie récupérée avec succès.'
        );
    }
}