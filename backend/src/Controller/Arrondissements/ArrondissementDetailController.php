<?php

namespace App\Controller\Arrondissements;

use App\Entity\Arrondissement;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/arrondissements')]
#[OA\Tag(name: 'Arrondissements')]
final class ArrondissementDetailController extends AbstractController
{
    #[Route('/{id}', name: 'app_arrondissement_detail', methods: ['GET'])]
    #[OA\Get(
        path: '/arrondissements/{id}',
        summary: 'Détail d\'un arrondissement',
        description: 'Retourne le détail d\'un arrondissement actif (non soft-deleted) à partir de son identifiant, avec son département et sa région.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Success - Détail arrondissement retourné',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Arrondissement retourné avec succès.',
                'data' => [
                    'id' => 1,
                    'nom' => 'Yaoundé 1er',
                    'code' => 'YDE1',
                    'is_delete' => false,
                    'createdAt' => '2024-01-15T10:00:00+00:00',
                    'updatedAt' => '2024-06-01T12:30:00+00:00',
                    'departement_id' => 1,
                    'departement' => [
                        'id' => 1,
                        'nom' => 'Mfoundi',
                        'region' => ['id' => 1, 'nom' => 'Centre'],
                    ],
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
        Arrondissement $arrondissement,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($arrondissement->isDelete()) {
            return $apiResponse->error('Arrondissement non trouvé.', Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($serializer->serialize($arrondissement, 'json', ['groups' => ['arrondissement:detail']]), true);

        return $apiResponse->success($data, Response::HTTP_OK, 'Arrondissement retourné avec succès.');
    }
}
