<?php

namespace App\Service\Core;

use App\Entity\Core\User;
use App\Exception\AccessDeniedException;
use App\Exception\ChangePasswordException;
use App\Repository\Core\PermissionRepository;
use App\Repository\Core\RolePermissionRepository;
use App\Repository\Core\RoleRepository;

/**
 * Service AccessCheckerService
 *
 * Responsable de vérifier si un utilisateur a les droits nécessaires pour accéder 
 * à une page ou une ressource spécifique en fonction de ses rôles et permissions.
 */
class AccessCheckerService
{
    public function __construct(
        private PermissionRepository $permissionRepository,
        private RolePermissionRepository $rolePermissionRepository,
        private RoleRepository $roleRepository
    ) {
    }

    /**
     * Vérifie si un utilisateur a les droits nécessaires pour accéder à une page ou ressource.
     *
     * @param User   $user        L'utilisateur dont l'accès est à vérifier.
     * @param bool   $isGranted   Indique si un accès explicite est déjà accordé.
     * @param string $accessPage  Le nom de la page ou de la ressource cible.
     *
     * @throws AccessDeniedException Si l'accès est refusé.
     */
    public function checker(User $user, bool $isGranted, string $accessPage): void
    {
        // Vérifie les prérequis d'accès pour l'utilisateur
        if (!$user || !$user->isVerified() || $user->isDelete()) {
            throw new AccessDeniedException("Utilisateur non autorisé ou désactivé.");
        }

        // Si l’utilisateur n’a pas déjà une autorisation explicite, on vérifie ses permissions
        if (!$isGranted && !$this->hasAccess($user, $accessPage)) {
            throw new AccessDeniedException("Accès refusé à la ressource : $accessPage");
        }

        // Oblige le changement de mot de passe au premier login
        if ($user->isFirstLogin()) {
            throw new ChangePasswordException("Veuillez changer votre mot de passe avant de continuer.");
        }
    }

    /**
     * Vérifie si l'utilisateur a le droit d'accéder à une ressource.
     */
    private function hasAccess(User $user, string $accessPage): bool
    {
        $permission = $this->permissionRepository->findOneBy(['nom' => $accessPage]);

        if (!$permission) {
            return false;
        }

        foreach ($user->getUserRoles() as $role) {
            if ($this->rolePermissionRepository->findOneBy([
                'idRole' => $role,
                'idPermission' => $permission,
            ])) {
                return true;
            }
        }

        return false;
    }
}
