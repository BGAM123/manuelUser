<?php

namespace App\Controller\AssetSubTypes;

use App\Repository\AssetSubTypeRepository;
use App\Repository\AssetTypeRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/asset-sub-types')]
#[OA\Tag(name: 'AssetSubTypes')]
final class CreateAssetSubTypeController extends AbstractController
{
    #[Route('', name: 'app_asset_sub_type_create', methods: ['POST'])]
    #[OA\Post(path: '/asset-sub-types', summary: 'Créer un sous-type de bien')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            required: ['nom', 'asset_type_id'],
            properties: [
                new OA\Property(property: 'nom', type: 'string', example: 'Berline'),
                new OA\Property(property: 'description', type: 'string', example: 'Véhicule léger à 4 portes'),
                new OA\Property(property: 'asset_type_id', type: 'integer', example: 1),
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Created - Sous-type de bien créé avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 201,
                'message' => 'Sous-type de bien créé avec succès.',
                'data' => ['id' => 1, 'nom' => 'Berline', 'asset_type_id' => 1, 'is_delete' => false],
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Bad Request - Payload invalide ou type de bien inexistant/supprimé')]
    #[OA\Response(response: 409, description: 'Conflict - Ce nom de sous-type de bien existe déjà')]
    public function __invoke(
        Request $request,
        AssetSubTypeRepository $assetSubTypeRepository,
        AssetTypeRepository $assetTypeRepository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $apiResponse->error('Charge JSON invalide.', Response::HTTP_BAD_REQUEST);
        }

        if (empty($payload['asset_type_id'])) {
            return $apiResponse->error('Le champ asset_type_id est obligatoire.', Response::HTTP_BAD_REQUEST);
        }

        $assetType = $assetTypeRepository->getActiveAssetTypeById((int) $payload['asset_type_id']);
        if (!$assetType) {
            return $apiResponse->error('Type de bien introuvable ou supprimé.', Response::HTTP_BAD_REQUEST);
        }

        if (!empty($payload['nom']) && $assetSubTypeRepository->existsByNom((string) $payload['nom'])) {
            return $apiResponse->error('Ce nom de sous-type de bien est déjà utilisé.', Response::HTTP_CONFLICT);
        }

        $assetSubType = $assetSubTypeRepository->buildAssetSubTypeFromPayload($payload);
        $assetSubType->setAssetType($assetType);

        $errors = $validator->validate($assetSubType);
        if (count($errors) > 0) {
            $errorsData = json_decode($serializer->serialize($errors, 'json'), true);
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $errorsData);
        }

        $assetSubTypeRepository->save($assetSubType);

        $data = json_decode($serializer->serialize($assetSubType, 'json', ['groups' => ['asset_sub_type:detail']]), true);
        return $apiResponse->success($data, Response::HTTP_CREATED, 'Sous-type de bien créé avec succès.');
    }
}