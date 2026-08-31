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
final class RestoreProjectController extends AbstractController
{
    #[Route('/{id}/restore', name: 'app_project_restore', methods: ['PUT'])]
    #[OA\Put(
        path: '/projects/{id}/restore',
        summary: 'Restaurer un projet supprimé',
        description: "Passe is_delete à false : le projet redevient visible dans les listes actives et peut être utilisé à nouveau."
    )]
    #[OA\Parameter(
        name: 'id', 
        in: 'path', 
        required: true, 
        schema: new OA\Schema(type: 'integer'),
        description: 'ID du projet à restaurer',
        example: 1
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Projet restauré avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true, 
                'status' => 200, 
                'message' => 'Projet restauré avec succès.', 
                'data' => null
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Not Found - Projet introuvable',
        content: new OA\JsonContent(
            example: [
                'success' => false, 
                'status' => 404, 
                'message' => 'La ressource demandée est introuvable.', 
                'data' => null
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request - Projet déjà actif',
        content: new OA\JsonContent(
            example: [
                'success' => false, 
                'status' => 400, 
                'message' => 'Ce projet est déjà actif.', 
                'data' => null
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Non authentifié',
        content: new OA\JsonContent(
            example: [
                'success' => false, 
                'status' => 401, 
                'message' => 'Authentification requise.', 
                'data' => null
            ]
        )
    )]
    #[OA\Response(
        response: 500,
        description: 'Erreur serveur',
        content: new OA\JsonContent(
            example: [
                'success' => false, 
                'status' => 500, 
                'message' => 'Une erreur interne du serveur est survenue.', 
                'data' => null
            ]
        )
    )]
    public function __invoke(
    Project $project,
    ProjectRepository $projectRepository,
    ApiResponseFactory $apiResponse
): JsonResponse {
    // ✅ Vérifier que le projet est bien supprimé
    if (!$project->isDelete()) {
        return $apiResponse->error('Ce projet est déjà actif.', Response::HTTP_BAD_REQUEST);
    }

    // ✅ Restaurer le projet
    $projectRepository->restore($project);

    return $apiResponse->success(
        null, 
        Response::HTTP_OK, 
        'Projet restauré avec succès.'
    );
}
}