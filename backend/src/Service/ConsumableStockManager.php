<?php

namespace App\Service;

use App\Entity\Consumable;
use App\Repository\ConsumableRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Point central de recalcul du stock : jusqu'ici ConsumableRepository::getStockActuel()
 * calculait toujours à la volée, sans jamais persister de valeur (Consumable::$stockActuel
 * n'existait pas). Ce service recalcule et persiste ce cache dès qu'un mouvement fait varier
 * le stock (entrée, transfert, retour BSP, consommation) — à appeler après toute mutation de
 * ce type, dans une transaction couvrant à la fois la mutation et ce recalcul.
 */
final class ConsumableStockManager
{
    public function __construct(
        private readonly ConsumableRepository $consumableRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function recalculateAndPersist(?Consumable $consumable): void
    {
        if (null === $consumable || null === $consumable->getId()) {
            return;
        }

        $alreadyInTransaction = $this->entityManager->getConnection()->isTransactionActive();
        if (!$alreadyInTransaction) {
            $this->entityManager->beginTransaction();
        }

        try {
            $stockActuel = $this->consumableRepository->getStockActuel($consumable->getId());
            $consumable->setStockActuel((string) $stockActuel);
            $this->entityManager->flush();

            if (!$alreadyInTransaction) {
                $this->entityManager->commit();
            }
        } catch (\Throwable $e) {
            if (!$alreadyInTransaction) {
                $this->entityManager->rollback();
            }
            throw $e;
        }
    }
}
