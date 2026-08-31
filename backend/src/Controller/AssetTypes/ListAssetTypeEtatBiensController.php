<?php

namespace App\Controller\AssetTypes;

use App\Entity\AssetType;
use App\Repository\EtatBienRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/asset-types')]
#[OA\Tag(name: 'AssetTypes')]
final class ListAssetTypeEtatBiensController extends AbstractController
{
    #[Route('/{id}/etat-biens', name: 'app_asset_type_etat_biens_list', methods: ['GET'])]
    #[OA\Get(
        path: '/asset-types/{id}/etat-biens',
        summary: 'États disponibles pour un type de bien',
        description: 'Retourne uniquement les états de bien autorisés pour le type de bien demandé.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'États du type de bien retournés avec succès.',
                'data' => [
                    ['id' => 1, 'nom' => 'Bon état'],
                    ['id' => 2, 'nom' => 'En panne'],
                    ['id' => 3, 'nom' => 'Accidenté'],
                ],
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => null])
    )]
    #[OA\Response(
        response: 404,
        description: 'Not Found',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'La ressource demandée est introuvable.', 'data' => null])
    )]
    public function __invoke(
        AssetType $assetType,
        EtatBienRepository $etatBienRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($assetType->isDelete()) {
            return $apiResponse->error('Type de bien non trouvé.', Response::HTTP_NOT_FOUND);
        }

        $etats = $etatBienRepository->findActiveByAssetTypeId((int) $assetType->getId());
        $data = json_decode($serializer->serialize($etats, 'json', ['groups' => ['etat_bien:list']]), true);

        // Ne pas exposer assetTypes dans cette liste ciblée
        if (is_array($data)) {
            $data = array_map(static function (array $item): array {
                unset($item['assetTypes']);

                return $item;
            }, $data);
        }

        return $apiResponse->success($data, Response::HTTP_OK, 'États du type de bien retournés avec succès.');
    }
}
