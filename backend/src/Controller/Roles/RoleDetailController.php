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

#[Route('/roles')]
#[OA\Tag(name: 'Roles')]
final class RoleDetailController extends AbstractController
{
    #[Route('/{id}', name: 'app_role_detail', methods: ['GET'])]
    #[OA\Get(path: '/roles/{id}', summary: 'Détails d un rôle (avec ses permissions)', description: 'Retourne les détails d un rôle spécifique, incluant ses permissions associées.')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Success - détails du rôle retournés',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Détails du rôle retournés avec succès.',
                'data' => [
                    'id' => 1,
                    'nom' => 'Administrateur',
                    'description' => 'Accès complet à la plateforme',
                    'is_active' => true,
                    'permissions' => [
                        ['id' => 2, 'nom' => 'assign_permission_to_role', 'description' => 'Affecter une permission à un rôle', 'is_active' => true]
                    ]
                ]
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Not Found - Rôle non trouvé')]
    public function __invoke(
        int $id,
        RoleRepository $roleRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $role = $roleRepository->getRoleById($id);
        if (!$role || $role->isDelete()) {
            return $apiResponse->error('Rôle non trouvé.', Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($serializer->serialize($role, 'json', ['groups' => ['role:detail']]), true);
        return $apiResponse->success($data, Response::HTTP_OK, 'Détails du rôle retournés avec succès.');
    }
}
