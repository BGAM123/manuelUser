<?php

/**
 * ProfileNormalizer - Normalise le profil utilisateur pour les réponses API.
 *
 * Enrichit le profil utilisateur avec :
 * - Service associé
 * - Rôles métier (depuis la relation user_role)
 * - Permissions associées aux rôles (depuis role_permission)
 */

namespace App\Serializer;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ProfileNormalizer implements NormalizerInterface
{
    /**
     * @param NormalizerInterface $normalizer Injected native ObjectNormalizer to read entity primitive properties.
     */
    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private readonly NormalizerInterface $normalizer,
        private readonly UrlGeneratorInterface $router,
        private readonly RequestStack $requestStack
    ) {
    }

    /**
     * @param mixed $object
     * @param string|null $format
     * @param array<string, mixed> $context
     * @return array<string, mixed>|string|int|float|bool|\ArrayObject<string, mixed>|null
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        // Force type safety and prevent normalization of unsupported objects
        if (!$object instanceof User) {
            throw new \InvalidArgumentException('The object must be an instance of User.');
        }

        $request = $this->requestStack->getCurrentRequest();
        // Appliquer ce normalizer uniquement pour l'endpoint /profile
        if (!$request || strpos($request->getPathInfo(), '/profile') === false || isset($context[self::class . '_ALREADY_CALLED'])) {
            return null; // Retourner null pour laisser le ObjectNormalizer par défaut gérer
        }

        $context[self::class . '_ALREADY_CALLED'] = true;

        // 1. Delegate core properties normalization to the native platform ObjectNormalizer
        $normalizedData = $this->normalizer->normalize($object, $format, $context);

        if (!is_array($normalizedData)) {
            /** @var array<string, mixed>|string|int|float|bool|\ArrayObject<string, mixed>|null $normalizedData */
            return $normalizedData;
        }

        // 2. Enrichir avec le service
        if ($object->getService()) {
            $normalizedData['service'] = [
                'id' => $object->getService()->getId(),
                'nom' => $object->getService()->getNom(),
                'sigle' => $object->getService()->getSigle(),
                'type_service' => $object->getService()->getTypeService(),
            ];
        }

        // 3. Enrichir avec les rôles métier (depuis la relation user_role)
        $roles = [];
        foreach ($object->getAssignedRoles() as $role) {
            if (!$role->isDelete()) {
                $roles[] = [
                    'id' => $role->getId(),
                    'nom' => $role->getNom(),
                ];
            }
        }
        $normalizedData['roles'] = $roles;

        // 4. Enrichir avec les permissions effectives de l'utilisateur
        $permissions = [];
        foreach ($object->getEffectivePermissions() as $permission) {
            $permissions[] = [
                'id' => $permission->getId(),
                'nom' => $permission->getNom(),
            ];
        }
        $normalizedData['permissions'] = $permissions;

        // 5. Enrichir avec les permissions spécifiques accordées / révoquées
        $grantedPermissions = [];
        foreach ($object->getGrantedPermissions() as $permission) {
            if (!$permission->isDelete() && $permission->isActive()) {
                $grantedPermissions[] = [
                    'id' => $permission->getId(),
                    'nom' => $permission->getNom(),
                ];
            }
        }
        $normalizedData['grantedPermissions'] = $grantedPermissions;

        $revokedPermissions = [];
        foreach ($object->getRevokedPermissions() as $permission) {
            if (!$permission->isDelete()) {
                $revokedPermissions[] = [
                    'id' => $permission->getId(),
                    'nom' => $permission->getNom(),
                ];
            }
        }
        $normalizedData['revokedPermissions'] = $revokedPermissions;

        // Ensure any residual hypermedia links are removed.
        if (array_key_exists('_links', $normalizedData)) {
            unset($normalizedData['_links']);
        }

        /** @var array<string, mixed> $normalizedData */
        return $normalizedData;
    }

    /**
     * Validates whether the incoming payload qualifies for profile normalization.
     * Ne s'applique que si c'est une route /profile.
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if (!$data instanceof User) {
            return false;
        }

        $request = $this->requestStack->getCurrentRequest();
        return $request && strpos($request->getPathInfo(), '/profile') !== false && !isset($context[self::class . '_ALREADY_CALLED']);
    }

    /**
     * Optimizes normalizer caching routines within the Symfony Dependency Injection component.
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            User::class => true,
        ];
    }
}
