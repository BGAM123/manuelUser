<?php

namespace App\Controller\AssetTypes;

use App\Entity\AssetType;
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
final class UpdateAssetTypeController extends AbstractController
{
    #[Route('/{id}', name: 'app_asset_type_update', methods: ['PUT', 'PATCH'])]
    #[OA\Put(path: '/asset-types/{id}', summary: 'Remplacer/renommer un type de bien')]
    #[OA\Patch(path: '/asset-types/{id}', summary: 'Mettre à jour partiellement un type de bien')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'nom', type: 'string', example: 'Véhicule 4x4'),
                new OA\Property(property: 'description', type: 'string'),
                new OA\Property(property: 'category_id', type: 'integer', example: 1, description: 'Pour réaffecter le type de bien à une autre catégorie'),
                new OA\Property(property: 'dureeVie', type: 'integer', example: 10, nullable: true),
                new OA\Property(property: 'taux', type: 'number', format: 'float', example: 20.00, nullable: true),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Success - Type de bien mis à jour avec succès')]
    #[OA\Response(response: 400, description: 'Bad Request - Payload invalide ou catégorie inexistante/supprimée')]
    #[OA\Response(response: 404, description: 'Not Found - Type de bien non trouvé')]
    #[OA\Response(response: 409, description: 'Conflict - Nom déjà utilisé, ou type de bien supprimé')]
    public function __invoke(
        AssetType $assetType,
        Request $request,
        AssetTypeRepository $assetTypeRepository,
        CategoryRepository $categoryRepository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($assetType->isDelete()) {
            return $apiResponse->error(
                'Ce type de bien est supprimé.',
                Response::HTTP_CONFLICT
            );
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $apiResponse->error('Charge JSON invalide.', Response::HTTP_BAD_REQUEST);
        }

        if (
            !empty($payload['nom'])
            && $assetTypeRepository->existsByNom((string) $payload['nom'], $assetType->getId())
        ) {
            return $apiResponse->error('Ce nom de type de bien est déjà utilisé.', Response::HTTP_CONFLICT);
        }

        if (array_key_exists('category_id', $payload) && null !== $payload['category_id']) {
            $category = $categoryRepository->getActiveCategoryById((int) $payload['category_id']);
            if (!$category) {
                return $apiResponse->error('La catégorie demandée est introuvable.', Response::HTTP_NOT_FOUND);
            }
            $assetType->setCategory($category);
        }

        $assetTypeRepository->applyPayloadToAssetType($assetType, $payload);

        $errors = $validator->validate($assetType);
        if (count($errors) > 0) {
            $errorsData = json_decode($serializer->serialize($errors, 'json'), true);
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $errorsData);
        }

        $assetTypeRepository->save($assetType);

        $data = json_decode($serializer->serialize($assetType, 'json', ['groups' => ['asset_type:detail']]), true);
        return $apiResponse->success($data, Response::HTTP_OK, 'Type de bien mis à jour avec succès.');
    }
}