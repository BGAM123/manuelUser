<?php

namespace App\Controller\Comptables;

use App\Service\ApiResponseFactory;
use App\Service\Comptables\FicheStock\FicheStockService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/comptables/fiches-stock')]
#[OA\Tag(name: 'Comptables')]
final class FicheStockController extends AbstractController
{
    public function __construct(
        private readonly FicheStockService $ficheStockService,
        private readonly ApiResponseFactory $apiResponse,
    ) {
    }

    #[Route('', name: 'app_fiches_stock', methods: ['GET'])]
    #[OA\Get(
        path: '/comptables/fiches-stock',
        summary: 'Fiches de stock',
        description: 'Retourne les fiches de stock des consommables. Les fiches sont générées dynamiquement à partir des mouvements (entrées, transferts, BSP). L\'API est READ-ONLY et ne modifie aucune donnée.'
    )]
    #[OA\Parameter(
        name: 'service_id',
        description: 'Filtre par service (optionnel)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'consumable_id',
        description: 'Filtre par consommable (optionnel)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'page',
        description: 'Numéro de page (défaut: 1)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'limit',
        description: 'Nombre de fiches par page (défaut: 10)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'date_debut',
        description: 'Date de début de période (format YYYY-MM-DD, optionnel)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', format: 'date')
    )]
    #[OA\Parameter(
        name: 'date_fin',
        description: 'Date de fin de période (format YYYY-MM-DD, optionnel)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', format: 'date')
    )]
    #[OA\Parameter(
        name: 'search',
        description: 'Recherche par nom de consommable (optionnel)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'header' => [
                    'service' => null,
                    'numeroNomenclature' => null,
                    'designation' => null,
                    'codeMateriel' => null,
                    'especeUnite' => null,
                    'prixUnitaire' => null,
                ],
                'fiches' => [
                    [
                        'consumable' => [
                            'id' => 1,
                            // 'nom' => 'Ramette de papier A4',
                            // 'unite_mesure' => 'Paquet',
                        ],
                        'service' => [
                            'id' => 5,
                            'nom' => 'Direction des Systèmes d\'Information',
                        ],
                        'movements' => [
                            [
                                'date' => '2026-01-10',
                                'origineDestination' => 'Entrée initiale',
                                'stockInitial' => 100,
                                'quantites' => [
                                    'entrees' => 30,
                                    'sorties' => 0,
                                    'enStock' => 130,
                                ],
                                'numeroBlBsp' => null,
                                'observations' => '',
                            ],
                            [
                                'date' => '2026-01-20',
                                'origineDestination' => 'Direction Comptabilité',
                                'stockInitial' => null,
                                'quantites' => [
                                    'entrees' => 0,
                                    'sorties' => 20,
                                    'enStock' => 110,
                                ],
                                'numeroBlBsp' => null,
                                'observations' => '',
                            ],
                        ],
                    ],
                ],
                'pagination' => [
                    'page' => 1,
                    'limit' => 10,
                    'total' => 45,
                    'totalPages' => 5,
                ],
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'Consommable introuvable.', 'data' => null])
    )]
    #[OA\Response(
        response: 401,
        description: 'Non authentifié',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null])
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $serviceId = $request->query->getInt('service_id');
        $consumableId = $request->query->getInt('consumable_id');
        $search = $request->query->get('search');
        $dateDebut = $request->query->get('date_debut');
        $dateFin = $request->query->get('date_fin');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 10)));

        // Validation des dates
        if ($dateDebut) {
            try {
                $dateDebut = new \DateTime($dateDebut);
            } catch (\Exception $e) {
                return $this->apiResponse->error(
                    'Format de date_debut invalide (attendu: YYYY-MM-DD).',
                    Response::HTTP_BAD_REQUEST
                );
            }
        }

        if ($dateFin) {
            try {
                $dateFin = new \DateTime($dateFin);
            } catch (\Exception $e) {
                return $this->apiResponse->error(
                    'Format de date_fin invalide (attendu: YYYY-MM-DD).',
                    Response::HTTP_BAD_REQUEST
                );
            }
        }

        // Validation de la cohérence des dates
        if ($dateDebut && $dateFin && $dateDebut > $dateFin) {
            return $this->apiResponse->error(
                'La date_debut doit être antérieure ou égale à la date_fin.',
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $fiches = $this->ficheStockService->generateFiches(
                $consumableId ?: null,
                $serviceId ?: null,
                $dateDebut,
                $dateFin,
                $search,
                $page,
                $limit
            );

            return $this->apiResponse->success($fiches, Response::HTTP_OK, 'Fiches de stock générées avec succès.');
        } catch (\InvalidArgumentException $e) {
            return $this->apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->apiResponse->error(
                'Une erreur est survenue lors de la génération des fiches de stock.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
