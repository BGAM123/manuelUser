<?php

namespace App\Serializer;

use App\Entity\User;
use App\Entity\Service;
use App\Entity\Role;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;

/**
 * Normalizer pour User avec groupes de sérialisation.
 * Maîtrise la sérialisation des relations Service et AssignedRoles
 * pour éviter les références circulaires.
 */
final class UserDetailNormalizer implements NormalizerInterface, NormalizerAwareInterface, CacheableSupportsMethodInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'UserDetailNormalizer_ALREADY_CALLED';

    /**
     * @param User $object
     * @param string|null $format
     * @param array $context
     * @return array
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        if (!$object instanceof User) {
            throw new \InvalidArgumentException('Expected User instance');
        }

        if (isset($context[self::ALREADY_CALLED])) {
            return [];
        }

        $context[self::ALREADY_CALLED] = true;

        // Utiliser le normalizer natif pour les champs primitifs
        $normalized = $this->normalizer->normalize($object, $format, $context);

        if (!is_array($normalized)) {
            return [];
        }

        // Maîtriser la sérialisation du Service
        if ($object->getService() instanceof Service) {
            $service = $object->getService();
            $normalized['service'] = [
                'id' => $service->getId(),
                'nom' => $service->getNom(),
                'sigle' => $service->getSigle(),
                'type_service' => $service->getTypeService(),
                'ordre' => $service->getOrdre(),
                'is_active' => $service->isActive(),
            ];
        } else {
            $normalized['service'] = null;
        }

        // Maîtriser la sérialisation des rôles assignés
        $assignedRoles = [];
        foreach ($object->getAssignedRoles() as $role) {
            if ($role instanceof Role && !$role->isDelete()) {
                $assignedRoles[] = [
                    'id' => $role->getId(),
                    'nom' => $role->getNom(),
                ];
            }
        }
        $normalized['assignedRoles'] = $assignedRoles;

        // Maîtriser la sérialisation des groupes assignés
        $assignedGroupes = [];
        foreach ($object->getAssignedGroupes() as $groupe) {
            if ($groupe->isDelete()) {
                continue;
            }

            $assignedGroupes[] = [
                'id' => $groupe->getId(),
                'nom' => $groupe->getNom(),
                'is_active' => $groupe->isActive(),
            ];
        }
        $normalized['assignedGroupes'] = $assignedGroupes;

        $breakdown = $object->getPermissionsBreakdown();

        $normalized['inheritedFromRoles'] = [];
        foreach ($breakdown['inheritedFromRoles'] as $permission) {
            $normalized['inheritedFromRoles'][] = [
                'id' => $permission->getId(),
                'nom' => $permission->getNom(),
            ];
        }

        $normalized['inheritedFromGroupes'] = [];
        foreach ($breakdown['inheritedFromGroupes'] as $permission) {
            $normalized['inheritedFromGroupes'][] = [
                'id' => $permission->getId(),
                'nom' => $permission->getNom(),
            ];
        }

        $normalized['grantedPermissions'] = [];
        foreach ($breakdown['granted'] as $permission) {
            $normalized['grantedPermissions'][] = [
                'id' => $permission->getId(),
                'nom' => $permission->getNom(),
            ];
        }

        $normalized['revokedPermissions'] = [];
        foreach ($breakdown['revoked'] as $permission) {
            $normalized['revokedPermissions'][] = [
                'id' => $permission->getId(),
                'nom' => $permission->getNom(),
            ];
        }

        $normalized['permissions'] = [];
        foreach ($breakdown['effective'] as $permission) {
            $normalized['permissions'][] = [
                'id' => $permission->getId(),
                'nom' => $permission->getNom(),
            ];
        }

        // Assurer que twoFactorEnabled est présent
        if (!isset($normalized['twoFactorEnabled'])) {
            $normalized['twoFactorEnabled'] = $object->isTwoFactorEnabled();
        }

        // Nettoyer les liens hypermedia
        unset($normalized['_links']);

        return $normalized;
    }

    /**
     * Vérifie si le normalizer supporte la normalisation de l'objet
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        // Activer pour User avec les groupes 'user:detail'
        if (!$data instanceof User) {
            return false;
        }

        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        $groups = $context['groups'] ?? [];
        if (is_string($groups)) {
            $groups = [$groups];
        }

        return in_array('user:detail', (array)$groups, true);
    }

    /**
     * Déclare le support du cache
     */
    public function hasCacheableSupportsMethod(): bool
    {
        return true;
    }

    /**
     * Retourne les types supportés
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            User::class => true,
        ];
    }
}
