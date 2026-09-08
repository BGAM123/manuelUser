<?php

namespace App\Service\RapportEtatRecapitulatif;

use App\Entity\Asset;
use App\Entity\Consumable;
use App\Repository\AssetRepository;
use App\Repository\ConsumableRepository;
use App\Repository\ServiceRepository;

/**
 * Service principal pour l'État Récapitulatif Mensuel.
 * 
 * Responsabilités :
 * - Orchestrer l'analyse des biens et consommables
 * - Calculer les stocks initiaux et finaux
 * - Agréger les entrées et sorties par période
 * - Formater la réponse selon le format demandé
 */
final class EtatRecapitulatifMensuelService
{
    public function __construct(
        private readonly AssetRepository $assetRepository,
        private readonly ConsumableRepository $consumableRepository,
        private readonly ServiceRepository $serviceRepository,
        private readonly EtatRecapitulatifAssetAnalyzer $assetAnalyzer,
        private readonly EtatRecapitulatifConsumableAnalyzer $consumableAnalyzer,
        private readonly EtatRecapitulatifStockCalculator $stockCalculator,
    ) {}

    /**
     * Génère l'état récapitulatif mensuel.
     *
     * @param \DateTimeInterface $periodeDebut Date de début de période
     * @param \DateTimeInterface $periodeFin Date de fin de période
     * @param array|null $serviceIds IDs des services (optionnel, tableau d'entiers)
     * @param int $page Numéro de page
     * @param int $limit Nombre d'éléments par page
     * @return array État récapitulatif mensuel avec pagination
     */
    public function generate(
        \DateTimeInterface $periodeDebut,
        \DateTimeInterface $periodeFin,
        ?array $serviceIds = null,
        int $page = 1,
        int $limit = 10
    ): array {
        // Validation des services si fournis
        if ($serviceIds !== null) {
            foreach ($serviceIds as $serviceId) {
                if (!$this->serviceRepository->find($serviceId)) {
                    throw new \InvalidArgumentException("Service avec ID {$serviceId} introuvable.");
                }
            }
        }

        // Récupérer les consommables actifs
        $consumables = $this->consumableRepository->createQueryBuilder('c')
            ->where('c.isDelete = false')
            ->leftJoin('c.category', 'cat')->addSelect('cat')
            ->leftJoin('c.assetType', 'at')->addSelect('at')
            ->orderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();

        // Analyser les consommables
        $consumableLines = $this->consumableAnalyzer->analyze(
            $consumables,
            $periodeDebut,
            $periodeFin,
            $serviceIds
        );

        // Numéroter
        $consumableLines = $this->applyNumerotation($consumableLines);

        // Pagination
        $total = count($consumableLines);
        $totalPages = (int) ceil($total / max(1, $limit));
        $offset = ($page - 1) * $limit;
        $paginatedLines = array_slice($consumableLines, $offset, $limit);

        return [
            'meta' => [
                'current_page' => $page,
                'limit' => $limit,
                'total_items' => $total,
                'total_pages' => $totalPages
            ],
            'data' => $paginatedLines
        ];
    }

    /**
     * Applique la numérotation séquentielle aux lignes.
     * 
     * @param array $lines Lignes à numéroter
     * @return array Lignes numérotées
     */
    private function applyNumerotation(array $lines): array
    {
        $numero = 1;
        foreach ($lines as &$line) {
            $line['numero'] = $numero++;
        }

        return $lines;
    }
}