<?php

namespace App\Service\Comptables\LivreJournal;

use App\Entity\Asset;
use App\Entity\Consumable;
use App\Repository\AssetRepository;
use App\Repository\ConsumableRepository;

/**
 * Service principal pour le Livre Journal.
 * 
 * Responsabilités :
 * - Récupérer et analyser les biens
 * - Récupérer et analyser les consommables
 * - Fusionner les résultats
 * - Appliquer les filtres
 * - Trier les résultats
 * - Paginer les résultats
 * - Numéroter les lignes
 */
final class LivreJournalService
{
    public function __construct(
        private readonly AssetRepository $assetRepository,
        private readonly ConsumableRepository $consumableRepository,
        private readonly LivreJournalAssetAnalyzer $assetAnalyzer,
        private readonly LivreJournalConsumableAnalyzer $consumableAnalyzer,
        private readonly LivreJournalFormatter $formatter,
    ) {}

    /**
     * Génère le Livre Journal.
     * 
     * @param array $filters Filtres (type, service, categorie, dateDebut, dateFin, exercice)
     * @param int $page Page actuelle
     * @param int $limit Nombre d'éléments par page
     * @return array Données du Livre Journal avec pagination
     */
    public function generate(array $filters = [], int $page = 1, int $limit = 10): array
    {
        // Récupérer les biens et consommables selon les filtres
        $assets = $this->getAssets($filters);
        $consumables = $this->getConsumables($filters);

        // Analyser les biens
        $assetLines = [];
        foreach ($assets as $asset) {
            $rawData = $this->assetAnalyzer->analyze($asset);
            $assetLines[] = $this->formatter->formatLine($rawData);
        }

        // Analyser les consommables
        $consumableLines = [];
        foreach ($consumables as $consumable) {
            $rawData = $this->consumableAnalyzer->analyze($consumable);
            $consumableLines[] = $this->formatter->formatLine($rawData);
        }

        // Fusionner les résultats
        $allLines = array_merge($assetLines, $consumableLines);

        // Appliquer les filtres supplémentaires (type, date)
        $allLines = $this->applyFilters($allLines, $filters);

        // Trier par createdAt (date)
        usort($allLines, function ($a, $b) {
            return strcmp($a['date'] ?? '', $b['date'] ?? '');
        });

        // Calculer le total avant pagination pour numérotation globale
        $total = count($allLines);

        // Paginer
        $offset = ($page - 1) * $limit;
        $paginatedLines = array_slice($allLines, $offset, $limit);

        // Appliquer la numérotation (globale sur toutes les pages)
        $paginatedLines = $this->formatter->applyNumerotation($paginatedLines, $offset);

        return [
            'data' => $paginatedLines,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => (int) ceil($total / $limit),
            ],
        ];
    }

    /**
     * Récupère les biens selon les filtres.
     * 
     * @param array $filters Filtres
     * @return array<Asset> Biens filtrés
     */
    private function getAssets(array $filters): array
    {
        $qb = $this->assetRepository->createQueryBuilder('a')
            ->where('a.isDelete = :isDelete')
            ->setParameter('isDelete', false)
            ->leftJoin('a.categories', 'c')
            ->leftJoin('a.services', 's')
            ->leftJoin('a.assignments', 'ass')
            ->leftJoin('a.sortie', 'ae')
            ->addSelect('c', 's', 'ass', 'ae');

        // Filtre par type
        if (isset($filters['type']) && in_array($filters['type'], ['BIEN', 'CONSOMMABLE'])) {
            if ($filters['type'] === 'BIEN') {
                // On garde les biens (filtre déjà appliqué par défaut)
            } else {
                // Si type = CONSOMMABLE, on ne retourne pas de biens
                return [];
            }
        }

        // Filtre par service
        if (isset($filters['service']) && !empty($filters['service'])) {
            $serviceIds = is_array($filters['service']) ? $filters['service'] : [$filters['service']];
            $qb->andWhere('s.id IN (:serviceIds)')
               ->setParameter('serviceIds', $serviceIds);
        }

        // Filtre par catégorie
        if (isset($filters['categorie']) && !empty($filters['categorie'])) {
            $categoryIds = is_array($filters['categorie']) ? $filters['categorie'] : [$filters['categorie']];
            $qb->andWhere('c.id IN (:categoryIds)')
               ->setParameter('categoryIds', $categoryIds);
        }

        // Filtre par date de début
        if (isset($filters['dateDebut']) && $filters['dateDebut']) {
            $qb->andWhere('a.createdAt >= :dateDebut')
               ->setParameter('dateDebut', new \DateTime($filters['dateDebut']));
        }

        // Filtre par date de fin
        if (isset($filters['dateFin']) && $filters['dateFin']) {
            $qb->andWhere('a.createdAt <= :dateFin')
               ->setParameter('dateFin', new \DateTime($filters['dateFin']));
        }

        // Filtre par exercice (via les projets)
        if (isset($filters['exercice']) && $filters['exercice']) {
            $qb->leftJoin('a.projects', 'p')
               ->andWhere('p.exercice = :exercice')
               ->setParameter('exercice', $filters['exercice']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère les consommables selon les filtres.
     * 
     * @param array $filters Filtres
     * @return array<Consumable> Consommables filtrés
     */
    private function getConsumables(array $filters): array
    {
        $qb = $this->consumableRepository->createQueryBuilder('c')
            ->where('c.isDelete = :isDelete')
            ->setParameter('isDelete', false)
            ->leftJoin('c.category', 'cat')
            ->leftJoin('c.service', 's')
            ->addSelect('cat', 's');

        // Filtre par type
        if (isset($filters['type']) && in_array($filters['type'], ['BIEN', 'CONSOMMABLE'])) {
            if ($filters['type'] === 'BIEN') {
                // Si type = BIEN, on ne retourne pas de consommables
                return [];
            }
            // Si type = CONSOMMABLE, on garde les consommables (filtre déjà appliqué par défaut)
        }

        // Filtre par service
        if (isset($filters['service']) && !empty($filters['service'])) {
            $serviceIds = is_array($filters['service']) ? $filters['service'] : [$filters['service']];
            $qb->andWhere('s.id IN (:serviceIds)')
               ->setParameter('serviceIds', $serviceIds);
        }

        // Filtre par catégorie
        if (isset($filters['categorie']) && !empty($filters['categorie'])) {
            $categoryIds = is_array($filters['categorie']) ? $filters['categorie'] : [$filters['categorie']];
            $qb->andWhere('cat.id IN (:categoryIds)')
               ->setParameter('categoryIds', $categoryIds);
        }

        // Filtre par date de début
        if (isset($filters['dateDebut']) && $filters['dateDebut']) {
            $qb->andWhere('c.createdAt >= :dateDebut')
               ->setParameter('dateDebut', new \DateTime($filters['dateDebut']));
        }

        // Filtre par date de fin
        if (isset($filters['dateFin']) && $filters['dateFin']) {
            $qb->andWhere('c.createdAt <= :dateFin')
               ->setParameter('dateFin', new \DateTime($filters['dateFin']));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Applique les filtres supplémentaires sur les lignes déjà analysées.
     * 
     * @param array $lines Lignes du Livre Journal
     * @param array $filters Filtres
     * @return array Lignes filtrées
     */
    private function applyFilters(array $lines, array $filters): array
    {
        // Les filtres de base (service, categorie, date) sont déjà appliqués dans les requêtes
        // Cette méthode peut être étendue pour des filtres plus complexes

        return $lines;
    }
}
