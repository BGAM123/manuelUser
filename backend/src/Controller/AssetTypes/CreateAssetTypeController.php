<?php

namespace App\Controller\AssetTypes;

use App\Repository\AssetTypeRepository;
use App\Repository\CategoryRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/asset-types')]
#[OA\Tag(name: 'AssetTypes')]
final class CreateAssetTypeController extends AbstractController
{
    #[Route('', name: 'app_asset_type_create', methods: ['POST'])]
    #[OA\Post(path: '/asset-types', summary: 'Créer un type de bien')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            required: ['nom', 'category_id'],
            properties: [
                new OA\Property(property: 'nom', type: 'string', example: 'Véhicule léger'),
                new OA\Property(property: 'description', type: 'string', example: '4x4, berline, utilitaire léger'),
                new OA\Property(property: 'category_id', type: 'integer', example: 1),
                new OA\Property(property: 'dureeVie', type: 'integer', example: 10, nullable: true, description: 'Durée de vie estimée en années'),
                new OA\Property(property: 'taux', type: 'number', format: 'float', example: 20.00, nullable: true, description: "Taux d'amortissement"),
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Created - Type de bien créé avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 201,
                'message' => 'Type de bien créé avec succès.',
                'data' => [
                    'id' => 4,
                    'nom' => 'Véhicule léger',
                    'category_id' => 1,
                    'dureeVie' => 10,
                    'taux' => '20.00',
                    'is_delete' => false,
                ],
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Bad Request - Payload invalide ou catégorie inexistante/supprimée')]
    #[OA\Response(response: 409, description: 'Conflict - Ce nom de type de bien existe déjà')]
    public function __invoke(
        Request $request,
        AssetTypeRepository $assetTypeRepository,
        CategoryRepository $categoryRepository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $apiResponse->error('Charge JSON invalide.', Response::HTTP_BAD_REQUEST);
        }

        if (empty($payload['category_id'])) {
            return $apiResponse->error('Le champ category_id est obligatoire.', Response::HTTP_BAD_REQUEST);
        }

        $category = $categoryRepository->getActiveCategoryById((int) $payload['category_id']);
        if (!$category) {
            return $apiResponse->error('La catégorie demandée est introuvable.', Response::HTTP_NOT_FOUND);
        }

        if (!empty($payload['nom']) && $assetTypeRepository->existsByNom((string) $payload['nom'])) {
            return $apiResponse->error('Ce nom de type de bien est déjà utilisé.', Response::HTTP_CONFLICT);
        }

        $assetType = $assetTypeRepository->buildAssetTypeFromPayload($payload);
        $assetType->setCategory($category);

        $errors = $validator->validate($assetType);
        if (count($errors) > 0) {
            $errorsData = json_decode($serializer->serialize($errors, 'json'), true);
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $errorsData);
        }

        $assetTypeRepository->save($assetType);

        $data = json_decode($serializer->serialize($assetType, 'json', ['groups' => ['asset_type:detail']]), true);
        return $apiResponse->success($data, Response::HTTP_CREATED, 'Type de bien créé avec succès.');
    }
}