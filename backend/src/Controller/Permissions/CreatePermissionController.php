<?php

namespace App\Controller\Permissions;

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
final class CreatePermissionController extends AbstractController
{
    #[Route('', name: 'app_permission_create', methods: ['POST'])]
    #[OA\Post(path: '/permissions', summary: 'Créer une ou plusieurs permissions')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            oneOf: [
                new OA\Schema(
                    type: 'object',
                    required: ['nom'],
                    properties: [
                        new OA\Property(property: 'nom', type: 'string', example: 'gerer_biens'),
                        new OA\Property(property: 'description', type: 'string', example: 'Gérer les biens du patrimoine'),
                        new OA\Property(property: 'is_active', type: 'boolean', example: true)
                    ],
                    example: 
                    [
                        [
                            'nom' => 'gerer_biens',
                            'description' => 'Gérer les biens du patrimoine',
                            'is_active' => true
                        ],
                        [
                            'nom' => 'voir_rapports',
                            'description' => 'Consulter les rapports',
                            'is_active' => true
                        ]
                    ]
                ),
                new OA\Schema(
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        required: ['nom'],
                        properties: [
                            new OA\Property(property: 'nom', type: 'string', example: 'gerer_biens'),
                            new OA\Property(property: 'description', type: 'string', example: 'Gérer les biens du patrimoine'),
                            new OA\Property(property: 'is_active', type: 'boolean', example: true)
                        ]
                    ),
                    example: [
                        [
                            'nom' => 'gerer_biens',
                            'description' => 'Gérer les biens du patrimoine',
                            'is_active' => true
                        ],
                        [
                            'nom' => 'voir_rapports',
                            'description' => 'Consulter les rapports',
                            'is_active' => true
                        ]
                    ]
                )
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Created - Permission(s) créée(s) avec succès',
        content: new OA\JsonContent(
            oneOf: [
                new OA\Schema(
                    example: [
                        'success' => true,
                        'status' => 201,
                        'message' => 'Permission créée avec succès.',
                        'data' => ['id' => 5, 'nom' => 'voir_rapports', 'description' => 'Consulter les rapports', 'is_active' => true]
                    ]
                ),
                new OA\Schema(
                    example: [
                        'success' => true,
                        'status' => 201,
                        'message' => 'Permissions créées avec succès.',
                        'data' => [
                            ['id' => 5, 'nom' => 'gerer_biens', 'description' => 'Gérer les biens du patrimoine', 'is_active' => true],
                            ['id' => 6, 'nom' => 'voir_rapports', 'description' => 'Consulter les rapports', 'is_active' => true]
                        ]
                    ]
                )
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Bad Request - Payload invalide')]
    #[OA\Response(response: 409, description: 'Conflict - Ce nom de permission existe déjà')]
    public function __invoke(
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

        // ✅ Détection : création multiple ou unique
        $isMultiple = $this->isArrayOfObjects($payload);

        if ($isMultiple) {
            return $this->createMultiplePermissions($payload, $permissionRepository, $serializer, $validator, $apiResponse);
        }

        // Création unique (compatibilité ascendante)
        return $this->createSinglePermission($payload, $permissionRepository, $serializer, $validator, $apiResponse);
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
     * Création multiple de permissions
     */
    private function createMultiplePermissions(
        array $payloads,
        PermissionRepository $permissionRepository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $createdPermissions = [];
        $errors = [];

        foreach ($payloads as $index => $payload) {
            if (!is_array($payload)) {
                $errors[] = "L'élément à l'index $index n'est pas un objet valide.";
                continue;
            }

            // Vérification d'unicité
            if (!empty($payload['nom']) && $permissionRepository->existsByNom((string) $payload['nom'])) {
                $errors[] = "Le nom '{$payload['nom']}' à l'index $index est déjà utilisé.";
                continue;
            }

            $permission = $permissionRepository->buildPermissionFromPayload($payload);

            $validationErrors = $validator->validate($permission);
            if (count($validationErrors) > 0) {
                $errorsData = json_decode($serializer->serialize($validationErrors, 'json'), true);
                $errors[] = "Validation échouée pour l'élément à l'index $index: " . json_encode($errorsData);
                continue;
            }

            $permissionRepository->save($permission);
            $createdPermissions[] = json_decode($serializer->serialize($permission, 'json', ['groups' => ['permission:detail']]), true);
        }

        if (!empty($errors)) {
            return $apiResponse->error('Certaines permissions n\'ont pas pu être créées.', Response::HTTP_BAD_REQUEST, ['errors' => $errors, 'created' => $createdPermissions]);
        }

        $message = count($createdPermissions) > 1 ? 'Permissions créées avec succès.' : 'Permission créée avec succès.';
        return $apiResponse->success($createdPermissions, Response::HTTP_CREATED, $message);
    }

    /**
     * Création unique de permission (compatibilité ascendante)
     */
    private function createSinglePermission(
        array $payload,
        PermissionRepository $permissionRepository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        // Vérification explicite d'unicité AVANT la validation métier, afin de pouvoir
        // renvoyer un 409 Conflict distinct des erreurs de validation classiques (400).
        if (!empty($payload['nom']) && $permissionRepository->existsByNom((string) $payload['nom'])) {
            return $apiResponse->error('Ce nom de permission est déjà utilisé.', Response::HTTP_CONFLICT);
        }

        $permission = $permissionRepository->buildPermissionFromPayload($payload);

        $errors = $validator->validate($permission);
        if (count($errors) > 0) {
            $errorsData = json_decode($serializer->serialize($errors, 'json'), true);
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $errorsData);
        }

        $permissionRepository->save($permission);

        $data = json_decode($serializer->serialize($permission, 'json', ['groups' => ['permission:detail']]), true);
        return $apiResponse->success($data, Response::HTTP_CREATED, 'Permission créée avec succès.');
    }
}
