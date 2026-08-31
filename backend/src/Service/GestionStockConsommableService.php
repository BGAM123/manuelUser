<?php

namespace App\Service;

use App\Repository\ConsumableEntryRepository;
use App\Repository\ConsumableTransferRepository;
use App\Repository\ConsumableRepository;
use Doctrine\ORM\EntityManagerInterface;

class GestionStockConsommableService
{
    public function __construct(
        private readonly ConsumableRepository $consumableRepository,
        private readonly ConsumableEntryRepository $consumableEntryRepository,
        private readonly ConsumableTransferRepository $consumableTransferRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Calcule le stock actuel d'un consommable pour un service donné
     */
    public function getStock(int $consumableId, int $serviceId): string
    {
        $stockInitial = $this->consumableEntryRepository->sumQuantiteByConsumableAndService($consumableId, $serviceId);
        $transfertsRecus = $this->consumableTransferRepository->sumQuantiteReceivedByConsumableAndService($consumableId, $serviceId);
        $transfertsEffectues = $this->consumableTransferRepository->sumQuantiteSentByConsumableAndService($consumableId, $serviceId);
        $sorties = $this->consumableTransferRepository->sumSortiesByConsumableAndService($consumableId, $serviceId);

        $stockActuel = bcadd($stockInitial, $transfertsRecus, 2);
        $stockActuel = bcsub($stockActuel, $transfertsEffectues, 2);
        $stockActuel = bcsub($stockActuel, $sorties, 2);

        return max(0, (float) $stockActuel);
    }

    /**
     * Récupère la situation des stocks avec filtres
     */
    public function getStockSituation(array $filters, int $page = 1, int $limit = 20): array
    {
        $stocks = $this->consumableRepository->calculateAllStocks($filters);
        $totalServices = $this->consumableRepository->countServicesWithStock($filters);

        // Regrouper par service
        $services = [];
        $totalQuantityInitial = '0';
        $totalQuantityCurrent = '0';
        $totalConsumables = 0;

        foreach ($stocks as $stock) {
            $serviceId = (int) $stock['serviceId'];
            $serviceNom = $stock['serviceNom'];
            $consumableId = (int) $stock['consumableId'];
            $stockInitial = (float) $stock['stockInitial'];
            $totalTransfertsRecus = (float) $stock['totalTransfertsRecus'];
            $totalTransfertsEffectues = (float) $stock['totalTransfertsEffectues'];
            $stockActuel = (float) $stock['stockActuel'];

            // Initialiser le service si nécessaire
            if (!isset($services[$serviceId])) {
                $services[$serviceId] = [
                    'service' => [
                        'id' => $serviceId,
                        'nom' => $serviceNom,
                    ],
                    'totalConsumables' => 0,
                    'totalQuantityInitial' => '0',
                    'totalQuantityReceived' => '0',
                    'totalQuantityTransferred' => '0',
                    'totalQuantityCurrent' => '0',
                    'consumables' => [],
                ];
            }

            // Ajouter le consommable au service
            $services[$serviceId]['consumables'][] = [
                'consumableId' => $consumableId,
                'nom' => $stock['designation'],
                // 'categorie' => $stock['categorieId'] ? [
                //     'id' => (int) $stock['categorieId'],
                //     'nom' => $stock['categorieNom'],
                // ] : null,
                'quantityInitial' => (string) $stockInitial,
                'quantityReceived' => (string) $totalTransfertsRecus,
                'quantityTransferred' => (string) $totalTransfertsEffectues,
                'stockActuel' => (string) $stockActuel,
            ];

            // Mettre à jour les totaux du service
            $services[$serviceId]['totalConsumables']++;
            $services[$serviceId]['totalQuantityInitial'] = bcadd($services[$serviceId]['totalQuantityInitial'], $stockInitial, 2);
            $services[$serviceId]['totalQuantityReceived'] = bcadd($services[$serviceId]['totalQuantityReceived'], $totalTransfertsRecus, 2);
            $services[$serviceId]['totalQuantityTransferred'] = bcadd($services[$serviceId]['totalQuantityTransferred'], $totalTransfertsEffectues, 2);
            $services[$serviceId]['totalQuantityCurrent'] = bcadd($services[$serviceId]['totalQuantityCurrent'], $stockActuel, 2);

            // Mettre à jour les totaux globaux
            $totalQuantityInitial = bcadd($totalQuantityInitial, $stockInitial, 2);
            $totalQuantityCurrent = bcadd($totalQuantityCurrent, $stockActuel, 2);
            $totalConsumables++;
        }

        // Convertir en tableau indexé
        $servicesArray = array_values($services);

        // Pagination sur les services
        $offset = ($page - 1) * $limit;
        $paginatedServices = array_slice($servicesArray, $offset, $limit);

        $summary = [
            'totalConsumables' => $totalConsumables,
            'totalQuantityInitial' => $totalQuantityInitial,
            'totalQuantityCurrent' => $totalQuantityCurrent,
        ];

        return [
            'summary' => $summary,
            'services' => $paginatedServices,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalServices,
                'pages' => (int) ceil($totalServices / $limit),
            ],
        ];
    }

    /**
     * Vérifie si un transfert est possible (stock suffisant)
     */
    public function canTransfer(int $consumableId, int $serviceSourceId, string $quantite): array
    {
        $stockActuel = $this->getStock($consumableId, $serviceSourceId);
        $quantiteDemandee = (float) $quantite;

        if ($stockActuel < $quantiteDemandee) {
            return [
                'canTransfer' => false,
                'stockDisponible' => $stockActuel,
                'quantiteDemandee' => $quantiteDemandee,
                'message' => sprintf('Stock insuffisant. Stock disponible : %s, Quantité demandée : %s', $stockActuel, $quantiteDemandee),
            ];
        }

        return [
            'canTransfer' => true,
            'stockDisponible' => $stockActuel,
            'quantiteDemandee' => $quantiteDemandee,
            'nouveauStock' => $stockActuel - $quantiteDemandee,
        ];
    }
}
