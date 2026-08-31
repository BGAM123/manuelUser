<?php

namespace App\Controller\Users;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\RoleRepository;
use App\Repository\PermissionRepository;
use App\Repository\GroupeRepository;
use App\Repository\ServiceRepository;
use App\Service\ApiResponseFactory;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/users', name: 'app_user_create', methods: ['POST'])]
#[OA\Tag(name: 'Users')]
final class CreateUserController extends AbstractController
{
    #[OA\Post(
        path: '/users',
        summary: 'Créer un nouvel utilisateur',
        description: 'Crée un nouvel utilisateur et stocke son mot de passe de manière sécurisée.'
    )]
    #[OA\RequestBody(
        description: 'Charge utile JSON pour créer un utilisateur',
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            required: ['firstName', 'lastName', 'email', 'password'],
            properties: [
                new OA\Property(property: 'firstName', type: 'string', example: 'John'),
                new OA\Property(property: 'lastName', type: 'string', example: 'Doe'),
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john.doe@example.com'),
                new OA\Property(property: 'password', type: 'string', example: 'secretPassword123'),
                new OA\Property(property: 'matricule', type: 'string', example: 'MAT-001', description: 'Matricule unique de l utilisateur (optionnel, max 50 caractères)'),
                new OA\Property(property: 'cni', type: 'string', example: '123456789', description: 'Numéro CNI de l utilisateur (optionnel, max 255 caractères)'),
                new OA\Property(property: 'is_active', type: 'boolean', example: true),
                new OA\Property(property: 'twoFactorEnabled', type: 'boolean', example: false, description: 'Activer la double authentification (optionnel, défaut: false)'),
                new OA\Property(property: 'service_id', type: 'integer', example: 2, description: 'Identifiant du service assigné à l utilisateur'),
                new OA\Property(property: 'role_ids', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2], description: 'IDs des rôles métier assignés à l utilisateur (max 2)'),
                new OA\Property(property: 'group_ids', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 3], description: 'IDs des groupes auxquels l utilisateur appartient (illimité)'),
                new OA\Property(property: 'granted_permission_ids', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2], description: 'IDs de permissions accordées explicitement à l utilisateur'),
                new OA\Property(property: 'revoked_permission_ids', type: 'array', items: new OA\Items(type: 'integer'), example: [3], description: 'IDs de permissions révoquées explicitement pour l utilisateur')
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Created - Utilisateur créé avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'status', type: 'integer', example: 201),
                new OA\Property(property: 'message', type: 'string', example: 'Utilisateur créé avec succès.'),
                new OA\Property(property: 'data', type: 'object')
            ],
            example: [
                'success' => true,
                'status' => 201,
                'message' => 'Utilisateur créé avec succès.',
                'data' => [
                    'id' => 3,
                    'firstName' => 'Jane',
                    'lastName' => 'Smith',
                    'email' => 'jane.smith@example.com',
                    'matricule' => 'MAT-001',
                    'cni' => '123456789',
                    'is_active' => true,
                    'twoFactorEnabled' => false,
                    'createdAt' => '05-01-2024 14:22:10',
                    'service' => [
                        'id' => 2,
                        'nom' => 'Informatique',
                        'sigle' => 'IT',
                        'type_service' => 'poste',
                        'ordre' => 1,
                        'is_active' => true
                    ]
                ]
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => null])
    )]
    public function __invoke(
        Request $request,
        SerializerInterface $serializer,
        UserRepository $userRepository,
        RoleRepository $roleRepository,
        GroupeRepository $groupeRepository,
        PermissionRepository $permissionRepository,
        ServiceRepository $serviceRepository,
        ValidatorInterface $validator,
        UserPasswordHasherInterface $passwordHasher,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $apiResponse->error('Invalid JSON payload.', Response::HTTP_BAD_REQUEST);
        }

        if (!empty($payload['service_id'])) {
            $validation = $userRepository->validateServiceAssignment((int) $payload['service_id'], $serviceRepository);
            if (!$validation['valid']) {
                return $apiResponse->error($validation['error'], Response::HTTP_BAD_REQUEST);
            }
        }

        $user = $userRepository->buildUserFromPayload($payload, $passwordHasher);

        // Gérer twoFactorEnabled (optionnel, défaut: false)
        if (isset($payload['twoFactorEnabled'])) {
            if (!is_bool($payload['twoFactorEnabled'])) {
                return $apiResponse->error('twoFactorEnabled must be a boolean.', Response::HTTP_BAD_REQUEST);
            }
            $user->setTwoFactorEnabled($payload['twoFactorEnabled']);
        }
        // Si matricule vide ou null, on le retire du payload pour que la génération automatique fonctionne
        if (isset($payload['matricule']) && ($payload['matricule'] === '' || $payload['matricule'] === null)) {
            unset($payload['matricule']);
        }

        // Validation du matricule si fourni manuellement (et non vide)
        if (!empty($payload['matricule'])) {
            if ($userRepository->isMatriculeExists($payload['matricule'])) {
                return $apiResponse->error('Ce matricule est déjà utilisé.', Response::HTTP_BAD_REQUEST);
            }
        }

        // Le repository va générer automatiquement le matricule si non fourni
        $user = $userRepository->buildUserFromPayload($payload, $passwordHasher);

        if (!empty($payload['service_id'])) {
            $service = $serviceRepository->getServiceById((int) $payload['service_id']);
            if ($service) {
                $user->setService($service);
            }
        }

        // Gère les rôles métier via role_ids
        if (!empty($payload['role_ids']) && is_array($payload['role_ids'])) {
            foreach ($payload['role_ids'] as $roleId) {
                $role = $roleRepository->getRoleById((int) $roleId);
                if (!$role || $role->isDelete()) {
                    return $apiResponse->error(sprintf('Rôle %d introuvable.', (int) $roleId), Response::HTTP_BAD_REQUEST);
                }
                try {
                    $user->addAssignedRole($role);
                } catch (\DomainException $e) {
                    return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
                }
            }
        } else {
            // Si aucun rôle n'est fourni, assigner le rôle par défaut (nom 'Utilisateurs')
            $defaultRole = $roleRepository->getRoleByNom('Utilisateurs');
            if (!$defaultRole || $defaultRole->isDelete()) {
                // Créer le rôle par défaut s'il n'existe pas
                $defaultRole = new \App\Entity\Role();
                $defaultRole->setNom('Utilisateurs');
                $defaultRole->setDescription('Rôle par défaut pour les utilisateurs');
                $defaultRole->setIsActive(true);
                $defaultRole->setCreatedAt(new \DateTimeImmutable());
                $defaultRole->setUpdatedAt(new \DateTimeImmutable());
                $roleRepository->save($defaultRole);
            }
            try {
                $user->addAssignedRole($defaultRole);
            } catch (\DomainException $e) {
                return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
            }
        }

        // Gère les groupes via group_ids (illimité, contrairement aux rôles)
        if (!empty($payload['group_ids']) && is_array($payload['group_ids'])) {
            foreach ($payload['group_ids'] as $groupeId) {
                $groupe = $groupeRepository->getGroupeById((int) $groupeId);
                if (!$groupe || $groupe->isDelete()) {
                    return $apiResponse->error(sprintf('Groupe %d introuvable.', (int) $groupeId), Response::HTTP_BAD_REQUEST);
                }
                $user->addAssignedGroupe($groupe);
            }
        }

        // Gère les permissions spécifiques accordées / révoquées
        $grantedIds = $payload['granted_permission_ids'] ?? [];
        $revokedIds = $payload['revoked_permission_ids'] ?? [];

        if (!empty($grantedIds) && !is_array($grantedIds)) {
            return $apiResponse->error('granted_permission_ids must be an array of integers.', Response::HTTP_BAD_REQUEST);
        }
        if (!empty($revokedIds) && !is_array($revokedIds)) {
            return $apiResponse->error('revoked_permission_ids must be an array of integers.', Response::HTTP_BAD_REQUEST);
        }

        $duplicateIds = array_intersect($grantedIds ?? [], $revokedIds ?? []);
        if (!empty($duplicateIds)) {
            return $apiResponse->error('A permission cannot be both granted and revoked for the same user.', Response::HTTP_BAD_REQUEST);
        }

        foreach ($grantedIds as $permissionId) {
            $permission = $permissionRepository->getPermissionById((int) $permissionId);
            if (!$permission || $permission->isDelete()) {
                return $apiResponse->error(sprintf('Permission %d introuvable.', (int) $permissionId), Response::HTTP_BAD_REQUEST);
            }
            $user->addGrantedPermission($permission);
        }

        foreach ($revokedIds as $permissionId) {
            $permission = $permissionRepository->getPermissionById((int) $permissionId);
            if (!$permission || $permission->isDelete()) {
                return $apiResponse->error(sprintf('Permission %d introuvable.', (int) $permissionId), Response::HTTP_BAD_REQUEST);
            }
            $user->addRevokedPermission($permission);
        }

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $errorsData = json_decode($serializer->serialize($errors, 'json'), true);
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $errorsData);
        }

        $userRepository->save($user);

        $jsonUser = $serializer->serialize($user, 'json', ['groups' => ['user:detail']]);
        return $apiResponse->success(json_decode($jsonUser, true), Response::HTTP_CREATED, 'Utilisateur créé avec succès.');
    }
}
