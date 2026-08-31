<?php

namespace App\Controller\TypeOrganigrammes;

use App\Entity\TypeOrganigramme;
use App\Exception\ResourceInUseException;
use App\Repository\TypeOrganigrammeRepository;
use App\Service\ApiResponseFactory;
use App\Service\ForceDeleteService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/type-organigrammes')]
#[OA\Tag(name: 'TypeOrganigrammes')]
final class DeleteTypeOrganigrammeController extends AbstractController
{
    #[Route('/{id}', name: 'app_type_organigramme_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/type-organigrammes/{id}',
        summary: 'Supprimer un type d\'organigramme',
        description: 'Par défaut, supprime logiquement (soft delete avec is_delete). Avec force=true, supprime définitivement la ligne en base.'
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
        description: 'Success - Type d\'organigramme supprimé avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Type d\'organigramme deleted successfully.')
            ],
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Type d\'organigramme deleted successfully.'
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Bad Request - Suppression définitive impossible car la ressource est liée à d\'autres données')]
    #[OA\Response(response: 404, description: 'Not Found - Type d\'organigramme non trouvé')]
    public function __invoke(
        int $id,
        Request $request,
        TypeOrganigrammeRepository $typeOrganigrammeRepository,
        ForceDeleteService $forceDeleteService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $force = filter_var($request->query->get('force', false), FILTER_VALIDATE_BOOLEAN);

        $type = $typeOrganigrammeRepository->find($id);

        if (!$type || (!$force && $type->isIsDelete())) {
            return $apiResponse->error("Le type d'organigramme demandé est introuvable.", Response::HTTP_NOT_FOUND);
        }

        if ($force) {
            try {
                $forceDeleteService->delete($type);
            } catch (ResourceInUseException $e) {
                return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
            }

            return $apiResponse->success(null, Response::HTTP_OK, 'Type d\'organigramme supprimé définitivement avec succès.');
        }

        $typeOrganigrammeRepository->softDelete($type);

        return $apiResponse->success(
            null,
            Response::HTTP_OK,
            'Type d\'organigramme deleted successfully.'
        );
    }
}
