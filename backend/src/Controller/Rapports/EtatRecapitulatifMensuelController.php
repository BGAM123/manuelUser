<?php

namespace App\Controller\Rapports;

use App\Service\ApiResponseFactory;
use App\Service\RapportEtatRecapitulatif\EtatRecapitulatifMensuelService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller pour l'État Récapitulatif Mensuel.
 *
 * Tag Swagger : Comptables
 *
 * Ce controller fournit une API READ-ONLY pour générer l'état récapitulatif
 * mensuel des mouvements des biens (entrées/sorties).
 */
#[Route('/rapports')]
#[OA\Tag(name: 'Comptables')]
class EtatRecapitulatifMensuelController extends AbstractController
{
    public function __construct(
        private readonly EtatRecapitulatifMensuelService $etatRecapitulatifService,
        private readonly ApiResponseFactory $apiResponse,
    ) {
    }

    #[Route('/etat-recapitulatif-mensuel', name: 'api_rapports_etat_recapitulatif_mensuel', methods: ['GET'])]
    #[OA\Get(
        path: '/rapports/etat-recapitulatif-mensuel',
        summary: 'Génère l\'état récapitulatif mensuel des consommables',
        description: 'Génère un état récapitulatif mensuel des mouvements de consommables (entrées/sorties/transferts) avec calcul des transferts entre services. Les paramètres de période sont optionnels. Si l\'exercice est fourni, les dates sont automatiquement définies sur l\'année civile.\n\nExemple de requête : GET /rapports/etat-recapitulatif-mensuel?serviceId=1,2,5&exercice=2024&page=1&limit=10'
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
        description: 'Nombre d\'éléments par page (défaut: 10)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    // #[OA\Parameter(
    //     name: 'periodeDebut',
    //     description: 'Date de début de période (format YYYY-MM-DD, optionnel)',
    //     in: 'query',
    //     required: false,
    //     schema: new OA\Schema(type: 'string', format: 'date')
    // )]
    // #[OA\Parameter(
    //     name: 'periodeFin',
    //     description: 'Date de fin de période (format YYYY-MM-DD, optionnel)',
    //     in: 'query',
    //     required: false,
    //     schema: new OA\Schema(type: 'string', format: 'date')
    // )]
    #[OA\Parameter(
        name: 'serviceId',
        description: 'ID du service pour filtrer les transferts (optionnel) - Peut être plusieurs IDs séparés par des virgules (ex: 1,2,5)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'exercice',
        description: 'Année de l\'exercice (optionnel) - Prioritaire sur periodeDebut/periodeFin',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'État récapitulatif mensuel généré avec succès.',
                'data' => [
                    'meta' => [
                        'current_page' => 1,
                        'limit' => 10,
                        'total_items' => 25,
                        'total_pages' => 3
                    ],
                    'data' => [
                        [
                            'numero' => 1,
                            'designation' => 'Papier A4',
                            'prixUnitaire' => 15,
                            'stockAuEntre' => [
                                'quantite' => 100,
                                'valeur' => 1500
                            ],
                            'stockAuSortie' => [
                                'quantite' => 50,
                                'valeur' => 750
                            ],
                            'entrees' => [
                                'quantite' => 100,
                                'valeur' => 1500
                            ],
                            'sorties' => [
                                'quantite' => 50,
                                'valeur' => 750
                            ]
                        ]
                    ]
                ]
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'Requête invalide.', 'data' => null])
    )]
    #[OA\Response(
        response: 401,
        description: 'Non authentifié',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null])
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, $request->query->getInt('limit', 10));
        $periodeDebutStr = $request->query->get('periodeDebut');
        $periodeFinStr = $request->query->get('periodeFin');
        $serviceIdStr = $request->query->get('serviceId');
        $serviceIds = null;
        if (null !== $serviceIdStr && '' !== trim($serviceIdStr)) {
            $serviceIds = array_map('intval', array_map('trim', explode(',', $serviceIdStr)));
        }
        $exercice = $request->query->getInt('exercice');

        // Si exercice est fourni, définir la période automatiquement
        if ($exercice) {
            $periodeDebut = new \DateTime("{$exercice}-01-01");
            $periodeFin = new \DateTime("{$exercice}-12-31");
        } else {
            // Validation des dates si fournies
            if ($periodeDebutStr && !$periodeFinStr) {
                return $this->apiResponse->error(
                    'Si periodeDebut est fourni, periodeFin est obligatoire.',
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$periodeDebutStr && $periodeFinStr) {
                return $this->apiResponse->error(
                    'Si periodeFin est fourni, periodeDebut est obligatoire.',
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Si aucune période n'est fournie, utiliser l'année courante
            if (!$periodeDebutStr && !$periodeFinStr) {
                $currentYear = (int) date('Y');
                $periodeDebut = new \DateTime("{$currentYear}-01-01");
                $periodeFin = new \DateTime("{$currentYear}-12-31");
            } else {
                // Validation des dates fournies
                try {
                    $periodeDebut = new \DateTime($periodeDebutStr);
                } catch (\Exception $e) {
                    return $this->apiResponse->error(
                        'Format de periodeDebut invalide (attendu: YYYY-MM-DD).',
                        Response::HTTP_BAD_REQUEST
                    );
                }

                try {
                    $periodeFin = new \DateTime($periodeFinStr);
                } catch (\Exception $e) {
                    return $this->apiResponse->error(
                        'Format de periodeFin invalide (attendu: YYYY-MM-DD).',
                        Response::HTTP_BAD_REQUEST
                    );
                }

                // Validation de la cohérence des dates
                if ($periodeDebut > $periodeFin) {
                    return $this->apiResponse->error(
                        'La periodeDebut doit être antérieure ou égale à la periodeFin.',
                        Response::HTTP_BAD_REQUEST
                    );
                }
            }
        }

        try {
            $result = $this->etatRecapitulatifService->generate(
                $periodeDebut,
                $periodeFin,
                $serviceIds,
                $page,
                $limit
            );

            return $this->apiResponse->success($result, Response::HTTP_OK, 'État récapitulatif mensuel généré avec succès.');
        } catch (\InvalidArgumentException $e) {
            return $this->apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            // Pour le debug, décommentez la ligne ci-dessous
            // return $this->apiResponse->error($e->getMessage() . ' - ' . $e->getFile() . ':' . $e->getLine(), Response::HTTP_INTERNAL_SERVER_ERROR);

            return $this->apiResponse->error(
                'Une erreur est survenue lors de la génération de l\'état récapitulatif mensuel.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}