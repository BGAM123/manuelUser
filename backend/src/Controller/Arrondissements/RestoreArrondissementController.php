<?php

namespace App\Controller\Arrondissements;

use App\Entity\Arrondissement;
use App\Repository\ArrondissementRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/arrondissements')]
#[OA\Tag(name: 'Arrondissements')]
final class RestoreArrondissementController extends AbstractController
{
    #[Route('/{id}/restore', name: 'app_arrondissement_restore', methods: ['POST'])]
    #[OA\Post(
        path: '/arrondissements/{id}/restore',
        summary: 'Restaurer un arrondissement supprimé logiquement',
        description: 'Restaure un arrondissement soft-deleted. Échoue si l\'arrondissement n\'est pas supprimé ou si son département parent est soft-deleted.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Success - Arrondissement restauré avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Arrondissement restauré avec succès.',
                'data' => [
                    'id' => 1,
                    'nom' => 'Yaoundé 1er',
                    'code' => 'YDE1',
                    'is_delete' => false,
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
    #[OA\Response(
        response: 409,
        description: 'Conflict',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 409, 'message' => 'Resource already exists.', 'data' => null])
    )]
    public function __invoke(
        Arrondissement $arrondissement,
        ArrondissementRepository $arrondissementRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if (!$arrondissement->isDelete()) {
            return $apiResponse->error('Cet arrondissement n\'est pas supprimé.', Response::HTTP_CONFLICT);
        }

        if ($arrondissement->getDepartement()?->isDelete()) {
            return $apiResponse->error('Impossible de restaurer : le département parent est supprimé.', Response::HTTP_CONFLICT);
        }

        $arrondissementRepository->restore($arrondissement);

        $data = json_decode($serializer->serialize($arrondissement, 'json', ['groups' => ['arrondissement:detail']]), true);

        return $apiResponse->success($data, Response::HTTP_OK, 'Arrondissement restauré avec succès.');
    }
}
