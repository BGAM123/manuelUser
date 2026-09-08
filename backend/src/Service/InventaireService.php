<?php

namespace App\Service;

use App\Entity\Asset;
use App\Entity\AssetAssignment;
use App\Entity\Category;
use App\Entity\User;
use App\Repository\AssetAssignmentRepository;
use App\Repository\AssetRepository;
use App\Repository\CategoryRepository;
use App\Service\DefaultAssetReferencesService;

/**
 * Service pour générer l'inventaire de tous les biens non supprimés,
 * regroupés par catégorie.
 */
final class InventaireService
{
    public function __construct(
        private readonly AssetRepository $assetRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly AssetAssignmentRepository $assetAssignmentRepository,
        private readonly DefaultAssetReferencesService $defaultAssetReferences,
    ) {}

    /**
     * Génère l'inventaire complet.
     *
     * @param User $user Utilisateur connecté (pour récupérer le contexte administratif)
     * @param list<int>|null $categoryIds IDs optionnels des catégories à inclure
     * @param int $page Page actuelle
     * @param int $limit Nombre d'éléments par page
     * @return array Structure d'inventaire groupée par catégorie avec métadonnées de pagination
     */
    public function generateInventory(User $user, ?array $categoryIds = null, int $page = 1, int $limit = 10, ?string $statut = null): array
    {
        // Récupérer le service de l'utilisateur
        $userService = $user->getService();

        // Contexte administratif
        $context = [
            'date' => (new \DateTimeImmutable())->format('Y-m-d'),
            'service' => null,
            'region' => null,
            'departement' => null,
            'arrondissement' => null,
        ];

        // Si l'utilisateur a un service, remplir le contexte
        if ($userService) {
            $context['service'] = [
                'id' => $userService->getId(),
                'nom' => $userService->getNom(),
            ];

            $context['region'] = $userService->getRegion() ? [
                'id' => $userService->getRegion()->getId(),
                'nom' => $userService->getRegion()->getNom(),
            ] : null;

            $context['departement'] = $userService->getDepartement() ? [
                'id' => $userService->getDepartement()->getId(),
                'nom' => $userService->getDepartement()->getNom(),
            ] : null;

            $context['arrondissement'] = $userService->getArrondissement() ? [
                'id' => $userService->getArrondissement()->getId(),
                'nom' => $userService->getArrondissement()->getNom(),
            ] : null;
        }

        // Un id de catégorie fourni peut pointer vers une catégorie soft-deletée : on résout
        // chaque id vers une catégorie active (ou la catégorie par défaut) avant de filtrer,
        // pour ne jamais traiter une catégorie supprimée comme active.
        $categoryResolutions = null;
        $resolvedCategoryIds = null;
        if (null !== $categoryIds) {
            $categoryResolutions = [];
            $resolvedIds = [];
            foreach ($categoryIds as $requestedCategoryId) {
                $resolvedCategory = $this->defaultAssetReferences->resolveActiveCategoryOrDefault($requestedCategoryId);
                $resolvedIds[] = $resolvedCategory->getId();
                $categoryResolutions[] = $this->defaultAssetReferences->describeCategoryResolution($requestedCategoryId, $resolvedCategory);
            }
            $resolvedCategoryIds = array_values(array_unique($resolvedIds));
        }

        // Récupérer les biens paginés
        $assets = $this->assetRepository->findPaginatedForInventory($page, $limit, $resolvedCategoryIds, $statut);
        $total = $this->assetRepository->countForInventory($resolvedCategoryIds, $statut);

        // Regrouper par catégorie
        $categories = $this->groupAssetsByCategory($assets);

        $meta = [
            'current_page' => $page,
            'limit' => $limit,
            'total_items' => $total,
            'total_pages' => (int) ceil($total / max(1, $limit)),
        ];

        $filters = [];
        if (null !== $categoryResolutions) {
            $filters['category_filter'] = $categoryResolutions;
        }
        
        if (!empty($filters)) {
            $meta['filters'] = $filters;
        }

        if (null !== $statut) {
            $filters['statut'] = $statut;
        }
        $meta['filters'] = $filters;

        return [
            'date' => $context['date'],
            'service' => $context['service'],
            'region' => $context['region'],
            'departement' => $context['departement'],
            'arrondissement' => $context['arrondissement'],
            'categories' => $categories,
            'meta' => $meta,
        ];
    }

