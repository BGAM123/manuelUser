<?php

namespace App\Controller\Inputs;

use App\Exception\ResourceInUseException;
use App\Exception\ResourceNotFoundException;
use App\Service\ApiResponseFactory;
use App\Service\InputService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/inputs')]
#[OA\Tag(name: 'Inputs')]
final class DeleteInputController extends AbstractController
{
    #[Route('/{id}', name: 'app_input_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/inputs/{id}',
        summary: 'Supprimer une valeur de champ',
        description: 'Par défaut, supprime logiquement une valeur de champ. Avec force=true, supprime définitivement la ligne en base.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(
        name: 'force',
        in: 'query',
        schema: new OA\Schema(type: 'boolean', default: false),
        description: 'true = suppression définitive (hard delete) au lieu de la suppression logique par défaut.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Valeur supprimée avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Valeur supprimée avec succès.',
                'data' => null
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Bad Request - Suppression définitive impossible car la ressource est liée à d\'autres données')]
    #[OA\Response(response: 404, description: 'Valeur introuvable')]
    public function __invoke(
        int $id,
        Request $request,
        InputService $inputService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $force = filter_var($request->query->get('force', false), FILTER_VALIDATE_BOOLEAN);

        try {
            if ($force) {
                $inputService->deleteForced($id);

                return $apiResponse->success(null, Response::HTTP_OK, 'Valeur supprimée définitivement avec succès.');
            }

            $inputService->delete($id);
        } catch (ResourceNotFoundException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (ResourceInUseException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return $apiResponse->success(
            null,
            Response::HTTP_OK,
            'Valeur supprimée avec succès.'
        );
    }
}