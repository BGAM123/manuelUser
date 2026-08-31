<?php

namespace App\Controller\Permissions;

use App\Repository\PermissionRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/permissions')]
#[OA\Tag(name: 'Permissions')]
final class PermissionDetailController extends AbstractController
{
    #[Route('/{id}', name: 'app_permission_detail', methods: ['GET'])]
    #[OA\Get(path: '/permissions/{id}', summary: 'Détails d une permission')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Success - détails de la permission retournés',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Détails de la permission retournés avec succès.',
                'data' => ['id' => 1, 'nom' => 'gerer_biens', 'description' => 'Gérer les biens du patrimoine', 'is_active' => true]
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Not Found - Permission non trouvée')]
    public function __invoke(
        int $id,
        PermissionRepository $permissionRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $permission = $permissionRepository->getPermissionById($id);
        if (!$permission || $permission->isDelete()) {
            return $apiResponse->error('Permission non trouvée.', Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($serializer->serialize($permission, 'json', ['groups' => ['permission:detail']]), true);
        return $apiResponse->success($data, Response::HTTP_OK, 'Détails de la permission retournés avec succès.');
    }
}
