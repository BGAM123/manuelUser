<?php

namespace App\Service\Comptables\FicheStock;

use App\Entity\Consumable;
use App\Repository\ConsumableRepository;
use App\Repository\ServiceRepository;
use Doctrine\DBAL\Connection;

/**
 * Analyse les consommables concernés par la fiche de stock.
 * Logique adaptée de ConsumableRepository::findServicesWithStock().
 * Copie isolée volontairement afin de ne pas modifier le module existant.
 */
final class FicheStockConsumableAnalyzer
{
    public function __construct(
        private readonly ConsumableRepository $consumableRepository,
        private readonly ServiceRepository $serviceRepository,
        private readonly Connection $connection,
    ) {
    }

    /**
     * Compte le nombre total de fiches (combinaisons consommable-service).
     *
     * @param int|null $consumableId Filtre par consommable
     * @param int|null $serviceId Filtre par service
     * @param string|null $search Filtre par recherche
     * @return int Nombre total de fiches
     */
    public function countTotalFiches(?int $consumableId = null, ?int $serviceId = null, ?string $search = null): int
    {
        // Si consumableId est spécifié, compter les services pour ce consommable
        if ($consumableId) {
            if ($serviceId) {
                // Un seul service spécifié = une seule fiche
                $consumable = $this->consumableRepository->find($consumableId);
                $service = $this->serviceRepository->find($serviceId);
                return ($consumable && !$consumable->isDelete() && $service) ? 1 : 0;
            }
            // Compter les services ayant des mouvements pour ce consommable
            $sql = "
                SELECT COUNT(DISTINCT s.id) as total
                FROM service s
                WHERE s.is_active = 1
                AND (
                    EXISTS (
                        SELECT 1 FROM consumable_entry ce 
                        WHERE ce.consumable_id = :consumableId 
                        AND ce.service_id = s.id 
                        AND ce.is_delete = 0
                    )
                    OR EXISTS (
                        SELECT 1 FROM consumable_transfer ct 
                        WHERE ct.consumable_id = :consumableId 
                        AND ct.service_destination_id = s.id 
                        AND ct.is_delete = 0
                    )
                    OR EXISTS (
                        SELECT 1 FROM consumable_transfer ct 
                        WHERE ct.consumable_id = :consumableId 
                        AND ct.service_source_id = s.id 
                        AND ct.is_delete = 0
                    )
                )
            ";
            $result = $this->connection->executeQuery($sql, ['consumableId' => $consumableId])->fetchAssociative();
            return (int) ($result['total'] ?? 0);
        }

        // Si serviceId est spécifié, compter les consommables pour ce service
        if ($serviceId) {
            $qb = $this->consumableRepository->createQueryBuilder('c')
                ->select('COUNT(DISTINCT c.id)')
                ->where('c.isDelete = false');

            if ($search && '' !== $search) {
                $qb->andWhere('c.nom LIKE :search')
                   ->setParameter('search', '%' . $search . '%');
            }

            // Filtrer les consommables qui ont des mouvements pour ce service
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->exists(
                        'SELECT 1 FROM App\Entity\ConsumableEntry ce WHERE ce.consumable = c.id AND ce.service = :serviceId AND ce.isDelete = false'
                    ),
                    $qb->expr()->exists(
                        'SELECT 1 FROM App\Entity\ConsumableTransfer ct WHERE ct.consumable = c.id AND ct.serviceDestination = :serviceId AND ct.isDelete = false'
                    ),
                    $qb->expr()->exists(
                        'SELECT 1 FROM App\Entity\ConsumableTransfer ct WHERE ct.consumable = c.id AND ct.serviceSource = :serviceId AND ct.isDelete = false'
                    )
                )
            )->setParameter('serviceId', $serviceId);

