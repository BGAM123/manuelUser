<?php

namespace App\Controller\Groupes;

use App\Repository\GroupeRepository;
use App\Repository\PermissionRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/groupes/{groupeId}/permissions/{permissionId}')]
#[OA\Tag(name: 'Groupes')]
final class RemovePermissionFromGroupeController extends AbstractController
{
    #[Route('', name: 'app_groupe_remove_permission', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/groupes/{groupeId}/permissions/{permissionId}',
        summary: 'Retirer une permission d\'un groupe',
        description: "Supprime uniquement l'association entre le groupe {groupeId} et la permission {permissionId}. Ni le groupe ni la permission ne sont supprimés."
    )]
    #[OA\Parameter(name: 'groupeId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'permissionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Success - Permission retirée du groupe',
        content: new OA\JsonContent(
            example: ['success' => true, 'status' => 200, 'message' => 'Permission retirée du groupe avec succès.', 'data' => null]
        )
    )]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'La ressource demandée est introuvable.', 'data' => null]))]
    public function __invoke(
        int $groupeId,
        int $permissionId,
        GroupeRepository $groupeRepository,
        PermissionRepository $permissionRepository,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $groupe = $groupeRepository->getGroupeById($groupeId);
        if (!$groupe || $groupe->isDelete()) {
            return $apiResponse->error('Groupe non trouvé.', Response::HTTP_NOT_FOUND);
        }

        $permission = $permissionRepository->getPermissionById($permissionId);
        if (!$permission) {
            return $apiResponse->error('Permission non trouvée.', Response::HTTP_NOT_FOUND);
        }

        $removed = $groupeRepository->unassignPermission($groupe, $permission);
        if (!$removed) {
            return $apiResponse->error("Cette permission n'est pas affectée à ce groupe.", Response::HTTP_NOT_FOUND);
        }

        return $apiResponse->success(null, Response::HTTP_OK, 'Permission retirée du groupe avec succès.');
    }
}