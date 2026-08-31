<?php

namespace App\Controller\SecurityModes;

use App\Service\ApiResponseFactory;
use App\Service\SecurityModeResponseBuilder;
use App\Service\SecurityModeService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/security-modes')]
#[OA\Tag(name: 'SecurityModes')]
final class ListSecurityModesController extends AbstractController
{
    #[Route('', name: 'app_security_mode_list', methods: ['GET'])]
    #[OA\Get(
        path: '/security-modes',
        summary: 'Lister les modes de sécurisation',
        description: 'Retourne la liste paginée des modes de sécurisation non supprimés avec possibilité de recherche.'
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
        description: 'Recherche par nom ou description (insensible à la casse)',
        example: 'physique'
    )]
    #[OA\Parameter(
        name: 'all',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'boolean', default: false),
        description: 'Récupérer tous les modes sans pagination'
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des modes de sécurisation',
        content: new OA\JsonContent(
            examples: [
                new OA\Examples(
                    example: 'paginated',
                    summary: 'Liste paginée',
                    value: [
                        'success' => true,
                        'status' => 200,
                        'message' => 'Modes de sécurisation récupérés avec succès.',
                        'data' => [
                            'items' => [
                                [
                                    'id' => 1,
                                    'nom' => 'Physique',
                                    'description' => 'Sécurisation par barrières physiques',
                                ],
                                [
                                    'id' => 2,
                                    'nom' => 'Électronique',
                                    'description' => 'Sécurisation par système électronique',
                                ],
                            ],
                            'total' => 2,
                            'page' => 1,
                            'limit' => 20,
                            'pages' => 1,
                            'search' => null,
                        ]
                    ]
                ),
                new OA\Examples(
                    example: 'all',
                    summary: 'Liste complète (sans pagination)',
                    value: [
                        'success' => true,
                        'status' => 200,
                        'message' => 'Modes de sécurisation récupérés avec succès.',
                        'data' => [
                            [
                                'id' => 1,
                                'nom' => 'Physique',
                                'description' => 'Sécurisation par barrières physiques',
                            ],
                            [
                                'id' => 2,
                                'nom' => 'Électronique',
                                'description' => 'Sécurisation par système électronique',
                            ],
                            [
                                'id' => 3,
                                'nom' => 'Biométrique',
                                'description' => 'Sécurisation par empreintes digitales',
                            ],
                        ]
                    ]
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Non authentifié'
    )]
    #[OA\Response(
        response: 500,
        description: 'Erreur serveur'
    )]
    public function __invoke(
        Request $request,
        SecurityModeService $securityModeService,
        SecurityModeResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $search = $request->query->get('search');
        $all = filter_var($request->query->get('all', false), FILTER_VALIDATE_BOOLEAN);

        // ✅ Si all=true, retourner tous les résultats (sans pagination)
        if ($all) {
            $modes = $securityModeService->listAll($search);
            return $apiResponse->success(
                $responseBuilder->buildList($modes),
                Response::HTTP_OK,
                'Modes de sécurisation récupérés avec succès.'
            );
        }

        // ✅ Pagination avec recherche
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(100, (int) $request->query->get('limit', 20)));

        $result = $securityModeService->list($page, $limit, $search);

        return $apiResponse->success([
            'items' => $responseBuilder->buildList($result['items']),
            'total' => $result['total'],
            'page' => $result['page'],
            'limit' => $result['limit'],
            'pages' => $result['pages'],
            'search' => $result['search'],
        ], Response::HTTP_OK, 'Modes de sécurisation récupérés avec succès.');
    }
}