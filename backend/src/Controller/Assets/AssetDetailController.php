<?php

namespace App\Controller\Assets;

use App\Entity\Asset;
use App\Repository\AssetRepository;
use App\Service\ApiResponseFactory;
use App\Service\AssetResponseBuilder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/assets')]
#[OA\Tag(name: 'Assets')]
final class AssetDetailController extends AbstractController
{
    // #[Route('/{id}', name: 'app_asset_detail', methods: ['GET'])]
    #[Route('/{id<\d+>}', name: 'app_asset_detail', methods: ['GET'], priority: 0)]
    #[OA\Get(
        path: '/assets/{id}',
        summary: 'Détail d\'un bien patrimonial',
        description: 'Retourne le bien complet : catégorie, type (sans valeurBien), état, projets, service, utilisateur rattaché (prénom, nom, email, rôles, service), fournisseur, photos, pièces jointes, affectations, maintenances, réévaluations, dépréciations, sortie. Pas d\'historiques.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Détail du bien récupéré avec succès.',
                'data' => [
                    'id' => 1,
                    'reference' => 'PAT-2026-00001',
                    'nom' => 'Ordinateur Portable HP ProBook 450 G10',
                    'numeroSerie' => 'HP-PB-2026-0001',
                    'description' => 'Ordinateur portable affecté à la Direction des Systèmes d\'Information.',
                    'statut' => 'ACTIF',
                    'exercice' => 2026,
                    'valeur' => 850000,
                    'valeurInitiale' => 850000,
                    'dateAcquisition' => '2026-07-30',
                    'modeAcquisition' => 'Achat',
                    'sourceFinancement' => 'Budget Propre',
                    'amortissement' => [
                        'valeurAcquisition' => 850000,
                        'valeurActuelle' => 680000,
                        'amortissementAnnuel' => 170000,
                        'amortissementCumule' => 170000,
                        'dureeVie' => 5,
                        'taux' => 20,
                        'anneesEcoulees' => 1,
                        'dateAcquisition' => '2026-07-30',
                    ],
                    'categorie' => ['id' => 2, 'nom' => 'Matériel Informatique'],
                    'typeBien' => ['id' => 5, 'nom' => 'Ordinateur Portable', 'dureeVie' => 5, 'taux' => 20],
                    'etatBien' => ['id' => 1, 'nom' => 'Fonctionnel'],
                    'projets' => [
                        ['id' => 3, 'nom' => 'Projet de Modernisation du Système d\'Information'],
                        ['id' => 7, 'nom' => 'Projet de Transformation Numérique'],
                    ],
                    'service' => [
                        'id' => 16,
                        'nom' => 'Direction des Systèmes d\'Information',
                        'sigle' => 'DSI',
                        'typeService' => 'SERVICE',
                    ],
                    'utilisateur' => [
                        'id' => 8,
                        'firstName' => 'Jean Paul',
                        'lastName' => 'NGONO',
                        'email' => 'jp.ngono@minepia.cm',
                        'assignedRoles' => [['id' => 2, 'nom' => 'Gestionnaire de biens']],
                        'service' => ['id' => 16, 'nom' => 'informaticien cen', 'typeService' => 'POSTE'],
                    ],
                    'fournisseur' => [
                        'type' => 'ENTREPRISE',
                        'nom' => 'CAMTEL TECHNOLOGIES',
                        'email' => 'contact@camtel-technologies.cm',
                        'telephone' => '+237699000000',
                        'adresse' => 'Avenue Kennedy',
                        'ville' => 'Yaoundé',
                        'pays' => 'Cameroun',
                    ],
                    'photos' => [
                        ['id' => 15, 'nom' => 'Photo façade', 'chemin' => '/uploads/assets/photos/photo1.jpg'],
                    ],
                    'piecesJointes' => [
                        ['id' => 21, 'nom' => 'Facture d\'achat', 'chemin' => '/uploads/assets/documents/facture.pdf'],
                    ],
                    'affectations' => [
                        [
                            'id' => 1,
                            'typeAffectation' => 'AFFECTATION',
                            'dateDebut' => '2026-08-01',
                            'dateFin' => null,
                            'commentaire' => 'Affectation pour le suivi des activités terrain.',
                            'service' => ['id' => 16, 'nom' => 'Direction des Systèmes d\'Information'],
                            'localisation' => [
                                'region' => ['id' => 1, 'nom' => 'Centre'],
                                'departement' => ['id' => 5, 'nom' => 'Mfoundi'],
                                'arrondissement' => ['id' => 12, 'nom' => 'Yaoundé I']
                            ],
                            'utilisateur' => ['id' => 8, 'firstName' => 'Jean Paul', 'lastName' => 'NGONO'],
                            'piecesJointes' => [
                                ['id' => 22, 'nom' => 'PV de remise', 'chemin' => '/uploads/assignments/pv-remise.pdf']
                            ],
                            'createdAt' => '2026-08-01 09:15:00'
                        ]
                    ],
                    'maintenances' => [
                        [
                            'id' => 12,
                            'etatBien' => ['id' => 2, 'nom' => 'Bon'],
                            'motif' => 'Entretien préventif',
                            'cout' => 100000,
                            'dateIntervention' => '2024-01-05',
                            'dateRecuperation' => '2025-06-05',
                            'observations' => 'Maintenance annuelle.',
                            'piecesJointes' => [
                                ['id' => 5, 'nom' => 'Facture', 'chemin' => '/uploads/maintenances/facture.pdf']
                            ],
                            'createdAt' => '2026-08-01 10:30:00'
                        ]
                    ],
                    'reevaluations' => [
                        [
                            'id' => 1,
                            'valeurActuelle' => 2000000,
                            'nouvelleValeur' => 2250000,
                            'methodeEvaluation' => 'Expertise',
                            'service' => ['id' => 6, 'nom' => 'Direction du Patrimoine'],
                            'dateReevaluation' => '2024-01-10',
                            'motif' => 'Réévaluation annuelle',
                            'observations' => null,
                            'piecesJointes' => [],
                            'createdAt' => '2026-08-01 11:00:00'
                        ]
                    ],
                    'depreciations' => [
                        [
                            'id' => 3,
                            'typeDepreciation' => 'Usure',
                            'methodeAmortissement' => 'Linéaire',
                            'dureeVie' => 10,
                            'valeurActuelle' => 2000000,
                            'tauxDepreciation' => 20,
                            'montantDepreciation' => 400000,
                            'dateDepreciation' => '2025-01-10',
                            'motif' => 'Dépréciation liée à l\'usage',
                            'observations' => null,
                            'piecesJointes' => [],
                            'createdAt' => '2026-08-01 11:20:00'
                        ]
                    ],
                    'sortie' => null,
                    // ✅ Ajout du bloc sécurisation avec le mode
                    'securisation' => [
                        'id' => 1,
                        'securityMode' => [
                            'id' => 1,
                            'nom' => 'Physique',
                            'description' => 'Sécurisation par barrières physiques'
                        ],
                        'dateSecurisation' => '2026-08-11',
                        'location' => [
                            'id' => 23,
                            'latitude' => 48.8566,
                            'longitude' => 2.3522
                        ],
                        'documents' => [
                            [
                                'id' => 54,
                                'nom' => 'Document de sécurisation',
                                'chemin' => '/uploads/securities/document_abc123.pdf',
                                // 'isPhoto' => false
                            ]
                        ],
                        'createdAt' => '2026-08-11 10:00:00',
                        'updatedAt' => '2026-08-11 10:00:00'
                    ],
                    'createdAt' => '2026-07-30 15:45:10',
                    'updatedAt' => '2026-07-31 09:10:42',
                    'doitEtreRestitue' => false,
                ],
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Not Found',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Le bien demandé est introuvable.', 'data' => null])
    )]
    #[OA\Response(
        response: 401,
        description: 'Non authentifié',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null])
    )]
    public function __invoke(
        int $id,
        AssetRepository $assetRepository,
        AssetResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $asset = $assetRepository->getActiveById($id);
        if (!$asset instanceof Asset) {
            return $apiResponse->error('Le bien demandé est introuvable.', Response::HTTP_NOT_FOUND);
        }

        return $apiResponse->success(
            $responseBuilder->buildDetail($asset),
            Response::HTTP_OK,
            'Détail du bien récupéré avec succès.'
        );
    }
}
