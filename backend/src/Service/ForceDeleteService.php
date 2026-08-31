<?php

namespace App\Service;

use App\Exception\ResourceInUseException;
use Doctrine\DBAL\Exception\ConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Suppression définitive générique ($em->remove() + flush()), commune à tous les
 * contrôleurs supportant ?force=true. Traduit toute violation de contrainte FK
 * en ResourceInUseException plutôt que de laisser fuiter une exception DBAL.
 */
final class ForceDeleteService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function delete(object $entity): void
    {
        try {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        } catch (ConstraintViolationException $e) {
            throw new ResourceInUseException(
                'Impossible de supprimer définitivement cette ressource car elle est liée à d\'autres données.',
                0,
                $e
            );
        }
    }
}
