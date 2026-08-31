<?php

namespace App\Controller\EtatBiens;

use App\Repository\AssetTypeRepository;
use App\Repository\EtatBienRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/etat-biens')]
#[OA\Tag(name: 'EtatBiens')]
final class CreateEtatBienController extends AbstractController
{
    #[Route('', name: 'app_etat_bien_create', methods: ['POST'])]
    #[OA\Post(
        path: '/etat-biens',
        summary: 'Créer un ou plusieurs états de bien',
        description: 'Crée un ou plusieurs états de bien et les associe éventuellement à des types de biens via asset_type_ids.'
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            oneOf: [
                new OA\Schema(
                    type: 'object',
                    required: ['nom'],
                    properties: [
                        new OA\Property(property: 'nom', type: 'string', example: 'En panne'),
                        new OA\Property(property: 'numeroOrdre', type: 'integer', example: 1, description: 'Numéro d\'ordre pour le tri (doit être unique'),
                        new OA\Property(property: 'description', type: 'string', example: 'Bien en panne de fonctionnement', description: 'Description détaillée de l\'état (optionnel)'),
                        new OA\Property(
                            property: 'asset_type_ids',
                            type: 'array',
                            items: new OA\Items(type: 'integer'),
                            example: [1, 3],
                            description: 'IDs des types de biens associés'
                        ),
                    ],
                    example: 
                        [   
                            [
                                'nom' => 'En panne',
                                'numeroOrdre' => 1,
                                'description' => 'Bien en panne de fonctionnement',
                                'asset_type_ids' => [1, 3]
                            ],
                            [
                                'nom' => 'Hors service',
                                'numeroOrdre' => 2,
                                'description' => 'Bien hors service',
                                'asset_type_ids' => [2, 4]
                            ]
                        ]
                ),
                new OA\Schema(
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        required: ['nom'],
                        properties: [
                            new OA\Property(property: 'nom', type: 'string', example: 'En panne'),
                            new OA\Property(property: 'numeroOrdre', type: 'integer', example: 1, description: 'Numéro d\'ordre pour le tri (doit être unique'),
                            new OA\Property(property: 'description', type: 'string', example: 'Bien en panne de fonctionnement', description: 'Description détaillée de l\'état (optionnel)'),
                            new OA\Property(
                                property: 'asset_type_ids',
                                type: 'array',
                                items: new OA\Items(type: 'integer'),
                                example: [1, 3],
                                description: 'IDs des types de biens associés'
                            ),
                        ]
                    ),
                    example: [
                        [
                            'nom' => 'En panne',
                            'numeroOrdre' => 1,
                            'description' => 'Bien en panne de fonctionnement',
                            'asset_type_ids' => [1, 3]
                        ],
                        [
                            'nom' => 'Hors service',
                            'numeroOrdre' => 2,
                            'description' => 'Bien hors service',
                            'asset_type_ids' => [2, 4]
                        ]
                    ]
                )
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Created',
        content: new OA\JsonContent(
            oneOf: [
                new OA\Schema(
                    example: [
                        'success' => true,
                        'status' => 201,
                        'message' => 'État de bien créé avec succès.',
                        'data' => [
                            'id' => 1,
                            'nom' => 'En panne',
                            'numeroOrdre' => 1,
                            'description' => 'Bien en panne de fonctionnement',
                            'assetTypes' => [
                                ['id' => 1, 'nom' => 'Véhicule'],
                                ['id' => 3, 'nom' => 'Groupe électrogène'],
                            ],
                        ],
                    ]
                ),
                new OA\Schema(
                    example: [
                        'success' => true,
                        'status' => 201,
                        'message' => 'États de bien créés avec succès.',
                        'data' => [
                            [
                                'id' => 1,
                                'nom' => 'En panne',
                                'numeroOrdre' => 1,
                                'description' => 'Bien en panne de fonctionnement',
                                'assetTypes' => [
                                    ['id' => 1, 'nom' => 'Véhicule'],
                                    ['id' => 3, 'nom' => 'Groupe électrogène'],
                                ],
                            ],
                            [
                                'id' => 2,
                                'nom' => 'Hors service',
                                'numeroOrdre' => 2,
                                'description' => 'Bien hors service',
                                'assetTypes' => [
                                    ['id' => 2, 'nom' => 'Ordinateur'],
                                    ['id' => 4, 'nom' => 'Imprimante'],
                                ],
                            ],
                        ],
                    ]
                )
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Bad Request - Payload invalide ou asset_type_ids introuvables')]
    #[OA\Response(response: 409, description: 'Conflict - Nom ou numéro d\'ordre déjà utilisé')]
    public function __invoke(
        Request $request,
        EtatBienRepository $etatBienRepository,
        AssetTypeRepository $assetTypeRepository,
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
            return $this->createMultipleEtatBiens($payload, $etatBienRepository, $assetTypeRepository, $serializer, $validator, $apiResponse);
        }

        // Création unique (compatibilité ascendante)
        return $this->createSingleEtatBien($payload, $etatBienRepository, $assetTypeRepository, $serializer, $validator, $apiResponse);
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
     * Création multiple d'états de bien
     */
    private function createMultipleEtatBiens(
        array $payloads,
        EtatBienRepository $etatBienRepository,
        AssetTypeRepository $assetTypeRepository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $createdEtatBiens = [];
        $errors = [];

        foreach ($payloads as $index => $payload) {
            if (!is_array($payload)) {
                $errors[] = "L'élément à l'index $index n'est pas un objet valide.";
                continue;
            }

            // Vérification d'unicité du nom
            if (!empty($payload['nom']) && $etatBienRepository->existsByNom((string) $payload['nom'])) {
                $errors[] = "Le nom '{$payload['nom']}' à l'index $index est déjà utilisé.";
                continue;
            }

            // Validation du numéro d'ordre
            if (array_key_exists('numeroOrdre', $payload) && $payload['numeroOrdre'] !== null) {
                if ($etatBienRepository->existsByNumeroOrdre((int) $payload['numeroOrdre'])) {
                    $errors[] = "Le numéro d'ordre {$payload['numeroOrdre']} à l'index $index est déjà utilisé.";
                    continue;
                }
            }

            $etatBien = $etatBienRepository->buildFromPayload($payload);

            // Association des types de biens
            if (array_key_exists('asset_type_ids', $payload)) {
                if (!is_array($payload['asset_type_ids'])) {
                    $errors[] = "asset_type_ids doit être un tableau à l'index $index.";
                    continue;
                }
                $ids = array_values(array_unique(array_map('intval', $payload['asset_type_ids'])));
                $assetTypes = $assetTypeRepository->findActiveByIds($ids);
                if (count($assetTypes) !== count($ids)) {
                    $errors[] = "Un ou plusieurs types de biens sont introuvables ou supprimés à l'index $index.";
                    continue;
                }
                $etatBien->syncAssetTypes($assetTypes);
            }

            $validationErrors = $validator->validate($etatBien);
            if (count($validationErrors) > 0) {
                $errorsData = json_decode($serializer->serialize($validationErrors, 'json'), true);
                $errors[] = "Validation échouée pour l'élément à l'index $index: " . json_encode($errorsData);
                continue;
            }

            $etatBienRepository->save($etatBien);
            $createdEtatBiens[] = json_decode($serializer->serialize($etatBien, 'json', ['groups' => ['etat_bien:detail']]), true);
        }

        if (!empty($errors)) {
            return $apiResponse->error('Certains états de bien n\'ont pas pu être créés.', Response::HTTP_BAD_REQUEST, ['errors' => $errors, 'created' => $createdEtatBiens]);
        }

        $message = count($createdEtatBiens) > 1 ? 'États de bien créés avec succès.' : 'État de bien créé avec succès.';
        return $apiResponse->success($createdEtatBiens, Response::HTTP_CREATED, $message);
    }

    /**
     * Création unique d'état de bien (compatibilité ascendante)
     */
    private function createSingleEtatBien(
        array $payload,
        EtatBienRepository $etatBienRepository,
        AssetTypeRepository $assetTypeRepository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if (!empty($payload['nom']) && $etatBienRepository->existsByNom((string) $payload['nom'])) {
            return $apiResponse->error('Ce nom d\'état de bien est déjà utilisé.', Response::HTTP_CONFLICT);
        }

        // ✅ Validation du numéro d'ordre
        if (array_key_exists('numeroOrdre', $payload) && $payload['numeroOrdre'] !== null) {
            if ($etatBienRepository->existsByNumeroOrdre((int) $payload['numeroOrdre'])) {
                return $apiResponse->error(
                    'Ce numéro d\'ordre est déjà utilisé. Veuillez en choisir un autre.',
                    Response::HTTP_CONFLICT
                );
            }
        }

        $etatBien = $etatBienRepository->buildFromPayload($payload);

        if (array_key_exists('asset_type_ids', $payload)) {
            if (!is_array($payload['asset_type_ids'])) {
                return $apiResponse->error('asset_type_ids doit être un tableau.', Response::HTTP_BAD_REQUEST);
            }
            $ids = array_values(array_unique(array_map('intval', $payload['asset_type_ids'])));
            $assetTypes = $assetTypeRepository->findActiveByIds($ids);
            if (count($assetTypes) !== count($ids)) {
                return $apiResponse->error('Un ou plusieurs types de biens sont introuvables ou supprimés.', Response::HTTP_BAD_REQUEST);
            }
            $etatBien->syncAssetTypes($assetTypes);
        }

        $errors = $validator->validate($etatBien);
        if (count($errors) > 0) {
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, json_decode($serializer->serialize($errors, 'json'), true));
        }

        $etatBienRepository->save($etatBien);
        $data = json_decode($serializer->serialize($etatBien, 'json', ['groups' => ['etat_bien:detail']]), true);

        return $apiResponse->success($data, Response::HTTP_CREATED, 'État de bien créé avec succès.');
    }
}