    /**
     * Regroupe les biens par catégorie avec les informations détaillées.
     * Les biens sont triés par type (nom ASC) puis par nom du bien (nom ASC).
     *
     * @param list<Asset> $assets
     * @return list<array>
     */
    private function groupAssetsByCategory(array $assets): array
    {
        // Trier les biens par type puis par nom
        $sortedAssets = $this->sortAssetsByTypeAndName($assets);

        $grouped = [];

        foreach ($sortedAssets as $asset) {
            $categories = $asset->getCategories();

            if ($categories->isEmpty()) {
                // Bien sans catégorie
                if (!isset($grouped[0])) {
                    $grouped[0] = [
                        'categorie' => null,
                        'biens' => [],
                    ];
                }
                $grouped[0]['biens'][] = $this->buildAssetData($asset);
            } else {
                // Pour chaque catégorie du bien
                foreach ($categories as $category) {
                    $catId = $category->getId();
                    if (!isset($grouped[$catId])) {
                        $grouped[$catId] = [
                            'categorie' => [
                                'id' => $category->getId(),
                                'nom' => $category->getNom(),
                                'ordre' =>$category->getOrdre()
                            ],
                            'biens' => [],
                        ];
                    }
                    $grouped[$catId]['biens'][] = $this->buildAssetData($asset);
                }
            }
        }

        // Trier les catégories par ordre
        uasort($grouped, function ($a, $b) {
            $ordreA = $a['categorie']['ordre'] ?? PHP_INT_MAX;
            $ordreB = $b['categorie']['ordre'] ?? PHP_INT_MAX;
            return $ordreA <=> $ordreB;
        });

        // Retourner comme liste (pas de clés numériques)
        return array_values($grouped);
    }

    /**
     * Trie les assets par type (nom ASC) puis par nom du bien (nom ASC).
     *
     * @param list<Asset> $assets
     * @return list<Asset>
     */
    private function sortAssetsByTypeAndName(array $assets): array
    {
        usort($assets, function (Asset $a, Asset $b): int {
            // Récupérer le premier type de chaque bien
            $typeA = $a->getAssetTypes()->isEmpty() ? null : $a->getAssetTypes()->first();
            $typeB = $b->getAssetTypes()->isEmpty() ? null : $b->getAssetTypes()->first();

            // Comparaison par type (nom ASC, les types null en premier)
            if ($typeA && $typeB) {
                $typeComparison = strcmp($typeA->getNom(), $typeB->getNom());
                if (0 !== $typeComparison) {
                    return $typeComparison;
                }
            } elseif ($typeA && !$typeB) {
                return 1; // typeB null vient avant typeA
            } elseif (!$typeA && $typeB) {
                return -1; // typeA null vient avant typeB
            }

            // Si même type, comparaison par nom du bien (nom ASC)
            return strcmp($a->getNom(), $b->getNom());
        });

        return $assets;
    }

