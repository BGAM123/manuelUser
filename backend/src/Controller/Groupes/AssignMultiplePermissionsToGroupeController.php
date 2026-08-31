<?php

namespace App\Controller\Groupes;

use App\Repository\GroupeRepository;
use App\Repository\PermissionRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/groupes/{groupeId}/permissions')]
#[OA\Tag(name: 'Groupes')]
final class AssignMultiplePermissionsToGroupeController extends AbstractController
{
    #[Route('', name: 'app_groupe_assign_multiple_permissions', methods: ['POST'])]
    #[OA\Post(
        path: '/groupes/{groupeId}/permissions',
        summary: 'Affecter plusieurs permissions à un groupe',
        description: "Ajoute les permissions spécifiées à la liste des permissions du groupe {groupeId}. Évite les doublons automatiquement."
    )]
    #[OA\Parameter(name: 'groupeId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            required: ['permission_ids'],
            properties: [new OA\Property(property: 'permission_ids', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3])]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Created - Permissions affectées au groupe avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 201,
                'message' => 'Permissions affectées au groupe avec succès.',
                'data' => [
                    'id' => 1,
                    'nom' => 'SECRETAIRE',
                    'is_active' => true,
                    'permissions' => [
                        ['id' => 1, 'nom' => 'USER_CREATE', 'is_active' => true],
                        ['id' => 2, 'nom' => 'USER_DELETE', 'is_active' => true]
                    ]
                ]
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Bad Request', content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'La ressource demandée est introuvable.', 'data' => null]))]
    public function __invoke(
        int $groupeId,
        Request $request,
        GroupeRepository $groupeRepository,
        PermissionRepository $permissionRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $groupe = $groupeRepository->getGroupeById($groupeId);
        if (!$groupe || $groupe->isDelete()) {
            return $apiResponse->error('Groupe non trouvé.', Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload) || !isset($payload['permission_ids']) || !is_array($payload['permission_ids']) || empty($payload['permission_ids'])) {
            return $apiResponse->error('permission_ids is required and must be a non-empty array.', Response::HTTP_BAD_REQUEST);
        }

        $permissionIds = array_unique(array_map('intval', $payload['permission_ids']));

        $permissions = [];
        foreach ($permissionIds as $permissionId) {
            $permission = $permissionRepository->getPermissionById($permissionId);
            if (!$permission || $permission->isDelete()) {
                return $apiResponse->error(sprintf('Permission %d introuvable.', $permissionId), Response::HTTP_BAD_REQUEST);
            }
            $permissions[] = $permission;
        }

        $assignedCount = 0;
        foreach ($permissions as $permission) {
            if (!$groupe->hasPermission($permission)) {
                $groupe->addPermission($permission);
                $assignedCount++;
            }
        }

        $groupeRepository->save($groupe);

        $data = json_decode($serializer->serialize($groupe, 'json', ['groups' => ['groupe:detail']]), true);
        return $apiResponse->success($data, Response::HTTP_CREATED, sprintf('Permissions affectées au groupe avec succès. %d nouvelles relations créées.', $assignedCount));
    }
}