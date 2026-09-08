<?php

namespace App\Service\Comptables\FicheStock;

use App\Entity\ConsumableTransfer;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Analyse les mouvements de consommables (entrées, transferts, BSP).
 * Logique adaptée de ConsumableTransferRepository::getMovementsByConsumableAndService().
 * Copie isolée volontairement afin de ne pas modifier le module existant.
 */
final class FicheStockMovementAnalyzer
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Récupère tous les mouvements pour un consommable et un service sur une période.
     *
     * @param int $consumableId ID du consommable
     * @param int $serviceId ID du service
     * @param \DateTimeInterface|null $dateDebut Date de début de période
     * @param \DateTimeInterface|null $dateFin Date de fin de période
     * @return array Mouvements normalisés
     */
    public function getMovements(int $consumableId, int $serviceId, ?\DateTimeInterface $dateDebut = null, ?\DateTimeInterface $dateFin = null): array
    {
        $conn = $this->entityManager->getConnection();

        // Entrées initiales (ConsumableEntry)
        $sqlEntries = "
            SELECT 
                'ENTRY' as type,
                NULL as statut,
                ce.quantite as quantite,
                NULL as service_source_id,
                s.id as service_destination_id,
                ss.nom as service_source_nom,
                s.nom as service_destination_nom,
                DATE_FORMAT(ce.date_entree, '%Y-%m-%d') as date,
                NULL as quantity_consumed,
                ce.observations
            FROM consumable_entry ce
            INNER JOIN service s ON s.id = ce.service_id
            LEFT JOIN service ss ON ss.id = NULL
            WHERE ce.consumable_id = :consumableId
            AND ce.service_id = :serviceId
            AND ce.is_delete = 0
        ";

        $params = ['consumableId' => $consumableId, 'serviceId' => $serviceId];

        if ($dateDebut) {
            $sqlEntries .= " AND ce.date_entree >= :dateDebut";
            $params['dateDebut'] = $dateDebut->format('Y-m-d');
        }
        if ($dateFin) {
            $sqlEntries .= " AND ce.date_entree <= :dateFin";
            $params['dateFin'] = $dateFin->format('Y-m-d');
        }

        $entries = $conn->executeQuery($sqlEntries, $params)->fetchAllAssociative();

        // Transferts reçus et envoyés
        $sqlTransfers = "
            SELECT 
                'TRANSFER' as type,
                ct.statut as statut,
                ct.quantite as quantite,
                ct.service_source_id as service_source_id,
                ct.service_destination_id as service_destination_id,
                ss.nom as service_source_nom,
                sd.nom as service_destination_nom,
                DATE_FORMAT(ct.date_transfert, '%Y-%m-%d') as date,
                ct.quantity_consumed as quantity_consumed,
                ct.observations
            FROM consumable_transfer ct
            LEFT JOIN service ss ON ss.id = ct.service_source_id
            LEFT JOIN service sd ON sd.id = ct.service_destination_id
            WHERE ct.consumable_id = :consumableId
            AND (ct.service_source_id = :serviceId OR ct.service_destination_id = :serviceId)
            AND ct.is_delete = 0
        ";

        if ($dateDebut) {
            $sqlTransfers .= " AND ct.date_transfert >= :dateDebut";
        }
        if ($dateFin) {
            $sqlTransfers .= " AND ct.date_transfert <= :dateFin";
        }

        $transfers = $conn->executeQuery($sqlTransfers, $params)->fetchAllAssociative();

        return array_merge($entries, $transfers);
    }

    /**
     * Récupère les mouvements antérieurs à la période pour calculer le stock initial.
     *
     * @param int $consumableId ID du consommable
     * @param int $serviceId ID du service
     * @param \DateTimeInterface $dateDebut Date de début de période
     * @return array Mouvements antérieurs
     */
    public function getMovementsBeforePeriod(int $consumableId, int $serviceId, \DateTimeInterface $dateDebut): array
    {
        $conn = $this->entityManager->getConnection();

        // Entrées initiales avant la période
        $sqlEntries = "
            SELECT 
                'ENTRY' as type,
                NULL as statut,
                ce.quantite as quantite,
                NULL as service_source_id,
                s.id as service_destination_id,
                ss.nom as service_source_nom,
                s.nom as service_destination_nom,
                DATE_FORMAT(ce.date_entree, '%Y-%m-%d') as date,
                NULL as quantity_consumed,
                ce.observations
            FROM consumable_entry ce
            INNER JOIN service s ON s.id = ce.service_id
            LEFT JOIN service ss ON ss.id = NULL
            WHERE ce.consumable_id = :consumableId
            AND ce.service_id = :serviceId
            AND ce.is_delete = 0
            AND ce.date_entree < :dateDebut
        ";

        $params = ['consumableId' => $consumableId, 'serviceId' => $serviceId, 'dateDebut' => $dateDebut->format('Y-m-d')];
        $entries = $conn->executeQuery($sqlEntries, $params)->fetchAllAssociative();

        // Transferts avant la période
        $sqlTransfers = "
            SELECT 
                'TRANSFER' as type,
                ct.statut as statut,
                ct.quantite as quantite,
                ct.service_source_id as service_source_id,
                ct.service_destination_id as service_destination_id,
                ss.nom as service_source_nom,
                sd.nom as service_destination_nom,
                DATE_FORMAT(ct.date_transfert, '%Y-%m-%d') as date,
                ct.quantity_consumed as quantity_consumed,
                ct.observations
            FROM consumable_transfer ct
            LEFT JOIN service ss ON ss.id = ct.service_source_id
            LEFT JOIN service sd ON sd.id = ct.service_destination_id
            WHERE ct.consumable_id = :consumableId
            AND (ct.service_source_id = :serviceId OR ct.service_destination_id = :serviceId)
            AND ct.is_delete = 0
            AND ct.date_transfert < :dateDebut
        ";

        $transfers = $conn->executeQuery($sqlTransfers, $params)->fetchAllAssociative();

        return array_merge($entries, $transfers);
    }

    /**
     * Trie les mouvements chronologiquement.
     *
     * @param array $movements Mouvements à trier
     * @return array Mouvements triés
     */
    public function sortMovements(array $movements): array
    {
        usort($movements, function ($a, $b) {
            $dateA = $a['date'] ?? '';
            $dateB = $b['date'] ?? '';
            
            if ($dateA === $dateB) {
                // Si même date, trier par ID si disponible
                $idA = $a['id'] ?? 0;
                $idB = $b['id'] ?? 0;
                return $idA <=> $idB;
            }
            
            return strcmp($dateA, $dateB);
        });

        return $movements;
    }
}