    /**
     * Construit les données d'un bien pour la réponse inventaire.
     */
    private function buildAssetData(Asset $asset): array
    {
        // Récupérer le détenteur actuel (affectation active sans date de fin)
        $currentAssignment = $this->getCurrentAssignment($asset);
        $detenteur = null;
        if ($currentAssignment && $currentAssignment->getUser()) {
            $user = $currentAssignment->getUser();
            $detenteur = [
                'id' => $user->getId(),
                'nom' => $user->getLastName(),
                'prenom' => $user->getFirstName(),
                'cni' => $user->getCni(),
                'matricule' => $user->getMatricule(),
                'service' => $user->getService() ? [
                    'id' => $user->getService()->getId(),
                    'nom' => $user->getService()->getNom(),
                ] : null,
            ];
        }

        // Récupérer le premier type de bien (AssetType)
        $primaryType = null;
        if (!$asset->getAssetTypes()->isEmpty()) {
            $assetType = $asset->getAssetTypes()->first();
            $primaryType = [
                'id' => $assetType->getId(),
                'nom' => $assetType->getNom(),
            ];
        }

        // Récupérer le premier état du bien
        $etat = null;
        if (!$asset->getEtatBiens()->isEmpty()) {
            $etatBien = $asset->getEtatBiens()->first();
            $etat = $etatBien->getNom();
        }

        // Construire les données du projet
        $project = null;
        if (!$asset->getProjects()->isEmpty()) {
            $assetProject = $asset->getProjects()->first();
            $project = [
                'id' => $assetProject->getId(),
                'nom' => $assetProject->getNom(),
                'date_debut' => $assetProject->getDateDebut()?->format('Y-m-d'),
                'date_fin' => $assetProject->getDateFinPrevue()?->format('Y-m-d'),
                'duree' => $this->calculateProjectDuration($assetProject->getDateDebut(), $assetProject->getDateFinPrevue()),
            ];
        }
        

        // Extraire l'année d'acquisition
        $anneeAcquisition = null;
        if ($asset->getDateAcquisition()) {
            $anneeAcquisition = (int) $asset->getDateAcquisition()->format('Y');
        }

        return [
            'id' => $asset->getId(),
            'nom' => $asset->getNom(),
            'type' => $primaryType,
            'valeur' => $asset->getValeur() ? (float) $asset->getValeur() : null,
            'annee_acquisition' => $anneeAcquisition,
            'etat' => $etat,
            'description' => $asset->getDescription(),
            'imputation_budgetaire' => $asset->getValeur() ? (float) $asset->getValeur() : null,
            'projet' => $project,
            'champs' => $this->buildChamps($asset), 
            'detenteur' => $detenteur,
        ];
    }

    private function buildChamps(Asset $asset): array
    {
        $result = [];

        // ✅ Récupérer les champs du bien
        foreach ($asset->getChamps() as $champ) {
            if ($champ->isDelete()) {
                continue;
            }

            // ✅ Récupérer les inputs du champ
            $inputs = [];
            foreach ($champ->getInputs() as $input) {
                if (!$input->isDelete()) {
                    $inputs[] = [
                        'id' => $input->getId(),
                        'valeur' => $input->getValeur(),
                        // 'createdAt' => $input->getCreatedAt()?->format('Y-m-d H:i:s'),
                        // 'updatedAt' => $input->getUpdatedAt()?->format('Y-m-d H:i:s'),
                    ];
                }
            }

            $result[] = [
                'id' => $champ->getId(),
                'nom' => $champ->getNom(),
                // 'type' => $champ->getType(),
                // 'sousType' => $champ->getSubtype(),
                // 'option' => $champ->getOption(),
                'inputs' => $inputs,
            ];
        }

        return $result;
    }

    /**
     * Récupère l'affectation actuelle d'un bien (sans date de fin).
     */
    private function getCurrentAssignment(Asset $asset): ?AssetAssignment
    {
        $assignments = $asset->getAssignments();

        foreach ($assignments as $assignment) {
            if (!$assignment->isDelete() && null === $assignment->getDateFin()) {
                return $assignment;
            }
        }

        return null;
    }

    /**
     * Calcule la durée entre deux dates.
     *
     * @param \DateTimeImmutable|null $dateDebut
     * @param \DateTimeImmutable|null $dateFin
     * @return string|null Format: "X ans", "X mois", etc.
     */
    private function calculateProjectDuration(?\DateTimeImmutable $dateDebut, ?\DateTimeImmutable $dateFin): ?string
    {
        if (!$dateDebut || !$dateFin) {
            return null;
        }

        try {
            $interval = $dateFin->diff($dateDebut);

            if ($interval->y > 0) {
                return sprintf('%d an%s', $interval->y, $interval->y > 1 ? 's' : '');
            } elseif ($interval->m > 0) {
                return sprintf('%d mois', $interval->m);
            } elseif ($interval->d > 0) {
                return sprintf('%d jour%s', $interval->d, $interval->d > 1 ? 's' : '');
            }

            return '0 jour';
        } catch (\Exception) {
            return null;
        }
    }
}
