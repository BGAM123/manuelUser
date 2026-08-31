<?php

namespace App\Controller\Permissions;

use App\Entity\Permission;
use App\Repository\PermissionRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/permissions')]
#[OA\Tag(name: 'Permissions')]
final class UpdatePermissionController extends AbstractController
{
    #[Route('/{id}', name: 'app_permission_update', methods: ['PUT', 'PATCH'])]
    #[OA\Put(path: '/permissions/{id}', summary: 'Remplacer une permission')]
    #[OA\Patch(path: '/permissions/{id}', summary: 'Mettre à jour partiellement une permission')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'nom', type: 'string', example: 'gerer_biens'),
                new OA\Property(property: 'description', type: 'string', example: 'Gérer les biens du patrimoine'),
                new OA\Property(property: 'is_active', type: 'boolean', example: true)
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Permission mise à jour avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Permission mise à jour avec succès.',
                'data' => ['id' => 5, 'nom' => 'voir_rapports', 'description' => 'Consulter les rapports', 'is_active' => true]
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Bad Request - Payload invalide')]
    #[OA\Response(response: 404, description: 'Not Found - Permission non trouvée')]
    #[OA\Response(response: 409, description: 'Conflict - Ce nom de permission existe déjà')]
    public function __invoke(
        Permission $permission,
        Request $request,
        PermissionRepository $permissionRepository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $apiResponse->error('Charge JSON invalide.', Response::HTTP_BAD_REQUEST);
        }

        if (!empty($payload['nom']) && $permissionRepository->existsByNom((string) $payload['nom'], $permission->getId())) {
            return $apiResponse->error('Ce nom de permission est déjà utilisé.', Response::HTTP_CONFLICT);
        }

        $permissionRepository->applyPayloadToPermission($permission, $payload);

        $errors = $validator->validate($permission);
        if (count($errors) > 0) {
            $errorsData = json_decode($serializer->serialize($errors, 'json'), true);
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $errorsData);
        }

        $permissionRepository->save($permission);

        $data = json_decode($serializer->serialize($permission, 'json', ['groups' => ['permission:detail']]), true);
        return $apiResponse->success($data, Response::HTTP_OK, 'Permission mise à jour avec succès.');
    }
}
