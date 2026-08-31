<?php

namespace App\Serializer;

use App\Entity\User;
use App\Entity\Service;
use App\Entity\Role;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;

/**
 * Normalizer spécialisé pour le profil utilisateur.
 * Retourne une représentation plate et maîtrisée de l'utilisateur
 * sans références circulaires.
 */
final class UserProfileNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface
{
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

        $normalized = [
            'id' => $object->getId(),
            'firstName' => $object->getFirstName(),
            'lastName' => $object->getLastName(),
            'email' => $object->getEmail(),
            'is_active' => $object->isActive(),
            'langue' => $object->getLangue(),
            'twoFactorEnabled' => $object->isTwoFactorEnabled(),
            'createdAt' => $object->getCreatedAt() ? $object->getCreatedAt()->format('d-m-Y H:i:s') : null,
        ];

        // Sérialiser le service si présent
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

        // Sérialiser les rôles assignés
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

        return $normalized;
    }

    /**
     * Vérifie si le normalizer supporte la normalisation de l'objet
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof User && ($context['_user_profile'] ?? false);
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
