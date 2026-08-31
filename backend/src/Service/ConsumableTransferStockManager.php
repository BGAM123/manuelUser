<?php

namespace App\Service;

use App\Entity\Consumable;
use App\Entity\ConsumableTransfer;
use App\Entity\Service;
use App\Entity\PieceJointe;
use App\Repository\ConsumableRepository;
use App\Repository\ConsumableTransferRepository;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\FileUploadService;

class ConsumableTransferStockManager
{
    public function __construct(
        private ConsumableTransferRepository $transferRepository,
        private ConsumableRepository $consumableRepository,
        private ServiceRepository $serviceRepository,
        private EntityManagerInterface $entityManager,
        private ConsumableStockManager $consumableStockManager,
        private FileUploadService $fileUploadService,
    ) {}

    /**
     * Récupère le stock actuel d'un service pour un consommable
     */
    public function getCurrentStock(int $consumableId, int $serviceId): float
    {
        return $this->transferRepository->getCurrentStock($consumableId, $serviceId);
    }

    /**
     * Vérifie si un transfert est possible
     * Utilise le stock_actuel du dernier transfert
     */
    public function canTransfer(int $consumableId, int $serviceSourceId, string $quantite): array
    {
        $stockActuel = $this->getCurrentStock($consumableId, $serviceSourceId);
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

    /**
     * Crée le transfert initial d'un consommable
     * Appelé à la création d'un consommable avec service_id et quantite
     */
    public function createInitialTransfer(
    Consumable $consumable,
    int $serviceId,
    string $quantite
): ConsumableTransfer {
    // 🔥 UTILISER getServiceById() au lieu de getActiveById()
    $service = $this->serviceRepository->getServiceById($serviceId);
    
    if (!$service) {
        throw new \Exception('Service non trouvé avec l\'ID: ' . $serviceId);
    }

    // Vérifier si un transfert initial existe déjà
    $existing = $this->entityManager->createQueryBuilder()
        ->select('ct')
        ->from('App\Entity\ConsumableTransfer', 'ct')
        ->where('ct.consumable = :consumableId')
        ->andWhere('ct.serviceDestination = :serviceId')
        ->andWhere('ct.statut = :statut')
        ->andWhere('ct.isDelete = false')
        ->setParameter('consumableId', $consumable->getId())
        ->setParameter('serviceId', $serviceId)
        ->setParameter('statut', ConsumableTransfer::STATUT_INITIAL)
        ->getQuery()
        ->getOneOrNullResult();

    if ($existing) {
        // Aucun transfert réel n'a lieu ici : on enregistre seulement le stock d'ouverture
        // du service destinataire. "quantite" (= quantité transférée) doit rester à 0 ;
        // seul "stockActuel" porte la quantité initiale disponible.
        $existing->setQuantite('0');
        $existing->setStockActuel($quantite);
        $this->entityManager->flush();
        return $existing;
    }

    $transfer = new ConsumableTransfer();
    $transfer->setConsumable($consumable);
    $transfer->setServiceDestination($service);
    $transfer->setServiceSource(null);
    $transfer->setType(ConsumableTransfer::TYPE_INITIAL);
    $transfer->setStatut(ConsumableTransfer::STATUT_INITIAL);
    // Le service destinataire n'a reçu aucun transfert à la création : quantité transférée = 0.
    $transfer->setQuantite('0');
    $transfer->setStockActuel($quantite);
    $transfer->setDateTransfert(new \DateTime());

    $this->entityManager->persist($transfer);
    $this->entityManager->flush();

    // Mettre à jour le stock global du consommable
    $this->consumableStockManager->recalculateAndPersist($consumable);

    return $transfer;
}

    /**
     * Crée un transfert entre deux services
     * Crée UN SEUL enregistrement
     */
    public function createTransfer(
        int $consumableId,
        int $serviceSourceId,
        int $serviceDestinationId,
        string $quantite,
        ?string $observations = null,
        array $documents = [],
        array $documentLabels = [],
        string $type = ConsumableTransfer::TYPE_TRANSFERT_DIRECT,
        ?string $statut = null
    ): ConsumableTransfer {
        // 1. Vérifier le stock source (transferts directs et sorties BSP : les deux retirent
        // du stock du service source, donc les deux doivent être bloqués si le stock est
        // insuffisant, sous peine de créer une sortie sur un stock négatif/inexistant)
        $statutSortie = $statut ?? ($type === ConsumableTransfer::TYPE_BSP ? ConsumableTransfer::STATUT_SORTI : ConsumableTransfer::STATUT_TRANSFERE);
        if ($type === ConsumableTransfer::TYPE_TRANSFERT_DIRECT || ($type === ConsumableTransfer::TYPE_BSP && $statutSortie === ConsumableTransfer::STATUT_SORTI)) {
            $check = $this->canTransfer($consumableId, $serviceSourceId, $quantite);
            if (!$check['canTransfer']) {
                throw new \Exception($check['message']);
            }
        }

        $consumable = $this->consumableRepository->getActiveById($consumableId);
        $serviceSource = $this->serviceRepository->getServiceById($serviceSourceId);
        $serviceDestination = $this->serviceRepository->getServiceById($serviceDestinationId);

        if (!$consumable || !$serviceSource || !$serviceDestination) {
            throw new \Exception('Entité non trouvée');
        }

        // 2. Créer UN SEUL transfert
        $transfer = new ConsumableTransfer();
        $transfer->setConsumable($consumable);
        $transfer->setServiceSource($serviceSource);
        $transfer->setServiceDestination($serviceDestination);
        $transfer->setType($type);
        
        // Définir le statut
        if ($statut === null) {
            $statut = $type === ConsumableTransfer::TYPE_BSP 
                ? ConsumableTransfer::STATUT_SORTI 
                : ConsumableTransfer::STATUT_TRANSFERE;
        }
        $transfer->setStatut($statut);
        
        $transfer->setQuantite($quantite);
        
        // Calculer stockActuel selon le type et le statut
        if ($type === ConsumableTransfer::TYPE_TRANSFERT_DIRECT) {
            // Pour TRANSFERT_DIRECT : stockActuel = stock du service destination après réception
            $stockDestination = $this->getCurrentStock($consumableId, $serviceDestinationId);
            $nouveauStockDest = $stockDestination + (float) $quantite;
            $transfer->setStockActuel((string) $nouveauStockDest);
        } elseif ($type === ConsumableTransfer::TYPE_BSP && $statut === ConsumableTransfer::STATUT_SORTI) {
            // Pour BSP SORTI : stockActuel = stock du service source après sortie.
            // Le stock suffisant a déjà été vérifié plus haut (sinon une \Exception a été levée).
            $stockSource = $this->getCurrentStock($consumableId, $serviceSourceId);
            $transfer->setStockActuel((string) ($stockSource - (float) $quantite));
        } else {
            // Pour INITIAL ou autres : stockActuel = quantité
            $transfer->setStockActuel($quantite);
        }
        
        $transfer->setObservations($observations);
        $transfer->setDateTransfert(new \DateTime());

        // 3. Attacher les pièces jointes AVANT le flush pour éviter les doublons
        foreach ($documents as $index => $file) {
            $nom = $documentLabels[$index] ?? $file->getClientOriginalName();
            $pieceJointe = $this->fileUploadService->upload($file, FileUploadService::KIND_DOCUMENT, $nom);
            $transfer->addPieceJointe($pieceJointe);
        }

        $this->entityManager->persist($transfer);
        
        // 4. Flush UNE SEULE FOIS pour éviter les doublons
        $this->entityManager->flush();

        // 5. Mettre à jour le stock global du consommable
        $this->consumableStockManager->recalculateAndPersist($consumable);

        return $transfer;
    }

/**
 * Recalcule tous les stocks d'un service
 * Formule : stock_actuel = (stock_initial + total_entrees) - total_sorties
 * Met à jour le stockActuel uniquement pour les transferts où le service est le DESTINATION
 * Convention : stockActuel d'un transfert représente le stock du service destination
 */
public function recalculateAllStocks(int $consumableId, int $serviceId): void
{
    $qb = $this->entityManager->createQueryBuilder()
        ->select('ct')
        ->from('App\Entity\ConsumableTransfer', 'ct')
        ->where('ct.consumable = :consumableId')
        ->andWhere('(ct.serviceDestination = :serviceId OR ct.serviceSource = :serviceId)')
        ->andWhere('ct.isDelete = false')
        ->orderBy('ct.id', 'ASC')
        ->setParameter('consumableId', $consumableId)
        ->setParameter('serviceId', $serviceId);
    
    $transfers = $qb->getQuery()->getResult();
    
    // 🔥 Initialiser le stock à 0
    $stock = 0;
    
    foreach ($transfers as $transfer) {
        // 1. Stock initial (INITIAL)
        if ($transfer->getStatut() === ConsumableTransfer::STATUT_INITIAL) {
            // Le service destination du transfert INITIAL est le propriétaire du stock.
            // Ce n'est pas un transfert réel (quantite = 0) : le stock d'ouverture est
            // porté par stockActuel, déjà positionné à la quantité initiale du consomptible.
            if ($transfer->getServiceDestination()->getId() === $serviceId) {
                $stock = (float) $transfer->getStockActuel();
                $transfer->setStockActuel((string) $stock);
                $this->entityManager->persist($transfer);
            }
        }
        // 2. Transfert reçu (TRANSFERE en destination)
        elseif ($transfer->getStatut() === ConsumableTransfer::STATUT_TRANSFERE) {
            // Si le service est le DESTINATION du transfert → ENTRÉE
            if ($transfer->getServiceDestination()->getId() === $serviceId) {
                $stock += (float) $transfer->getQuantite();
                // Mettre à jour stockActuel uniquement pour le service destination
                $transfer->setStockActuel((string) $stock);
                $this->entityManager->persist($transfer);
            }
            // Si le service est la SOURCE du transfert → SORTIE
            elseif ($transfer->getServiceSource() && $transfer->getServiceSource()->getId() === $serviceId) {
                $stock -= (float) $transfer->getQuantite();
                // NE PAS mettre à jour stockActuel (ce n'est pas le service destination)
            }
        }
        // 3. Sortie BSP (SORTI en source)
        elseif ($transfer->getStatut() === ConsumableTransfer::STATUT_SORTI) {
            // Si le service est la SOURCE du transfert → SORTIE
            if ($transfer->getServiceSource() && $transfer->getServiceSource()->getId() === $serviceId) {
                $stock -= (float) $transfer->getQuantite();
                // NE PAS mettre à jour stockActuel (ce n'est pas le service destination)
            }
        }
    }
    
    $this->entityManager->flush();
}

    /**
     * Récupère le stock d'un service à une date donnée
     * Utile pour les rapports
     */
    public function getStockAtDate(
        int $consumableId,
        int $serviceId,
        \DateTimeInterface $date
    ): float {
        $transfer = $this->transferRepository->createQueryBuilder('ct')
            ->where('ct.consumable = :consumableId')
            ->andWhere('ct.serviceDestination = :serviceId')
            ->andWhere('ct.dateTransfert <= :date')
            ->andWhere('ct.isDelete = false')
            ->orderBy('ct.id', 'DESC')
            ->setMaxResults(1)
            ->setParameter('consumableId', $consumableId)
            ->setParameter('serviceId', $serviceId)
            ->setParameter('date', $date)
            ->getQuery()
            ->getOneOrNullResult();
        
        return $transfer ? (float) $transfer->getStockActuel() : 0;
    }
}