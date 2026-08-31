<?php

namespace App\Controller\Roles;

use App\Repository\RoleRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Liste les permissions actuellement affectées à un rôle donné.
 */
#[Route('/roles/{roleId}/permissions')]
#[OA\Tag(name: 'Roles')]
final class ListRolePermissionsController extends AbstractController
{
    #[Route('', name: 'app_role_list_permissions', methods: ['GET'])]
    #[OA\Get(
        path: '/roles/{roleId}/permissions',
        summary: 'Lister les permissions d un rôle',
        description: "Retourne la liste des permissions actuellement affectées au rôle {roleId}."
    )]
    #[OA\Parameter(name: 'roleId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Success - liste des permissions du rôle retournée',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Permissions du rôle retournées avec succès.',
                'data' => [
                    ['id' => 2, 'nom' => 'assign_permission_to_role', 'description' => 'Affecter une permission à un rôle', 'is_active' => true]
                ]
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'La ressource demandée est introuvable.', 'data' => null]))]
    public function __invoke(
        int $roleId,
        RoleRepository $roleRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $role = $roleRepository->getRoleById($roleId);
        if (!$role || $role->isDelete()) {
            return $apiResponse->error('Rôle non trouvé.', Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($serializer->serialize($role->getPermissions(), 'json', ['groups' => ['permission:list']]), true);
        return $apiResponse->success($data, Response::HTTP_OK, 'Permissions du rôle retournées avec succès.');
    }
}
