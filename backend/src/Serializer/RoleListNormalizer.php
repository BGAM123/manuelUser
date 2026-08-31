<?php

namespace App\Serializer;

use App\Entity\Role;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizer spécialisé pour la liste des rôles.
 * Retourne chaque rôle avec ses permissions (id et nom).
 */
class RoleListNormalizer implements NormalizerInterface
{
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        if (!$object instanceof Role) {
            throw new \InvalidArgumentException('Expected Role instance');
        }

        $permissions = $object->getPermissions();

        return [
            'id' => $object->getId(),
            'nom' => $object->getNom(),
            'description' => $object->getDescription(),
            'is_active' => $object->isActive(),
            'permissions' => $permissions->map(fn ($permission) => [
                'id' => $permission->getId(),
                'nom' => $permission->getNom(),
            ])->toArray(),
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Role && ($context['_role_list'] ?? false);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Role::class => false,
        ];
    }
}
