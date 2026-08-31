<?php

namespace App\Controller\Roles;

use App\Entity\Role;
use App\Repository\RoleRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/roles')]
#[OA\Tag(name: 'Roles')]
final class SoftDeleteRoleController extends AbstractController
{
    #[Route('/{id}/soft-delete', name: 'app_role_soft_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/roles/{id}/soft-delete',
        summary: 'Suppression logique d un rôle',
        description: "Passe is_delete (et is_active) à true : le rôle reste en base (les utilisateurs qui l'ont encore ne perdent pas leur historique) mais disparaît des listes actives."
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Success - Rôle désactivé (suppression logique)',
        content: new OA\JsonContent(
            example: ['success' => true, 'status' => 200, 'message' => 'Rôle supprimé (logiquement) avec succès.', 'data' => null]
        )
    )]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'La ressource demandée est introuvable.', 'data' => null]))]
    public function __invoke(
        Role $role,
        RoleRepository $roleRepository,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $roleRepository->softDelete($role);

        return $apiResponse->success(null, Response::HTTP_OK, 'Rôle supprimé (logiquement) avec succès.');
    }
}
