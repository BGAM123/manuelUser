<?php

namespace App\Service\Comptables\FicheStock;

use App\Entity\Consumable;
use App\Entity\Service;

/**
 * Service principal d'orchestration pour la Fiche de Stock.
 * Coordonne les analyseurs, calculateurs et formateurs.
 */
final class FicheStockService
{
    public function __construct(
        private readonly FicheStockConsumableAnalyzer $consumableAnalyzer,
        private readonly FicheStockMovementAnalyzer $movementAnalyzer,
        private readonly FicheStockStockCalculator $stockCalculator,
        private readonly FicheStockFormatter $formatter,
    ) {
    }

    /**
     * Génère les fiches de stock selon les filtres avec pagination.
     *
     * @param int|null $consumableId Filtre par consommable
     * @param int|null $serviceId Filtre par service
     * @param \DateTimeInterface|null $dateDebut Date de début de période
     * @param \DateTimeInterface|null $dateFin Date de fin de période
     * @param string|null $search Filtre par recherche
     * @param int $page Page
     * @param int $limit Limite par page
     * @return array Fiches de stock générées avec pagination
     */
    public function generateFiches(
        ?int $consumableId = null,
        ?int $serviceId = null,
        ?\DateTimeInterface $dateDebut = null,
        ?\DateTimeInterface $dateFin = null,
        ?string $search = null,
        int $page = 1,
        int $limit = 10
    ): array {
        // Validation des filtres
        if ($consumableId && !$this->consumableAnalyzer->consumableExists($consumableId)) {
            throw new \InvalidArgumentException('Consommable introuvable.');
        }

        if ($serviceId && !$this->consumableAnalyzer->serviceExists($serviceId)) {
            throw new \InvalidArgumentException('Service introuvable.');
        }

        // Compter le total de fiches pour la pagination
        $total = $this->consumableAnalyzer->countTotalFiches($consumableId, $serviceId, $search);

        // Récupérer les consommables concernés avec pagination
        $consumables = $this->consumableAnalyzer->getConsumables($consumableId, $serviceId, $search, $page, $limit);

        $fiches = [];

        foreach ($consumables as $consumable) {
            // Récupérer les services concernés pour ce consommable
            $services = $this->consumableAnalyzer->getServicesForConsumable($consumable->getId(), $serviceId);

            foreach ($services as $service) {
                $fiche = $this->generateFicheForConsumableAndService(
                    $consumable,
                    $service,
                    $dateDebut,
                    $dateFin
                );

                if ($fiche !== null) {
                    $fiches[] = $fiche;
                }
            }
        }

        return $this->formatter->formatResponse($fiches, $page, $limit, $total);
    }

    /**
     * Génère une fiche de stock pour un consommable et un service donnés.
     *
     * @param Consumable $consumable Consommable
     * @param Service $service Service
     * @param \DateTimeInterface|null $dateDebut Date de début de période
     * @param \DateTimeInterface|null $dateFin Date de fin de période
     * @return array|null Fiche de stock ou null si aucun mouvement
     */
    private function generateFicheForConsumableAndService(
        Consumable $consumable,
        Service $service,
        ?\DateTimeInterface $dateDebut = null,
        ?\DateTimeInterface $dateFin = null
    ): ?array {
        $consumableId = $consumable->getId();
        $serviceId = $service->getId();

        // Récupérer les mouvements de la période
        $movements = $this->movementAnalyzer->getMovements($consumableId, $serviceId, $dateDebut, $dateFin);
        $movements = $this->movementAnalyzer->sortMovements($movements);

        // Si aucun mouvement dans la période, vérifier le stock initial
        if (empty($movements)) {
            if ($dateDebut) {
                $beforeMovements = $this->movementAnalyzer->getMovementsBeforePeriod($consumableId, $serviceId, $dateDebut);
                $initialStock = $this->stockCalculator->calculateInitialStock($beforeMovements, $serviceId);

                if ($initialStock > 0) {
                    // Retourner une fiche avec seulement le stock initial
                    return $this->formatter->formatFiche(
                        $consumable,
                        $service,
                        []
                    );
                }
            }
            return null;
        }

        // Calculer le stock initial
        $initialStock = 0.0;
        if ($dateDebut) {
            $beforeMovements = $this->movementAnalyzer->getMovementsBeforePeriod($consumableId, $serviceId, $dateDebut);
            $initialStock = $this->stockCalculator->calculateInitialStock($beforeMovements, $serviceId);
        }

        // Calculer le stock évolutif
        $stocks = $this->stockCalculator->calculateEvolutionaryStock($initialStock, $movements, $serviceId);

        // Formater les mouvements
        $formattedMovements = $this->formatter->formatMovements(
            $movements,
            $stocks,
            $serviceId,
            $this->stockCalculator
        );

        // Formater la fiche complète
        return $this->formatter->formatFiche(
            $consumable,
            $service,
            $formattedMovements
        );
    }
}
