<?php

namespace App\Controller\EtatBiens;

use App\Entity\EtatBien;
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
final class UpdateEtatBienController extends AbstractController
{
    #[Route('/{id}', name: 'app_etat_bien_update', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/etat-biens/{id}',
        summary: 'Mettre à jour un état de bien',
        description: 'Met à jour le nom et synchronise complètement les types de biens associés si asset_type_ids est fourni.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'nom', type: 'string', example: 'En maintenance'),
                new OA\Property(property: 'numeroOrdre', type: 'integer', example: 2, description: 'Numéro d\'ordre pour le tri (doit être unique'),
                new OA\Property(property: 'description', type: 'string', example: 'Bien en cours de maintenance', description: 'Description détaillée de l\'état (optionnel)'),
                new OA\Property(
                    property: 'asset_type_ids',
                    type: 'array',
                    items: new OA\Items(type: 'integer'),
                    example: [1, 2, 5]
                ),
            ],
            example: ['nom' => 'En maintenance', 'numeroOrdre' => 2, 'description' => 'Bien en cours de maintenance', 'asset_type_ids' => [1, 2, 5]]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'État de bien mis à jour avec succès.',
                'data' => [
                    'id' => 1,
                    'nom' => 'En maintenance',
                    'numeroOrdre' => 2,
                    'description' => 'Bien en cours de maintenance',
                    'assetTypes' => [['id' => 1, 'nom' => 'Véhicule']],
                ],
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Bad Request')]
    #[OA\Response(response: 404, description: 'Not Found')]
    #[OA\Response(response: 409, description: 'Conflict')]
    public function __invoke(
        EtatBien $etatBien,
        Request $request,
        EtatBienRepository $etatBienRepository,
        AssetTypeRepository $assetTypeRepository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($etatBien->isDelete()) {
            return $apiResponse->error('Cet état de bien est supprimé.', Response::HTTP_CONFLICT);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $apiResponse->error('Charge JSON invalide.', Response::HTTP_BAD_REQUEST);
        }

        if (!empty($payload['nom']) && $etatBienRepository->existsByNom((string) $payload['nom'], $etatBien->getId())) {
            return $apiResponse->error('Ce nom d\'état de bien est déjà utilisé.', Response::HTTP_CONFLICT);
        }

        // ✅ Validation du numéro d'ordre
        // if (array_key_exists('numeroOrdre', $payload) && $payload['numeroOrdre'] !== null) {
        //     if ($etatBienRepository->existsByNumeroOrdre((int) $payload['numeroOrdre'])) {
        //         return $apiResponse->error(
        //             'Ce numéro d\'ordre est déjà utilisé. Veuillez en choisir un autre.',
        //             Response::HTTP_CONFLICT
        //         );
        //     }
        // }

        // ✅ Validation du numéro d'ordre
        if (array_key_exists('numeroOrdre', $payload) && $payload['numeroOrdre'] !== null) {
            if ($etatBienRepository->existsByNumeroOrdre((int) $payload['numeroOrdre'], $etatBien->getId())) {
                return $apiResponse->error(
                    'Ce numéro d\'ordre est déjà utilisé. Veuillez en choisir un autre.',
                    Response::HTTP_CONFLICT
                );
            }
        }

        $etatBienRepository->applyPayload($etatBien, $payload);

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

        return $apiResponse->success($data, Response::HTTP_OK, 'État de bien mis à jour avec succès.');
    }
}
