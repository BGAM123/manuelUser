<?php

namespace App\Controller\Inputs;

use App\Exception\ResourceNotFoundException;
use App\Service\ApiResponseFactory;
use App\Service\InputService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/inputs')]
#[OA\Tag(name: 'Inputs')]
final class RestoreInputController extends AbstractController
{
    #[Route('/{id}/restore', name: 'app_input_restore', methods: ['PUT'])]
    #[OA\Put(
        path: '/inputs/{id}/restore',
        summary: 'Restaurer une valeur de champ',
        description: 'Restaure une valeur de champ supprimée.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Valeur restaurée avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Valeur restaurée avec succès.',
                'data' => null
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Valeur introuvable')]
    public function __invoke(
        int $id,
        InputService $inputService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        try {
            $inputService->restore($id);
        } catch (ResourceNotFoundException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_NOT_FOUND);
        }

        return $apiResponse->success(
            null,
            Response::HTTP_OK,
            'Valeur restaurée avec succès.'
        );
    }
}