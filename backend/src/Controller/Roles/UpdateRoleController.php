<?php

namespace App\Controller\Roles;

use App\Entity\Role;
use App\Repository\RoleRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/roles')]
#[OA\Tag(name: 'Roles')]
final class UpdateRoleController extends AbstractController
{
    #[Route('/{id}', name: 'app_role_update', methods: ['PUT', 'PATCH'])]
    #[OA\Put(path: '/roles/{id}', summary: 'Remplacer un rôle', description: 'Remplace entièrement les champs d un rôle existant.')]
    #[OA\Patch(path: '/roles/{id}', summary: 'Mettre à jour partiellement un rôle', description: 'Met à jour un ou plusieurs champs d un rôle existant.')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'nom', type: 'string', example: 'Gestionnaire de Biens'),
                new OA\Property(property: 'description', type: 'string', example: 'Gère les biens du patrimoine au quotidien'),
                new OA\Property(property: 'is_active', type: 'boolean', example: false),
                new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3], description: 'Liste optionnelle des IDs de permissions à synchroniser')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Rôle mis à jour avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Rôle mis à jour avec succès.',
                'data' => ['id' => 7, 'nom' => 'Auditeur', 'description' => 'Consultation à des fins de contrôle', 'is_active' => false]
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Bad Request - Payload invalide')]
    #[OA\Response(response: 404, description: 'Not Found - Rôle non trouvé')]
    #[OA\Response(response: 409, description: 'Conflict - Ce nom de rôle existe déjà')]
    public function __invoke(
        Role $role,
        Request $request,
        RoleRepository $roleRepository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $apiResponse->error('Charge JSON invalide.', Response::HTTP_BAD_REQUEST);
        }

        if (!empty($payload['nom']) && $roleRepository->existsByNom((string) $payload['nom'], $role->getId())) {
            return $apiResponse->error('Ce nom de rôle est déjà utilisé.', Response::HTTP_CONFLICT);
        }

        try {
            $roleRepository->applyPayloadToRole($role, $payload);
        } catch (\InvalidArgumentException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $errors = $validator->validate($role);
        if (count($errors) > 0) {
            $errorsData = json_decode($serializer->serialize($errors, 'json'), true);
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $errorsData);
        }

        $roleRepository->save($role);

        $data = json_decode($serializer->serialize($role, 'json', ['groups' => ['role:detail']]), true);
        return $apiResponse->success($data, Response::HTTP_OK, 'Rôle mis à jour avec succès.');
    }
}
