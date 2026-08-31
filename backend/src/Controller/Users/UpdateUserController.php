<?php

namespace App\Controller\Users;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\RoleRepository;
use App\Repository\PermissionRepository;
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

#[Route('/users/{id}', name: 'app_user_edit', methods: ['PUT', 'PATCH'])]
#[OA\Tag(name: 'Users')]
final class UpdateUserController extends AbstractController
{
    #[OA\Put(
        path: '/users/{id}',
        summary: 'Mettre à jour un utilisateur',
        description: 'Remplace les détails de l utilisateur ou met à jour des champs spécifiques.'
    )]
    #[OA\Patch(
        path: '/users/{id}',
        summary: 'Mise à jour partielle d un utilisateur',
        description: 'Met à jour un ou plusieurs champs d un utilisateur existant.'
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'L identifiant unique de l utilisateur',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        description: 'JSON payload containing the fields to update',
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'firstName', type: 'string', example: 'John'),
                new OA\Property(property: 'lastName', type: 'string', example: 'Doe'),
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john.doe@example.com'),
                new OA\Property(property: 'password', type: 'string', example: 'newSecret123'),
                new OA\Property(property: 'matricule', type: 'string', example: 'MAT-001', description: 'Matricule unique de l utilisateur (optionnel, max 50 caractères)'),
                new OA\Property(property: 'cni', type: 'string', nullable: true, example: '123456789', description: 'Numéro CNI de l utilisateur (optionnel, max 255 caractères, peut être null)'),
                new OA\Property(property: 'is_active', type: 'boolean', example: true),
                new OA\Property(property: 'twoFactorEnabled', type: 'boolean', example: true, description: 'Activer ou désactiver la double authentification'),
                new OA\Property(property: 'service_id', type: 'integer', example: 2, description: 'Identifiant du service assigné à l utilisateur'),
                new OA\Property(property: 'role_ids', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2], description: 'IDs des rôles métier assignés à l utilisateur (max 2)'),
                new OA\Property(property: 'granted_permission_ids', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2], description: 'IDs de permissions accordées explicitement à l utilisateur'),
                new OA\Property(property: 'revoked_permission_ids', type: 'array', items: new OA\Items(type: 'integer'), example: [3], description: 'IDs de permissions révoquées explicitement pour l utilisateur')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Utilisateur mis à jour avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'User updated successfully.'),
                new OA\Property(property: 'data', type: 'object')
            ],
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'User updated successfully.',
                'data' => [
                    'id' => 1,
                    'firstName' => 'Jean',
                    'lastName' => 'Dupont',
                    'email' => 'jean.dupont@example.com',
                    'matricule' => 'MAT-001',
                    'cni' => '123456789',
                    'is_active' => true,
                    'twoFactorEnabled' => true,
                    'createdAt' => '01-01-2024 10:30:45',
                    'service' => [
                        'id' => 3,
                        'nom' => 'Ressources Humaines',
                        'sigle' => 'RH',
                        'type_service' => 'poste',
                        'ordre' => 2,
                        'is_active' => true
                    ]
                ]
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Bad Request', content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'La ressource demandée est introuvable.', 'data' => null]))]
    public function __invoke(
        User $user,
        UserRepository $userRepository,
        RoleRepository $roleRepository,
        PermissionRepository $permissionRepository,
        ServiceRepository $serviceRepository,
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        UserPasswordHasherInterface $passwordHasher,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $apiResponse->error('Invalid JSON payload.', Response::HTTP_BAD_REQUEST);
        }

        if (!empty($payload['service_id'])) {
            $validation = $userRepository->validateServiceAssignment((int) $payload['service_id'], $serviceRepository, $user->getId());
            if (!$validation['valid']) {
                return $apiResponse->error($validation['error'], Response::HTTP_BAD_REQUEST);
            }
        }

        $userRepository->applyPayloadToUser($user, $payload, $passwordHasher);

        // Gérer twoFactorEnabled
        if (isset($payload['twoFactorEnabled'])) {
            if (!is_bool($payload['twoFactorEnabled'])) {
                return $apiResponse->error('twoFactorEnabled must be a boolean.', Response::HTTP_BAD_REQUEST);
            }
            $user->setTwoFactorEnabled($payload['twoFactorEnabled']);

            // Si on désactive la 2FA, nettoyer les codes OTP
            if (!$payload['twoFactorEnabled']) {
                $user->setOtpCode(null);
                $user->setOtpExpiresAt(null);
            }
        }

        if (!empty($payload['service_id'])) {
            $service = $serviceRepository->getServiceById((int) $payload['service_id']);
            if ($service) {
                $user->setService($service);
            }
        } elseif (array_key_exists('service_id', $payload) && null === $payload['service_id']) {
            $user->setService(null);
        }

        // Gère les rôles métier via role_ids
        if (array_key_exists('role_ids', $payload)) {
            // Vider les rôles existants
            foreach ($user->getAssignedRoles()->toArray() as $existingRole) {
                $user->removeAssignedRole($existingRole);
            }

            // Ajouter les nouveaux rôles
            if (is_array($payload['role_ids'])) {
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
            } elseif (null !== $payload['role_ids']) {
                return $apiResponse->error('role_ids must be an array of integers or null.', Response::HTTP_BAD_REQUEST);
            }
        }

        // Gère les permissions spécifiques accordées / révoquées
        if (array_key_exists('granted_permission_ids', $payload)) {
            $currentGranted = $user->getGrantedPermissions()->toArray();
            foreach ($currentGranted as $existingPermission) {
                $user->removeGrantedPermission($existingPermission);
            }

            if (is_array($payload['granted_permission_ids'])) {
                foreach ($payload['granted_permission_ids'] as $permissionId) {
                    $permission = $permissionRepository->getPermissionById((int) $permissionId);
                    if (!$permission || $permission->isDelete()) {
                        return $apiResponse->error(sprintf('Permission %d introuvable.', (int) $permissionId), Response::HTTP_BAD_REQUEST);
                    }
                    $user->addGrantedPermission($permission);
                }
            } elseif (null !== $payload['granted_permission_ids']) {
                return $apiResponse->error('granted_permission_ids must be an array of integers or null.', Response::HTTP_BAD_REQUEST);
            }
        }

        if (array_key_exists('revoked_permission_ids', $payload)) {
            $currentRevoked = $user->getRevokedPermissions()->toArray();
            foreach ($currentRevoked as $existingPermission) {
                $user->removeRevokedPermission($existingPermission);
            }

            if (is_array($payload['revoked_permission_ids'])) {
                foreach ($payload['revoked_permission_ids'] as $permissionId) {
                    $permission = $permissionRepository->getPermissionById((int) $permissionId);
                    if (!$permission || $permission->isDelete()) {
                        return $apiResponse->error(sprintf('Permission %d introuvable.', (int) $permissionId), Response::HTTP_BAD_REQUEST);
                    }
                    $user->addRevokedPermission($permission);
                }
            } elseif (null !== $payload['revoked_permission_ids']) {
                return $apiResponse->error('revoked_permission_ids must be an array of integers or null.', Response::HTTP_BAD_REQUEST);
            }
        }

        $duplicateIds = array_intersect(
            is_array($payload['granted_permission_ids'] ?? []) ? $payload['granted_permission_ids'] : [],
            is_array($payload['revoked_permission_ids'] ?? []) ? $payload['revoked_permission_ids'] : []
        );
        if (!empty($duplicateIds)) {
            return $apiResponse->error('A permission cannot be both granted and revoked for the same user.', Response::HTTP_BAD_REQUEST);
        }

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $errorsData = json_decode($serializer->serialize($errors, 'json'), true);
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $errorsData);
        }

        $userRepository->save($user);

        $jsonUser = $serializer->serialize($user, 'json', ['groups' => ['user:detail']]);
        return $apiResponse->success(json_decode($jsonUser, true), Response::HTTP_OK, 'User updated successfully.');
    }
}
