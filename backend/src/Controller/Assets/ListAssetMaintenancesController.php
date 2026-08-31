<?php

namespace App\Controller\Assets;

use App\Repository\AssetRepository;
use App\Repository\AssetMaintenanceRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/assets')]
#[OA\Tag(name: 'Asset Maintenances')]
final class ListAssetMaintenancesController extends AbstractController
{
    public function __construct(
        private readonly AssetMaintenanceRepository $maintenanceRepository
    ) {
    }

    #[Route('/maintenances/en-cours', name: 'app_asset_maintenance_schedule', methods: ['GET'])]
    #[OA\Get(
        path: '/assets/maintenances/en-cours',
        summary: 'Biens actuellement en maintenance par catégorie',
        description: 'Retourne la liste des biens ayant une maintenance ouverte, regroupés par catégorie avec le coût total, le nombre de maintenances et les informations de seuil de maintenance (hérité de la catégorie). Un bien n\'apparaît qu\'une seule fois avec sa dernière maintenance en cours.'
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1),
        description: 'Numéro de la page'
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 20),
        description: 'Nombre d\'éléments par page (max: 200)'
    )]
    #[OA\Parameter(
        name: 'category_id',
        in: 'query',
        schema: new OA\Schema(type: 'integer'),
        description: 'Filtrer par ID de catégorie'
    )]
    #[OA\Parameter(
        name: 'asset_type_id',
        in: 'query',
        schema: new OA\Schema(type: 'integer'),
        description: 'Filtrer par ID de type de bien'
    )]
    #[OA\Parameter(
        name: 'service_id',
        in: 'query',
        schema: new OA\Schema(type: 'integer'),
        description: 'Filtrer par structure/service rattaché au bien'
    )]
    #[OA\Parameter(
        name: 'exercice',
        in: 'query',
        schema: new OA\Schema(type: 'integer'),
        description: 'Filtre les maintenances intervenues durant cette année (ex: 2026). Basé sur la date d\'intervention.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - biens en maintenance retournés par catégorie',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Biens en maintenance retournés avec succès.',
                'data' => [
                    'meta' => [
                        'current_page' => 1,
                        'limit' => 20,
                        'total_items' => 3,
                        'total_pages' => 1
                    ],
                    'data' => [
                        [
                            'categorie' => [
                                'id' => 2,
                                'nom' => 'Matériel informatique',
                                'seuil' => 5
                            ],
                            'nombre_maintenances' => 2,
                            'cout_total' => 45000.00,
                            // 'duree_moyenne_estimee' => 8.5,
                            'maintenances' => [
                                [
                                    'bien' => [
                                        'id' => 1,
                                        'reference' => 'PAT-2026-00001',
                                        'designation' => 'Ordinateur Portable HP ProBook 450 G10',
                                        'nombre_maintenances_total' => 3,
                                        'seuil' => 5,
                                        'seuil_depasse' => false,
                                        'reste_avant_depassement' => 2
                                    ],
                                    'typeBien' => [
                                        'id' => 5,
                                        'nom' => 'Ordinateur Portable'
                                    ],
                                    'maintenance' => [
                                        'id' => 12,
                                        'etatBien' => [
                                            'id' => 3,
                                            'nom' => 'En panne'
                                        ],
                                        'motif' => 'Écran défectueux',
                                        'cout' => '25000.00',
                                        'dateIntervention' => '2026-08-01',
                                        'observations' => 'Prise en charge sous garantie',
                                        'statut' => 'EN COURS'
                                    ]
                                ]
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
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 400,
                'message' => 'Le paramètre exercice doit être une année valide (4 chiffres).',
                'data' => null
            ]
        )
    )]
    public function __invoke(
        Request $request,
        AssetRepository $assetRepository,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 20);
        $limit = $limit < 1 ? 20 : ($limit > 200 ? 200 : $limit);

        $categoryId = $request->query->has('category_id') ? $request->query->getInt('category_id') : null;
        $assetTypeId = $request->query->has('asset_type_id') ? $request->query->getInt('asset_type_id') : null;
        $serviceId = $request->query->has('service_id') ? $request->query->getInt('service_id') : null;

        $dateDebut = null;
        $dateFin = null;
        $exerciceParam = $request->query->get('exercice');
        if (null !== $exerciceParam && '' !== $exerciceParam) {
            if (!preg_match('/^\d{4}$/', (string) $exerciceParam)) {
                return $apiResponse->error('Le paramètre exercice doit être une année valide (4 chiffres).', Response::HTTP_BAD_REQUEST);
            }
            $dateDebut = new \DateTimeImmutable($exerciceParam . '-01-01');
            $dateFin = new \DateTimeImmutable($exerciceParam . '-12-31');
        }

        // Récupérer toutes les maintenances ouvertes (paginées)
        $pairs = $assetRepository->findActiveInMaintenance($page, $limit, $categoryId, $assetTypeId, $dateDebut, $dateFin, $serviceId);
        $total = $assetRepository->countActiveInMaintenance($categoryId, $assetTypeId, $dateDebut, $dateFin, $serviceId);

        // ✅ Filtrer : garder uniquement la dernière maintenance en cours par bien
        $uniquePairs = $this->getUniqueMaintenancesByAsset($pairs);

        // Grouper par catégorie avec calcul des durées estimées et des seuils
        $groupedData = $this->groupMaintenancesByCategory($uniquePairs);

        // Construire la réponse paginée
        $meta = [
            'current_page' => $page,
            'limit' => $limit,
            'total_items' => $total,
            'total_pages' => (int) ceil($total / max(1, $limit)),
        ];

        return $apiResponse->success([
            'meta' => $meta,
            'data' => $groupedData,
        ], Response::HTTP_OK, 'Biens en maintenance retournés avec succès.');
    }

    /**
     * ✅ Filtre les paires pour ne garder que la dernière maintenance en cours par bien
     * 
     * @param array $pairs Tableau de paires [Asset, AssetMaintenance]
     * @return array Tableau filtré avec un seul [Asset, AssetMaintenance] par bien
     */
    private function getUniqueMaintenancesByAsset(array $pairs): array
    {
        $uniqueAssets = [];

        foreach ($pairs as [$asset, $maintenance]) {
            $assetId = $asset->getId();
            
            // Si le bien n'existe pas encore dans le tableau ou si la maintenance est plus récente
            if (!isset($uniqueAssets[$assetId])) {
                $uniqueAssets[$assetId] = [$asset, $maintenance];
            } else {
                // Comparer les dates d'intervention pour garder la plus récente
                [$existingAsset, $existingMaintenance] = $uniqueAssets[$assetId];
                
                $existingDate = $existingMaintenance->getDateIntervention();
                $currentDate = $maintenance->getDateIntervention();
                
                // Si la maintenance actuelle est plus récente, on la remplace
                if ($currentDate !== null && ($existingDate === null || $currentDate > $existingDate)) {
                    $uniqueAssets[$assetId] = [$asset, $maintenance];
                }
            }
        }

        return array_values($uniqueAssets);
    }

    /**
     * Groupe les maintenances par catégorie avec les informations de seuil
     * 
     * @param array $pairs Tableau de paires [Asset, AssetMaintenance]
     * @return array
     */
    private function groupMaintenancesByCategory(array $pairs): array
    {
        $grouped = [];

        foreach ($pairs as [$asset, $maintenance]) {
            // Récupérer la catégorie du bien (le seuil vient de la catégorie)
            $category = $asset->getCategories()->first();
            $categoryId = $category ? $category->getId() : 0;
            $categoryKey = $categoryId ? 'cat_' . $categoryId : 'cat_sans_categorie';

            // Le seuil est celui de la catégorie
            $seuil = $category ? $category->getSeuil() : null;
            $seuilDefini = $seuil !== null;

            // Compter le nombre total de maintenances pour ce bien
            $nombreMaintenancesBien = $this->maintenanceRepository->countMaintenancesForAsset($asset);
            
            // Vérifier si le bien a dépassé le seuil de sa catégorie
            $seuilDepasse = false;
            $resteAvantDepassement = null;
            if ($seuilDefini) {
                $seuilDepasse = $nombreMaintenancesBien > $seuil;
                $resteAvantDepassement = max(0, $seuil - $nombreMaintenancesBien);
            }

            // Initialiser le groupe si inexistant
            if (!isset($grouped[$categoryKey])) {
                $grouped[$categoryKey] = [
                    'categorie' => $category ? [
                        'id' => $category->getId(),
                        'nom' => $category->getNom(),
                        'seuil' => $seuil,
                    ] : null,
                    'nombre_maintenances' => 0,
                    'cout_total' => 0.0,
                    // 'duree_moyenne_estimee' => 0,
                    'maintenances' => [],
                    '_durees' => [],
                ];
            }

            // Calcul de la durée estimée
            $dateIntervention = $maintenance->getDateIntervention();
            $dateRecuperationPrevue = $maintenance->getDateRecuperationPrevue();
            
            $dureeEstimee = null;
            $joursEcoules = null;
            $estEnRetard = false;

            if ($dateIntervention !== null) {
                $now = new \DateTime();
                $joursEcoules = (int) $dateIntervention->diff($now)->days;

                if ($dateRecuperationPrevue !== null) {
                    $dureeEstimee = (int) $dateIntervention->diff($dateRecuperationPrevue)->days;
                    
                    if ($now > $dateRecuperationPrevue) {
                        $estEnRetard = true;
                    }
                }
            }

            $type = $asset->getAssetTypes()->first();
            $etatBien = $maintenance->getEtatBien();

            // Ajouter la maintenance avec les informations du bien
            $grouped[$categoryKey]['maintenances'][] = [
                'bien' => [
                    'id' => $asset->getId(),
                    'reference' => $asset->getReference(),
                    'designation' => $asset->getNom(),
                    'nombre_maintenances_total' => $nombreMaintenancesBien,
                    'seuil' => $seuil,
                    'seuil_depasse' => $seuilDepasse,
                    'reste_avant_depassement' => $resteAvantDepassement,
                ],
                'typeBien' => $type ? [
                    'id' => $type->getId(),
                    'nom' => $type->getNom()
                ] : null,
                'maintenance' => [
                    'id' => $maintenance->getId(),
                    'etatBien' => $etatBien ? [
                        'id' => $etatBien->getId(),
                        'nom' => $etatBien->getNom()
                    ] : null,
                    'motif' => $maintenance->getMotif(),
                    'cout' => $maintenance->getCout(),
                    'dateIntervention' => $dateIntervention?->format('Y-m-d'),
                    'observations' => $maintenance->getObservations(),
                    'statut' => $maintenance->getStatut(),
                ],
            ];

            // Mettre à jour les agrégats
            $grouped[$categoryKey]['nombre_maintenances']++;
            $cout = (float) ($maintenance->getCout() ?? 0);
            $grouped[$categoryKey]['cout_total'] += $cout;

            if ($dureeEstimee !== null) {
                $grouped[$categoryKey]['_durees'][] = $dureeEstimee;
            }
        }

        // Calculer les statistiques finales
        $result = [];
        foreach ($grouped as $group) {
            $durees = $group['_durees'];
            if (!empty($durees)) {
                // $group['duree_moyenne_estimee'] = round(array_sum($durees) / count($durees), 1);
            }
            unset($group['_durees']);
            $result[] = $group;
        }

        // Trier par coût total décroissant
        usort($result, function ($a, $b) {
            return $b['cout_total'] <=> $a['cout_total'];
        });

        return $result;
    }
}