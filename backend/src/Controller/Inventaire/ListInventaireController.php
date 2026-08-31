<?php

namespace App\Controller\Inventaire;

use App\Service\ApiResponseFactory;
use App\Service\InventaireService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/inventaire', name: 'app_inventaire_list', methods: ['GET'])]
#[OA\Tag(name: 'Inventaire')]
final class ListInventaireController extends AbstractController
{
    #[OA\Get(
        path: '/inventaire',
        summary: 'Générer l\'inventaire de tous les biens',
        description: 'Retourne l\'inventaire paginé de tous les biens non supprimés, regroupés par catégorie.'
    )]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10))]
    #[OA\Parameter(
        name: 'categories',
        in: 'query',
        description: 'Filtre par catégories: une ID (ex: 5), plusieurs IDs séparées par des virgules (ex: 5,7,10), ou "all" pour toutes les catégories. Par défaut, toutes les catégories.',
        schema: new OA\Schema(type: 'string', example: '5')
    )]
    #[OA\Parameter(
        name: 'statut',
        in: 'query',
        description: 'Filtrer par statut du bien. Valeurs possibles: ACTIF, SORTIS, etc.',
        schema: new OA\Schema(type: 'string', example: 'ACTIF')
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Inventaire récupéré avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Inventaire récupéré avec succès.'),
                new OA\Property(property: 'data', type: 'object')
            ],
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Inventaire récupéré avec succès.',
                'data' => [
                    'date' => '2026-08-10',
                    'service' => [
                        'id' => 10,
                        'nom' => 'Service du patrimoine'
                    ],
                    'region' => [
                        'id' => 1,
                        'nom' => 'Centre'
                    ],
                    'departement' => [
                        'id' => 5,
                        'nom' => 'Mfoundi'
                    ],
                    'arrondissement' => [
                        'id' => 2,
                        'nom' => 'Yaoundé II'
                    ],
                    'categories' => [
                        [
                            'categorie' => [
                                'id' => 1,
                                'nom' => 'Matériel informatique'
                            ],
                            'biens' => [
                                [
                                    'id' => 25,
                                    'nom' => 'Ordinateur Dell',
                                    'type' => [
                                        'id' => 2,
                                        'nom' => 'Ordinateur portable'
                                    ],
                                    'valeur' => 750000,
                                    'annee_acquisition' => 2025,
                                    'etat' => 'Bon',
                                    'description' => 'Ordinateur portable utilisé par le service.',
                                    'imputation_budgetaire' => 750000,
                                    'projet' => [
                                        'id' => 5,
                                        'nom' => 'Projet de modernisation',
                                        'date_debut' => '2025-01-01',
                                        'date_fin' => '2027-12-31',
                                        'duree' => '3 ans'
                                    ],
                                    'detenteur' => [
                                        'id' => 15,
                                        'nom' => 'NOM',
                                        'prenom' => 'PRENOM',
                                        'cni' => '123456789',
                                        'matricule' => 'MAT-001',
                                        'service' => [
                                            'id' => 10,
                                            'nom' => 'Service du patrimoine'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'meta' => [
                        'current_page' => 1,
                        'limit' => 10,
                        'total_items' => 25,
                        'total_pages' => 3,
                        'filters' => [
                            'statut' => 'ACTIF'
                        ]
                    ]
                ]
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request - Utilisateur sans service',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'L\'utilisateur connecté n\'est associé à aucun service.', 'data' => null])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized - Non authentifié',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Non authentifié.', 'data' => null])
    )]
    public function __invoke(
        Request $request,
        InventaireService $inventaireService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        // Récupérer l'utilisateur connecté
        $user = $this->getUser();
        if (!$user) {
            return $apiResponse->error('Non authentifié.', Response::HTTP_UNAUTHORIZED);
        }

        try {
            // Parser les paramètres de pagination
            $page = max(1, $request->query->getInt('page', 1));
            $limit = $request->query->getInt('limit', 10);
            $limit = $limit < 1 ? 10 : ($limit > 200 ? 200 : $limit);

            // Parser les paramètres de catégories
            $categoriesParam = $request->query->get('categories');
            $categoryIds = null;

            if ($categoriesParam && 'all' !== $categoriesParam) {
                // Peut être une seule ID ou plusieurs séparées par des virgules
                $categoryIds = array_map('intval', array_filter(
                    array_map('trim', explode(',', $categoriesParam)),
                    fn ($val) => '' !== $val && is_numeric($val)
                ));

                if ([] === $categoryIds) {
                    $categoryIds = null;
                }
            }

            $statut = $request->query->get('statut');
            if ($statut !== null && $statut !== '') {
                // Valider le statut (optionnel)
                $validStatuts = ['ACTIF', 'SORTIE'];
                if (!in_array(strtoupper($statut), $validStatuts)) {
                    return $apiResponse->error(
                        'Statut invalide. Les valeurs possibles sont: ' . implode(', ', $validStatuts),
                        Response::HTTP_BAD_REQUEST
                    );
                }
            }

            // Générer l'inventaire avec pagination
            $inventory = $inventaireService->generateInventory($user, $categoryIds, $page, $limit, $statut);

            return $apiResponse->success(
                $inventory,
                Response::HTTP_OK,
                'Inventaire récupéré avec succès.'
            );
        } catch (\DomainException $e) {
            return $apiResponse->error(
                $e->getMessage(),
                Response::HTTP_BAD_REQUEST
            );
        } catch (\Exception $e) {
            // Erreur interne - ne pas révéler les détails techniques
            return $apiResponse->error(
                'Une erreur est survenue lors de la récupération de l\'inventaire.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