            return (int) $qb->getQuery()->getSingleScalarResult();
        }

        // Compter toutes les combinaisons consommable-service
        $sql = "
            SELECT COUNT(DISTINCT CONCAT(c.id, '-', s.id)) as total
            FROM consumable c
            INNER JOIN service s ON s.is_active = 1
            WHERE c.is_delete = 0
            AND (
                EXISTS (
                    SELECT 1 FROM consumable_entry ce 
                    WHERE ce.consumable_id = c.id 
                    AND ce.service_id = s.id 
                    AND ce.is_delete = 0
                )
                OR EXISTS (
                    SELECT 1 FROM consumable_transfer ct 
                    WHERE ct.consumable_id = c.id 
                    AND ct.service_destination_id = s.id 
                    AND ct.is_delete = 0
                )
                OR EXISTS (
                    SELECT 1 FROM consumable_transfer ct 
                    WHERE ct.consumable_id = c.id 
                    AND ct.service_source_id = s.id 
                    AND ct.is_delete = 0
                )
            )
        ";

        $params = [];
        if ($search && '' !== $search) {
            $sql .= " AND c.nom LIKE :search";
            $params['search'] = '%' . $search . '%';
        }

        $result = $this->connection->executeQuery($sql, $params)->fetchAssociative();
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Récupère les consommables concernés selon les filtres avec pagination.
     *
     * @param int|null $consumableId Filtre par consommable
     * @param int|null $serviceId Filtre par service
     * @param string|null $search Filtre par recherche
     * @param int $page Page
     * @param int $limit Limite par page
     * @return array Consommables filtrés
     */
    public function getConsumables(?int $consumableId = null, ?int $serviceId = null, ?string $search = null, int $page = 1, int $limit = 10): array
    {
        $qb = $this->consumableRepository->createQueryBuilder('c')
            ->leftJoin('c.category', 'cat')->addSelect('cat')
            ->where('c.isDelete = false');

        if ($consumableId) {
            $qb->andWhere('c.id = :consumableId')
               ->setParameter('consumableId', $consumableId);
        }

        if ($search && '' !== $search) {
            $qb->andWhere('c.nom LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        $qb->orderBy('c.nom', 'ASC');

        // Pagination
        $qb->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère les services concernés pour un consommable.
     *
     * @param int $consumableId ID du consommable
     * @param int|null $serviceId Filtre par service
     * @return array Services concernés
     */
    public function getServicesForConsumable(int $consumableId, ?int $serviceId = null): array
    {
        if ($serviceId) {
            $service = $this->serviceRepository->find($serviceId);
            return $service ? [$service] : [];
        }

        // Récupérer tous les services ayant des mouvements pour ce consommable
        $sql = "
            SELECT DISTINCT s.id, s.nom 
            FROM service s
            WHERE s.is_active = 1
            AND (
                EXISTS (
                    SELECT 1 FROM consumable_entry ce 
                    WHERE ce.consumable_id = :consumableId 
                    AND ce.service_id = s.id 
                    AND ce.is_delete = 0
                )
                OR EXISTS (
                    SELECT 1 FROM consumable_transfer ct 
                    WHERE ct.consumable_id = :consumableId 
                    AND ct.service_destination_id = s.id 
                    AND ct.is_delete = 0
                )
                OR EXISTS (
                    SELECT 1 FROM consumable_transfer ct 
                    WHERE ct.consumable_id = :consumableId 
                    AND ct.service_source_id = s.id 
                    AND ct.is_delete = 0
                )
            )
            ORDER BY s.nom ASC
        ";

        $services = $this->connection->executeQuery($sql, ['consumableId' => $consumableId])->fetchAllAssociative();

        return array_map(function ($row) {
            return $this->serviceRepository->find($row['id']);
        }, $services);
    }

    /**
     * Vérifie si un consommable existe.
     *
     * @param int $consumableId ID du consommable
     * @return bool
     */
    public function consumableExists(int $consumableId): bool
    {
        $consumable = $this->consumableRepository->find($consumableId);
        return $consumable !== null && !$consumable->isDelete();
    }

    /**
     * Vérifie si un service existe.
     *
     * @param int $serviceId ID du service
     * @return bool
     */
    public function serviceExists(int $serviceId): bool
    {
        $service = $this->serviceRepository->find($serviceId);
        return $service !== null;
    }
}
