<?php

namespace App\Controller\Projects;

use App\Entity\Project;
use App\Exception\ResourceInUseException;
use App\Service\ApiResponseFactory;
use App\Service\ForceDeleteService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projects')]
#[OA\Tag(name: 'Projects')]
final class DeleteProjectController extends AbstractController
{
    #[Route('/{id}', name: 'app_project_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/projects/{id}',
        summary: 'Suppression physique d\'un projet',
        description: "Supprime définitivement le projet de la base de données (entityManager->remove() + flush()). Action irréversible."
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Success - Projet supprimé définitivement',
        content: new OA\JsonContent(
            example: ['success' => true, 'status' => 200, 'message' => 'Projet supprimé définitivement avec succès.', 'data' => null]
        )
    )]
    #[OA\Response(response: 400, description: 'Bad Request - Suppression impossible car la ressource est liée à d\'autres données')]
    #[OA\Response(
        response: 404,
        description: 'Not Found',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'La ressource demandée est introuvable.', 'data' => null])
    )]
    public function __invoke(
        Project $project,
        ForceDeleteService $forceDeleteService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        try {
            $forceDeleteService->delete($project);
        } catch (ResourceInUseException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return $apiResponse->success(null, Response::HTTP_OK, 'Projet supprimé définitivement avec succès.');
    }
}
