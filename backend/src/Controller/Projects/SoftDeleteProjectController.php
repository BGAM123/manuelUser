<?php

namespace App\Controller\Projects;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projects')]
#[OA\Tag(name: 'Projects')]
final class SoftDeleteProjectController extends AbstractController
{
    #[Route('/{id}/soft-delete', name: 'app_project_soft_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/projects/{id}/soft-delete',
        summary: 'Suppression logique d\'un projet',
        description: "Passe is_delete à true : le projet reste consultable en historique (utilisateurs affectés conservés) mais disparaît des listes actives."
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Success - Projet archivé (suppression logique)',
        content: new OA\JsonContent(
            example: ['success' => true, 'status' => 200, 'message' => 'Projet supprimé (logiquement) avec succès.', 'data' => null]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Not Found',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'La ressource demandée est introuvable.', 'data' => null])
    )]
    public function __invoke(
        Project $project,
        ProjectRepository $projectRepository,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $projectRepository->softDelete($project);

        return $apiResponse->success(null, Response::HTTP_OK, 'Projet supprimé (logiquement) avec succès.');
    }
}
