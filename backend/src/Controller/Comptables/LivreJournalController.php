<?php

namespace App\Controller\Comptables;

use App\Service\ApiResponseFactory;
use App\Service\Comptables\LivreJournal\LivreJournalService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller pour le Livre Journal (API comptable).
 * 
 * Tag Swagger : Comptables
 * 
 * Ce controller fournit une API READ-ONLY pour générer le Livre Journal
 * à partir des données existantes (biens et consommables).
 */
#[Route('/comptables')]
class LivreJournalController extends AbstractController
{
    public function __construct(
        private readonly LivreJournalService $livreJournalService,
        private readonly ApiResponseFactory $apiResponseFactory,
    ) {}

    /**
     * Récupérer le Livre Journal.
     * 
     * Endpoint READ-ONLY qui génère dynamiquement le Livre Journal
     * à partir des données de biens et consommables existants.
     */
    #[Route('/livre-journal', name: 'api_comptables_livre_journal', methods: ['GET'])]
    #[OA\Get(
        path: '/comptables/livre-journal',
        summary: 'Récupérer le Livre Journal',
        description: 'Génère le Livre Journal à partir des données de biens et consommables existants avec filtres et pagination.',
        tags: ['Comptables']
    )]
    #[OA\Parameter(
        name: 'type',
        description: 'Filtrer par type (BIEN ou CONSOMMABLE)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['BIEN', 'CONSOMMABLE'])
    )]
    #[OA\Parameter(
        name: 'service',
        description: 'Filtrer par ID de service (ou plusieurs IDs séparés par virgules)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'categorie',
        description: 'Filtrer par ID de catégorie (ou plusieurs IDs séparés par virgules)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'dateDebut',
        description: 'Date de début (format YYYY-MM-DD)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', format: 'date')
    )]
    #[OA\Parameter(
        name: 'dateFin',
        description: 'Date de fin (format YYYY-MM-DD)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', format: 'date')
    )]
    #[OA\Parameter(
        name: 'exercice',
        description: 'Filtrer par exercice (année)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'page',
        description: 'Numéro de page (défaut: 1)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 1)
    )]
    #[OA\Parameter(
        name: 'limit',
        description: 'Nombre d\'éléments par page (défaut: 10, max: 1000)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 10, maximum: 1000)
    )]
    #[OA\Response(
        response: 200,
        description: 'Livre Journal récupéré avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Livre Journal récupéré avec succès.',
                'data' => [
                    'data' => [
                        [
                            'id' => 154,
                            'numeroOrdre' => 1,
                            'numeroOrdreClasse' => 7,
                            'type' => 'BIEN',
                            'date' => '2026-08-01',
                            'origineDestination' => 'Service Informatique',
                            'imputationBudgetaire' => 500000.0,
                            'designation' => 'Ordinateur HP',
                            'uniteMesure' => 'Unité',
                            'prixUnitaire' => 500000.0,
                            'entree' => [
                                'quantite' => 1,
                                'valeur' => 500000.0
                            ],
                            'sortie' => [
                                'quantite' => 0,
                                'valeur' => 0
                            ],
                            'observations' => ''
                        ],
                        [
                            'id' => 42,
                            'numeroOrdre' => 2,
                            'numeroOrdreClasse' => 3,
                            'type' => 'CONSOMMABLE',
                            'date' => '2026-08-02',
                            'origineDestination' => 'Service Administratif',
                            'imputationBudgetaire' => 15000.0,
                            'designation' => 'Papier A4',
                            'uniteMesure' => 'Paquet',
                            'prixUnitaire' => 15000.0,
                            'entree' => [
                                'quantite' => 10,
                                'valeur' => 150000.0
                            ],
                            'sortie' => [
                                'quantite' => 5,
                                'valeur' => 75000.0
                            ],
                            'observations' => ''
                        ]
                    ],
                    'pagination' => [
                        'page' => 1,
                        'limit' => 10,
                        'total' => 150,
                        'totalPages' => 15
                    ]
                ]
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Requête invalide',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'status', type: 'integer', example: 400),
                new OA\Property(property: 'message', type: 'string', example: 'Paramètres invalides.'),
                new OA\Property(property: 'data', type: 'object', example: []),
            ]
        )
    )]
    public function getLivreJournal(Request $request): JsonResponse
    {
        // Récupérer les paramètres de filtre
        $filters = [
            'type' => $request->query->get('type'),
            'service' => $request->query->get('service'),
            'categorie' => $request->query->get('categorie'),
            'dateDebut' => $request->query->get('dateDebut'),
            'dateFin' => $request->query->get('dateFin'),
            'exercice' => $request->query->get('exercice'),
        ];

        // Parser les IDs multiples (séparés par virgules)
        if ($filters['service']) {
            $filters['service'] = array_map('trim', explode(',', $filters['service']));
        }
        if ($filters['categorie']) {
            $filters['categorie'] = array_map('trim', explode(',', $filters['categorie']));
        }

        // Récupérer les paramètres de pagination
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 10);

        // Valider la limite
        if ($limit < 1) {
            $limit = 10;
        }
        if ($limit > 1000) {
            $limit = 1000;
        }

        // Valider la page
        if ($page < 1) {
            $page = 1;
        }

        try {
            // Générer le Livre Journal
            $result = $this->livreJournalService->generate($filters, $page, $limit);

            return $this->apiResponseFactory->success($result, 200, 'Livre Journal récupéré avec succès.');
        } catch (\Exception $e) {
            return $this->apiResponseFactory->error('Erreur lors de la génération du Livre Journal : ' . $e->getMessage(), 500);
        }
    }
}
