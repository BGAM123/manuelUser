<?php

namespace App\Controller\Roles;

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
final class CreateRoleController extends AbstractController
{
    #[Route('', name: 'app_role_create', methods: ['POST'])]
    #[OA\Post(path: '/roles', summary: 'Créer un ou plusieurs rôles', description: 'Crée un ou plusieurs rôles avec éventuellement des permissions associées.')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            oneOf: [
                new OA\Schema(
                    type: 'object',
                    required: ['nom'],
                    properties: [
                        new OA\Property(property: 'nom', type: 'string', example: 'Gestionnaire de Biens'),
                        new OA\Property(property: 'description', type: 'string', example: 'Gère les biens du patrimoine au quotidien'),
                        new OA\Property(property: 'is_active', type: 'boolean', example: true),
                        new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3], description: 'Liste optionnelle des IDs de permissions à affecter au rôle')
                    ],
                    example: 
                    [
                        [
                            'nom' => 'Gestionnaire de Biens',
                            'description' => 'Gère les biens du patrimoine au quotidien',
                            'is_active' => true,
                            'permissions' => [1, 2, 3]
                        ],
                        [
                            'nom' => 'Auditeur',
                            'description' => 'Consultation à des fins de contrôle',
                            'is_active' => true,
                            'permissions' => [4, 5]
                        ]
                    ]
                ),
                new OA\Schema(
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        required: ['nom'],
                        properties: [
                            new OA\Property(property: 'nom', type: 'string', example: 'Gestionnaire de Biens'),
                            new OA\Property(property: 'description', type: 'string', example: 'Gère les biens du patrimoine au quotidien'),
                            new OA\Property(property: 'is_active', type: 'boolean', example: true),
                            new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3], description: 'Liste optionnelle des IDs de permissions à affecter au rôle')
                        ]
                    ),
                    example: [
                        [
                            'nom' => 'Gestionnaire de Biens',
                            'description' => 'Gère les biens du patrimoine au quotidien',
                            'is_active' => true,
                            'permissions' => [1, 2, 3]
                        ],
                        [
                            'nom' => 'Auditeur',
                            'description' => 'Consultation à des fins de contrôle',
                            'is_active' => true,
                            'permissions' => [4, 5]
                        ]
                    ]
                )
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Created - Rôle(s) créé(s) avec succès',
        content: new OA\JsonContent(
            oneOf: [
                new OA\Schema(
                    example: [
                        'success' => true,
                        'status' => 201,
                        'message' => 'Rôle créé avec succès.',
                        'data' => ['id' => 7, 'nom' => 'Auditeur', 'description' => 'Consultation à des fins de contrôle', 'is_active' => true]
                    ]
                ),
                new OA\Schema(
                    example: [
                        'success' => true,
                        'status' => 201,
                        'message' => 'Rôles créés avec succès.',
                        'data' => [
                            ['id' => 7, 'nom' => 'Gestionnaire de Biens', 'description' => 'Gère les biens du patrimoine au quotidien', 'is_active' => true],
                            ['id' => 8, 'nom' => 'Auditeur', 'description' => 'Consultation à des fins de contrôle', 'is_active' => true]
                        ]
                    ]
                )
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Bad Request - Payload invalide')]
    #[OA\Response(response: 409, description: 'Conflict - Ce nom de rôle existe déjà')]
    public function __invoke(
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

        // ✅ Détection : création multiple ou unique
        $isMultiple = $this->isArrayOfObjects($payload);

        if ($isMultiple) {
            return $this->createMultipleRoles($payload, $roleRepository, $serializer, $validator, $apiResponse);
        }

        // Création unique (compatibilité ascendante)
        return $this->createSingleRole($payload, $roleRepository, $serializer, $validator, $apiResponse);
    }

    /**
     * Vérifie si le payload est un tableau d'objets (création multiple)
     */
    private function isArrayOfObjects(array $payload): bool
    {
        if (empty($payload)) {
            return false;
        }

        // Si c'est un tableau indexé numériquement et que le premier élément est un tableau avec des clés de chaînes
        $firstKey = array_key_first($payload);
        if (is_int($firstKey)) {
            $firstItem = $payload[$firstKey];
            if (is_array($firstItem) && !empty($firstItem)) {
                $firstItemKey = array_key_first($firstItem);
                return is_string($firstItemKey);
            }
        }

        return false;
    }

    /**
     * Création multiple de rôles
     */
    private function createMultipleRoles(
        array $payloads,
        RoleRepository $roleRepository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $createdRoles = [];
        $errors = [];

        foreach ($payloads as $index => $payload) {
            if (!is_array($payload)) {
                $errors[] = "L'élément à l'index $index n'est pas un objet valide.";
                continue;
            }

            // Vérification d'unicité
            if (!empty($payload['nom']) && $roleRepository->existsByNom((string) $payload['nom'])) {
                $errors[] = "Le nom '{$payload['nom']}' à l'index $index est déjà utilisé.";
                continue;
            }

            try {
                $role = $roleRepository->buildRoleFromPayload($payload);
            } catch (\InvalidArgumentException $e) {
                $errors[] = "Erreur à l'index $index: " . $e->getMessage();
                continue;
            }

            $validationErrors = $validator->validate($role);
            if (count($validationErrors) > 0) {
                $errorsData = json_decode($serializer->serialize($validationErrors, 'json'), true);
                $errors[] = "Validation échouée pour l'élément à l'index $index: " . json_encode($errorsData);
                continue;
            }

            $roleRepository->save($role);
            $createdRoles[] = json_decode($serializer->serialize($role, 'json', ['groups' => ['role:detail']]), true);
        }

        if (!empty($errors)) {
            return $apiResponse->error('Certains rôles n\'ont pas pu être créés.', Response::HTTP_BAD_REQUEST, ['errors' => $errors, 'created' => $createdRoles]);
        }

        $message = count($createdRoles) > 1 ? 'Rôles créés avec succès.' : 'Rôle créé avec succès.';
        return $apiResponse->success($createdRoles, Response::HTTP_CREATED, $message);
    }

    /**
     * Création unique de rôle (compatibilité ascendante)
     */
    private function createSingleRole(
        array $payload,
        RoleRepository $roleRepository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if (!empty($payload['nom']) && $roleRepository->existsByNom((string) $payload['nom'])) {
            return $apiResponse->error('Ce nom de rôle est déjà utilisé.', Response::HTTP_CONFLICT);
        }

        try {
            $role = $roleRepository->buildRoleFromPayload($payload);
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
        return $apiResponse->success($data, Response::HTTP_CREATED, 'Rôle créé avec succès.');
    }
}
