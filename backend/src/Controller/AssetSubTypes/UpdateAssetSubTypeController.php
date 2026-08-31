<?php

namespace App\Controller\AssetSubTypes;

use App\Entity\AssetSubType;
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
final class UpdateAssetSubTypeController extends AbstractController
{
    #[Route('/{id}', name: 'app_asset_sub_type_update', methods: ['PUT', 'PATCH'])]
    #[OA\Put(path: '/asset-sub-types/{id}', summary: 'Remplacer/renommer un sous-type de bien')]
    #[OA\Patch(path: '/asset-sub-types/{id}', summary: 'Mettre à jour partiellement un sous-type de bien')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'nom', type: 'string', example: 'Berline climatisée'),
                new OA\Property(property: 'description', type: 'string'),
                new OA\Property(property: 'asset_type_id', type: 'integer', example: 1, description: 'Pour réaffecter le sous-type à un autre type de bien'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Success - Sous-type de bien mis à jour avec succès')]
    #[OA\Response(response: 400, description: 'Bad Request - Payload invalide ou type de bien inexistant/supprimé')]
    #[OA\Response(response: 404, description: 'Not Found - Sous-type de bien non trouvé')]
    #[OA\Response(response: 409, description: 'Conflict - Nom déjà utilisé, ou sous-type supprimé (à restaurer avant modification)')]
    public function __invoke(
        AssetSubType $assetSubType,
        Request $request,
        AssetSubTypeRepository $assetSubTypeRepository,
        AssetTypeRepository $assetTypeRepository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($assetSubType->isDelete()) {
            return $apiResponse->error(
                'Ce sous-type de bien est supprimé. Restaurez-le avant de le modifier.',
                Response::HTTP_CONFLICT
            );
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $apiResponse->error('Charge JSON invalide.', Response::HTTP_BAD_REQUEST);
        }

        if (
            !empty($payload['nom'])
            && $assetSubTypeRepository->existsByNom((string) $payload['nom'], $assetSubType->getId())
        ) {
            return $apiResponse->error('Ce nom de sous-type de bien est déjà utilisé.', Response::HTTP_CONFLICT);
        }

        if (array_key_exists('asset_type_id', $payload) && null !== $payload['asset_type_id']) {
            $assetType = $assetTypeRepository->getActiveAssetTypeById((int) $payload['asset_type_id']);
            if (!$assetType) {
                return $apiResponse->error('Type de bien introuvable ou supprimé.', Response::HTTP_BAD_REQUEST);
            }
            $assetSubType->setAssetType($assetType);
        }

        $assetSubTypeRepository->applyPayloadToAssetSubType($assetSubType, $payload);

        $errors = $validator->validate($assetSubType);
        if (count($errors) > 0) {
            $errorsData = json_decode($serializer->serialize($errors, 'json'), true);
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $errorsData);
        }

        $assetSubTypeRepository->save($assetSubType);

        $data = json_decode($serializer->serialize($assetSubType, 'json', ['groups' => ['asset_sub_type:detail']]), true);
        return $apiResponse->success($data, Response::HTTP_OK, 'Sous-type de bien mis à jour avec succès.');
    }
}