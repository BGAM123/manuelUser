<?php

namespace App\Service;

use App\Entity\Asset;

/**
 * Builder pour formater les réponses de l'API de stock patrimonial.
 */
final class StockResponseBuilder
{
    public function __construct(
        private readonly StockService $stockService
    ) {
    }

    /**
     * Formate un bien avec sa situation calculée
     */
    public function buildAssetListItem(Asset $asset): array
    {
        $situation = $this->stockService->calculateAssetSituation($asset);
        $details = $this->getSituationDetails($asset->getId(), $situation);

        return [
            'id' => $asset->getId(),
            // 'reference' => $asset->getReference(),
            // 'code' => $asset->getCode(),
            'nom' => $asset->getNom(),
            'numero_serie' => $asset->getNumeroSerie(),
            // 'date_acquisition' => $asset->getDateAcquisition()?->format('Y-m-d'),
            // 'valeur_initiale' => $asset->getValeurInitiale(),
            // 'valeur' => $asset->getValeur(),
            'statut' => $asset->getStatut(),
            'situation' => $situation,
            'situation_details' => $details,
            // 'categories' => $asset->getCategories()->map(fn($c) => [
            //     'id' => $c->getId(),
            //     'nom' => $c->getNom(),
            // ])->toArray(),
            // 'asset_types' => $asset->getAssetTypes()->map(fn($t) => [
            //     'id' => $t->getId(),
            //     'nom' => $t->getNom(),
            // ])->toArray(),
            // 'asset_sub_types' => $asset->getAssetSubTypes()->map(fn($st) => [
            //     'id' => $st->getId(),
            //     'nom' => $st->getNom(),
            // ])->toArray(),
            // 'etat_biens' => $asset->getEtatBiens()->map(fn($e) => [
            //     'id' => $e->getId(),
            //     'nom' => $e->getNom(),
            // ])->toArray(),
            // 'projects' => $asset->getProjects()->map(fn($p) => [
            //     'id' => $p->getId(),
            //     'nom' => $p->getNom(),
            // ])->toArray(),
            // 'services' => $asset->getServices()->map(fn($s) => [
            //     'id' => $s->getId(),
            //     'nom' => $s->getNom(),
            //     'sigle' => $s->getSigle(),
            // ])->toArray(),
            // 'quantite_stock' => $asset->getQuantiteStock(),
            // 'created_at' => $asset->getCreatedAt()?->format('Y-m-d H:i:s'),
            // 'updated_at' => $asset->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Formate un bien en détail avec toutes les informations
     */
    public function buildAssetDetail(Asset $asset): array
    {
        $listItem = $this->buildAssetListItem($asset);
        
        // Ajouter des informations supplémentaires pour le détail
        $listItem['description'] = $asset->getDescription();
        $listItem['valeur_initiale'] = $asset->getValeurInitiale();
        $listItem['prix_mercurial'] = $asset->getPrixMercurial();
        $listItem['mode_acquisition'] = $asset->getModeAcquisition();
        $listItem['active_amortissement'] = $asset->isActiveAmortissement();
        $listItem['active_reevaluation'] = $asset->isActiveReevaluation();
        
        // Informations fournisseur
        if ($asset->getTypeFournisseur()) {
            $listItem['fournisseur'] = [
                'type' => $asset->getTypeFournisseur(),
                'nom' => $asset->getFournisseurNom(),
                'email' => $asset->getFournisseurEmail(),
                'telephone' => $asset->getFournisseurTelephone(),
                'adresse' => $asset->getFournisseurAdresse(),
                'ville' => $asset->getFournisseurVille(),
                'pays' => $asset->getFournisseurPays(),
            ];
        }

        // Projets
        $listItem['projects'] = $asset->getProjects()->map(fn($p) => [
            'id' => $p->getId(),
            'nom' => $p->getNom(),
        ])->toArray();

        return $listItem;
    }

    /**
     * Récupère les détails de la situation d'un bien
     */
    private function getSituationDetails(int $assetId, string $situation): array
    {
        return match ($situation) {
            StockService::SITUATION_AFFECTE => [
                'assignment' => $this->stockService->getActiveAssignment($assetId),
            ],
            StockService::SITUATION_MAINTENANCE => [
                'maintenance' => $this->stockService->getOpenMaintenance($assetId),
            ],
            StockService::SITUATION_SORTI_DEFINITIVEMENT => [
                'exit' => $this->stockService->getAssetExit($assetId),
            ],
            StockService::SITUATION_SORTIE_TEMPORAIRE,
            StockService::SITUATION_RETOURNE => [
                'bsp' => $this->stockService->getAssetBsp($assetId),
            ],
            default => [],
        };
    }

    /**
     * Formate le résumé du stock avec nouvelle structure
     */
    // public function buildStockSummary(array $counts, array $assignments, array $statuts): array
    // {
    //     return [
    //         'patrimoine' => [
    //             'total' => $counts['patrimoine_total'] ?? 0,
    //             'actifs' => $statuts['actifs'] ?? 0,
    //             'inactifs' => $statuts['inactifs'] ?? 0,
    //         ],
    //         'situations' => [
    //             'disponibles' => $counts['disponibles'] ?? 0,
    //             'non_affectes' => $counts['non_affectes'] ?? 0,
    //             'affectes' => $counts['affectes_total'] ?? 0,
    //             'maintenance' => $counts['maintenance'] ?? 0,
    //         ],
    //         'affectations' => [
    //             'total' => $assignments['total'] ?? 0,
    //             'utilisateurs' => [
    //                 'total_biens' => $assignments['utilisateurs_total'] ?? 0,
    //                 'items' => array_map(fn($u) => [
    //                     'user_id' => $u['id'],
    //                     'nom' => $u['last_name'],
    //                     'prenom' => $u['first_name'],
    //                     'matricule' => $u['matricule'],
    //                     'nombre_biens' => $u['nombre_biens'],
    //                 ], $assignments['utilisateurs'] ?? []),
    //             ],
    //             'services' => [
    //                 'total_biens' => $assignments['services_total'] ?? 0,
    //                 'items' => array_map(fn($s) => [
    //                     'service_id' => $s['id'],
    //                     'nom' => $s['nom'],
    //                     'sigle' => $s['sigle'],
    //                     'nombre_biens' => $s['nombre_biens'],
    //                 ], $assignments['services'] ?? []),
    //             ],
    //         ],
    //         'sorties' => [
    //             'definitives' => $counts['sortis_definitivement'] ?? 0,
    //             'bsp_non_retournes' => $counts['bsp_non_retournes'] ?? 0,
    //         ],
    //     ];
    // }

    public function buildStockSummary(array $counts, array $assignments, array $statuts, array $projects = []): array
{
    return [
        'total_biens' => $counts['total_biens'] ?? 0,
        'total_sortis' => $counts['total_sortis'] ?? 0,
        'total_patrimoine' => $counts['total_patrimoine'] ?? 0,
        'patrimoine' => [
            'total' => $counts['total_patrimoine'] ?? 0,
            'actifs' => $statuts['actifs'] ?? 0,
            'inactifs' => $statuts['inactifs'] ?? 0,
        ],
        'sorties' => [
            'definitives' => $counts['sorties_definitives'] ?? 0,
            // 'bsp_non_retournes' => $counts['bsp_non_retournes'] ?? 0,
            // 'total' => $counts['total_sortis'] ?? 0,
        ],
        'situations' => [
            'non_affectes' => $counts['non_affectes'] ?? 0,
            'affectes' => $counts['affectes'] ?? 0,
            'maintenance' => $counts['maintenance'] ?? 0,
        ],
        'affectations' => [
            'total' => $assignments['total'] ?? 0,
            'utilisateurs' => [
                'total_biens' => $assignments['utilisateurs_total'] ?? 0,
                'items' => array_map(fn($u) => [
                    'user_id' => $u['id'],
                    'nom' => $u['last_name'],
                    'prenom' => $u['first_name'],
                    'matricule' => $u['matricule'],
                    'nombre_biens' => $u['nombre_biens'],
                ], $assignments['utilisateurs'] ?? []),
            ],
            'services' => [
                'total_biens' => $assignments['services_total'] ?? 0,
                'items' => array_map(fn($s) => [
                    'service_id' => $s['id'],
                    'nom' => $s['nom'],
                    'sigle' => $s['sigle'],
                    'nombre_biens' => $s['nombre_biens'],
                ], $assignments['services'] ?? []),
            ],
        ],
        'projets' => array_map(fn($p) => [
            'id' => $p['id'],
            'nom' => $p['nom'],
            'exercice' => $p['exercice'],
            'nombre_biens' => $p['nombre_biens'],
        ], $projects),
    ];
}

    /**
     * Formate un mouvement d'historique
     */
    public function buildHistoryItem(array $movement): array
    {
        return match ($movement['type']) {
            'ASSIGNMENT' => $this->buildAssignmentHistory($movement),
            'MAINTENANCE' => $this->buildMaintenanceHistory($movement),
            'EXIT' => $this->buildExitHistory($movement),
            'BSP' => $this->buildBspHistory($movement),
            default => $movement,
        };
    }

    /**
     * Formate un historique d'affectation
     */
    private function buildAssignmentHistory(array $movement): array
    {
        return [
            'type' => 'ASSIGNMENT',
            'id' => $movement['id'],
            // 'date_debut' => $movement['date_debut'],
            // 'date_fin' => $movement['date_fin'],
            // 'type_affectation' => $movement['type_affectation'],
            'detenteur' => $this->formatDetenteur(
                $movement['user_id'] ?? null,
                $movement['user_name'] ?? null,
                $movement['service_id'] ?? null,
                $movement['service_name'] ?? null
            ),
            'created_at' => $movement['created_at'],
        ];
    }

    /**
     * Formate un historique de maintenance
     */
    private function buildMaintenanceHistory(array $movement): array
    {
        return [
            'type' => 'MAINTENANCE',
            'id' => $movement['id'],
            'date_intervention' => $movement['date_intervention'],
            'date_recuperation' => $movement['date_recuperation'],
            'statut' => $movement['statut'],
            'motif' => $movement['motif'],
            'cout' => $movement['cout'],
            'created_at' => $movement['created_at'],
        ];
    }

    /**
     * Formate un historique de sortie
     */
    private function buildExitHistory(array $movement): array
    {
        return [
            'type' => 'EXIT',
            'id' => $movement['id'],
            // 'date_sortie' => $movement['date_sortie'],
            // 'motif_sortie' => $movement['motif_sortie'],
            // 'protocole_reference' => $movement['protocole_reference'],
            'detenteur' => $this->formatDetenteur(
                $movement['user_id'] ?? null,
                $movement['user_name'] ?? null,
                $movement['service_id'] ?? null,
                $movement['service_name'] ?? null
            ),
            'created_at' => $movement['created_at'],
        ];
    }

    /**
     * Formate un historique BSP
     */
    private function buildBspHistory(array $movement): array
    {
        return [
            'type' => 'BSP',
            'id' => $movement['id'],
            'numero' => $movement['numero'],
            // 'date_etablissement' => $movement['date_etablissement'],
            // 'date_retour_effective' => $movement['date_retour_effective'],
            // 'retour' => (bool) $movement['retour'],
            // 'quantite_servie' => $movement['quantite_servie'],
            'service' => $this->formatDetenteur(
                null,
                null,
                $movement['service_id'] ?? null,
                $movement['service_name'] ?? null
            ),
            'beneficiaire' => $movement['beneficiaire_name'] ?? null,
            'created_at' => $movement['created_at'],
        ];
    }




    /**
     * Formate le détenteur (user ou service)
     */
    private function formatDetenteur(?int $userId, ?string $userName, ?int $serviceId, ?string $serviceName): ?array
    {
        if ($userId && $userName) {
            return [
                'type' => 'USER',
                'id' => $userId,
                'nom' => $userName,
            ];
        }

        if ($serviceId && $serviceName) {
            return [
                'type' => 'SERVICE',
                'id' => $serviceId,
                'nom' => $serviceName,
            ];
        }

        return null;
    }

    /**
     * Formate un mouvement global
     */
    public function buildGlobalMovement(array $movement): array
    {
        return [
            'type' => $movement['type'],
            'id' => $movement['id'],
            'asset' => [
                'id' => $movement['asset_id'],
                'nom' => $movement['asset_nom'],
            ],
            'date_intervention' => $movement['date_debut'],
            'date_recuperation' => $movement['date_fin'],
            // 'created_at' => $movement['created_at'],
        ];
    }

    /**
     * Formate la réponse paginée
     */
    public function buildPaginatedResponse(array $items, int $total, int $page, int $limit): array
    {
        return [
            'data' => $items,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => (int) ceil($total / $limit),
                'has_next_page' => $page * $limit < $total,
                'has_previous_page' => $page > 1,
            ],
        ];
    }

    /**
     * Formate la réponse unifiée pour l'API unique /gestion_stock_bien
     */
    public function buildUnifiedResponse(array $stockData, ?array $statistics = null): array
    {
        $response = [
            'success' => true,
            'message' => 'Gestion du stock des biens récupérée avec succès',
            'data' => [],
        ];

        // Résumé du stock
        if (isset($stockData['summary'])) {
            $assignments = $stockData['assignments'] ?? [];
            $statuts = $stockData['statuts'] ?? [];
            $projects = $stockData['projects'] ?? [];
            $response['data']['summary'] = $this->buildStockSummary($stockData['summary'], $assignments, $statuts, $projects);
        }

        // Biens paginés
        if (isset($stockData['assets'])) {
            $assets = $stockData['assets']['data'];
            $pagination = $stockData['assets']['pagination'];
            
            $formattedAssets = array_map(
                fn($asset) => $this->buildAssetListItem($asset),
                $assets
            );

            $response['data']['assets'] = [
                'data' => $formattedAssets,
                'pagination' => [
                    'page' => $pagination['page'],
                    'limit' => $pagination['limit'],
                    'total' => $pagination['total'],
                    'pages' => $pagination['pages'],
                ],
            ];
        }

        return $response;
    }

    /**
     * Formate les statistiques selon le format demandé
     */
    private function buildStatistics(?array $statistics): array
    {
        if (!$statistics) {
            return [
                'by_category' => [],
                'by_type' => [],
                'by_etat' => [],
                'by_service' => [],
            ];
        }

        return [
            'by_category' => $statistics['by_category'] ?? [],
            'by_type' => $statistics['by_type'] ?? [],
            'by_etat' => $statistics['by_etat'] ?? [],
            'by_service' => $statistics['by_service'] ?? [],
        ];
    }
}
