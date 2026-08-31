<?php

namespace App\Serializer;

use App\Entity\User;
use App\Entity\Service;
use App\Entity\Role;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * Normalizer spécialisé pour la liste des utilisateurs.
 * Retourne une représentation plate et maîtrisée de l'utilisateur
 * pour les listes sans références circulaires.
 */
final class UserListNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;
    
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
            'is_delete' => $object->isDelete(),
            'matricule' => $object->getMatricule(),
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

        // Sérialiser les groupes assignés
        $assignedGroupes = [];
        foreach ($object->getAssignedGroupes() as $groupe) {
            if (!$groupe->isDelete()) {
                $assignedGroupes[] = [
                    'id' => $groupe->getId(),
                    'nom' => $groupe->getNom(),
                    'description' => $groupe->getDescription(),
                    'is_active' => $groupe->isActive(),
                ];
            }
        }
        $normalized['assignedGroupes'] = $assignedGroupes;

        return $normalized;
    }

    /**
     * Vérifie si le normalizer supporte la normalisation de l'objet
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof User && ($context['_user_list'] ?? false);
    }

    /**
     * Retourne les types supportés avec une priorité élevée
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            User::class => true,
        ];
    }

    /**
     * Définit la priorité du normalizer pour être prioritaire sur le serializer par défaut
     */
    public function getNormalizationCacheKey(object $object, ?string $format = null, array $context = []): ?string
    {
        if ($object instanceof User && ($context['_user_list'] ?? false)) {
            return 'user_list_custom';
        }
        return null;
    }
}