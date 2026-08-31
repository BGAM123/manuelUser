<?php

namespace App\EventListener;

use App\Entity\BlameableInterface;
use App\Entity\User;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Peuple automatiquement createdBy/updatedBy sur toute entité BlameableInterface. Aucune
 * infrastructure Blameable/Timestampable (Gedmo ou autre) n'existe dans ce projet ; tout
 * createdBy existant ailleurs (Bsp, ConsumableTransfer, AssetReformRequest) est posé
 * manuellement service par service — ce listener ne remplace pas ces appels explicites,
 * il ne fait que poser createdBy si rien ne l'a déjà fait (voir prePersist ci-dessous).
 */
final class BlameableListener
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof BlameableInterface) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        // Ne jamais écraser une valeur déjà posée explicitement par un service
        // (ex: ConsumableTransferService::createBspTransfer() pose createdBy lui-même).
        if (null === $entity->getCreatedBy()) {
            $entity->setCreatedBy($user);
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof BlameableInterface) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        // preUpdate se déclenche après le calcul du changeset. setNewValue() ne peut
        // modifier qu'un champ déjà présent dans ce changeset : sur une mise à jour
        // classique, updatedBy n'y figure pas encore. On recalcule donc le changeset
        // après avoir posé l'utilisateur pour que la colonne soit incluse dans l'UPDATE.
        $entity->setUpdatedBy($user);
        $entityManager = $args->getObjectManager();
        $entityManager->getUnitOfWork()->recomputeSingleEntityChangeSet(
            $entityManager->getClassMetadata($entity::class),
            $entity,
        );
    }
}
