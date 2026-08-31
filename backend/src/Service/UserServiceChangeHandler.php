<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\AssetAssignment;
use App\Repository\AssetAssignmentRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service pour gérer les changements de service utilisateur et mettre à jour les affectations de biens.
 */
final class UserServiceChangeHandler
{
    public function __construct(
        private readonly AssetAssignmentRepository $assignmentRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Gère le changement de service d'un utilisateur.
     * Met à jour toutes les affectations actives de l'utilisateur pour refléter le nouveau service.
     *
     * @param User $user
     * @param int|null $oldServiceId
     * @param int|null $newServiceId
     * @return void
     */
    public function handleServiceChange(User $user, ?int $oldServiceId, ?int $newServiceId): void
    {
        if ($oldServiceId === $newServiceId) {
            return;
        }

        // Récupérer toutes les affectations actives de cet utilisateur (dateFin null)
        $activeAssignments = $this->assignmentRepository->findActiveByUser($user->getId());

        foreach ($activeAssignments as $assignment) {
            // Mettre à jour l'affectation pour utiliser le nouveau service
            // On garde l'utilisateur comme détenteur principal, mais on met à jour le service
            if ($newServiceId) {
                $assignment->setCommentaire(sprintf(
                    'Mise à jour automatique suite au changement de service utilisateur (de %d à %d).',
                    $oldServiceId,
                    $newServiceId
                ));
                // Note: Le service dans l'affectation reste inchangé pour garder l'historique
                // L'affectation reste liée à l'utilisateur, qui a maintenant un nouveau service
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Crée une nouvelle affectation lors du changement de service utilisateur.
     * Optionnel: ferme l'ancienne affectation et en crée une nouvelle avec le nouveau service.
     *
     * @param User $user
     * @param int|null $newServiceId
     * @return void
     */
    public function createNewAssignmentsOnServiceChange(User $user, ?int $newServiceId): void
    {
        if (!$newServiceId) {
            return;
        }

        // Récupérer toutes les affectations actives de cet utilisateur
        $activeAssignments = $this->assignmentRepository->findActiveByUser($user->getId());

        foreach ($activeAssignments as $assignment) {
            // Fermer l'ancienne affectation
            $assignment->setDateFin(new \DateTime());
            $assignment->setCommentaire('Affectation fermée suite au changement de service utilisateur.');

            // Créer une nouvelle affectation avec le nouveau service
            $newAssignment = new AssetAssignment();
            $newAssignment->setAsset($assignment->getAsset());
            $newAssignment->setUser($user);
            $newAssignment->setService($assignment->getService()); // Garder le même service si défini
            $newAssignment->setTypeAffectation($assignment->getTypeAffectation());
            $newAssignment->setDateDebut(new \DateTime());
            $newAssignment->setCommentaire(sprintf(
                'Nouvelle affectation suite au changement de service utilisateur (nouveau service: %d).',
                $newServiceId
            ));

            $this->entityManager->persist($newAssignment);
        }

        $this->entityManager->flush();
    }
}
