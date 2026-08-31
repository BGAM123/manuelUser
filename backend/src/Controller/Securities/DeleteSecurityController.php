<?php

namespace App\Controller\Securities;

use App\Exception\ResourceInUseException;
use App\Exception\ResourceNotFoundException;
use App\Service\ApiResponseFactory;
use App\Service\SecurityService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/securities')]
#[OA\Tag(name: 'Securities')]
final class DeleteSecurityController extends AbstractController
{
    #[Route('/{id}', name: 'app_security_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/securities/{id}',
        summary: 'Supprimer une sécurisation',
        description: 'Par défaut, supprime logiquement une sécurisation (les relations avec les biens et les documents sont également supprimées logiquement). Avec force=true, supprime définitivement la ligne en base.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Parameter(
        name: 'force',
        in: 'query',
        schema: new OA\Schema(type: 'boolean', default: false),
        description: 'true = suppression définitive (hard delete) au lieu de la suppression logique par défaut.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Sécurisation supprimée avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Sécurisation supprimée avec succès.',
                'data' => null
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Bad Request - Suppression définitive impossible car la ressource est liée à d\'autres données', content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'Impossible de supprimer définitivement cette ressource car elle est liée à d\'autres données.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Sécurisation introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'La sécurisation demandée n\'existe pas.', 'data' => null]))]
    public function __invoke(
        int $id,
        Request $request,
        SecurityService $securityService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $force = filter_var($request->query->get('force', false), FILTER_VALIDATE_BOOLEAN);

        try {
            if ($force) {
                $securityService->deleteForced($id);

                return $apiResponse->success(null, Response::HTTP_OK, 'Sécurisation supprimée définitivement avec succès.');
            }

            $securityService->delete($id);
        } catch (ResourceNotFoundException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (ResourceInUseException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return $apiResponse->success(
            null,
            Response::HTTP_OK,
            'Sécurisation supprimée avec succès.'
        );
    }
}
