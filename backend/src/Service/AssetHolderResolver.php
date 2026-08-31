<?php

namespace App\Service;

use App\Entity\Asset;
use App\Entity\AssetAssignment;
use App\Entity\User;
use App\Repository\AssetAssignmentRepository;
use App\Repository\UserRepository;

/**
 * Service pour résoudre le détenteur actuel d'un bien.
 * Priorité: user_id > service_id > utilisateur du service
 */
final class AssetHolderResolver
{
    public function __construct(
        private readonly AssetAssignmentRepository $assignmentRepository,
        private readonly UserRepository $userRepository
    ) {
    }

    /**
     * Retourne le détenteur actuel d'un bien.
     * Priorité: 1) affectation avec user_id, 2) affectation avec service_id, 3) utilisateur du service
     *
     * @param Asset $asset
     * @return array{type: string, id: int, nom: string, prenom?: string, matricule?: string, email?: string, sigle?: string}|null
     */
    public function resolveCurrentHolder(Asset $asset): ?array
    {
        // Récupérer l'affectation active (dateFin null)
        $activeAssignment = $this->assignmentRepository->findActiveByAsset($asset->getId());

        if (!$activeAssignment) {
            // Pas d'affectation, essayer de récupérer l'utilisateur du service
            $service = $asset->getServices()->first();
            if ($service && !$service->isDelete()) {
                return $this->resolveServiceUser($service);
            }
            return null;
        }

        // Priorité 1: user_id dans l'affectation
        $user = $activeAssignment->getUser();
        if ($user) {
            return [
                'type' => 'user',
                'id' => $user->getId(),
                'nom' => $user->getLastName(),
                'prenom' => $user->getFirstName(),
                'matricule' => $user->getMatricule(),
                'email' => $user->getEmail()
            ];
        }

        // Priorité 2: service_id dans l'affectation
        $service = $activeAssignment->getService();
        if ($service) {
            return $this->resolveServiceUser($service);
        }

        return null;
    }

    /**
     * Résout l'utilisateur responsable d'un service.
     *
     * @param \App\Entity\Service $service
     * @return array{type: string, id: int, nom: string, prenom?: string, matricule?: string, email?: string, sigle?: string}|null
     */
    private function resolveServiceUser(\App\Entity\Service $service): ?array
    {
        // Pour l'instant, on retourne le service lui-même
        // Dans une implémentation future, on pourrait chercher l'utilisateur actif du service
        return [
            'type' => 'service',
            'id' => $service->getId(),
            'nom' => $service->getNom(),
            'sigle' => $service->getSigle()
        ];
    }

    /**
     * Retourne tous les détenteurs potentiels d'un bien avec leur priorité.
     *
     * @param Asset $asset
     * @return array<int, array{type: string, id: int, priority: int, nom: string, prenom?: string, matricule?: string, email?: string, sigle?: string}>
     */
    public function resolveAllHolders(Asset $asset): array
    {
        $holders = [];

        // Récupérer toutes les affectations non supprimées
        $assignments = $this->assignmentRepository->findByAsset($asset->getId());

        foreach ($assignments as $assignment) {
            $priority = 1; // Priorité par défaut

            $user = $assignment->getUser();
            if ($user) {
                $holders[] = [
                    'type' => 'user',
                    'id' => $user->getId(),
                    'priority' => $priority,
                    'nom' => $user->getLastName(),
                    'prenom' => $user->getFirstName(),
                    'matricule' => $user->getMatricule(),
                    'email' => $user->getEmail()
                ];
                continue;
            }

            $service = $assignment->getService();
            if ($service) {
                $holders[] = [
                    'type' => 'service',
                    'id' => $service->getId(),
                    'priority' => $priority,
                    'nom' => $service->getNom(),
                    'sigle' => $service->getSigle()
                ];
            }
        }

        // Trier par priorité (1 = le plus prioritaire)
        usort($holders, fn($a, $b) => $a['priority'] <=> $b['priority']);

        return $holders;
    }

    /**
     * Recherche l'utilisateur associé à un service
     *
     * @param \App\Entity\Service $service
     * @return array{id: int, firstName: string, lastName: string}|null
     */
    public function findUserByService(\App\Entity\Service $service): ?array
    {
        if (!$service) {
            return null;
        }
        
        $user = $this->userRepository->findOneBy(['service' => $service, 'isDelete' => false, 'isActive' => true]);
        if (!$user) {
            return null;
        }

        return [
            'id' => $user->getId(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
        ];
    }
}
