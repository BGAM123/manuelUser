<?php

namespace App\Controller\Projects;

use App\Repository\ProjectRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/projects')]
#[OA\Tag(name: 'Projects')]
final class ProjectDetailController extends AbstractController
{
    #[Route('/{id}', name: 'app_project_detail', methods: ['GET'])]
    #[OA\Get(
        path: '/projects/{id}',
        summary: 'Détails d\'un projet',
        description: 'Retourne le détail d\'un projet actif avec la liste des responsables affectés.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Success - détails du projet retournés',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Détails du projet retournés avec succès.',
                'data' => [
                    'id' => 1,
                    'nom' => 'Recensement du patrimoine 2026',
                    'date_debut' => '2026-09-01',
                    'date_fin_prevue' => null,
                    'exercice' => '2020',
                    'createdAt' => '27-07-2026 12:43:27',
                    'updatedAt' => '27-07-2026 13:08:17',
                    'statut' => 'PLANIFIE',
                    // 'responsables' => [
                    //     [
                    //         'id' => 4,
                    //         'firstName' => 'Manon',
                    //         'lastName' => 'Tanguy',
                    //         'email' => 'zparent@example.net'
                    //     ]
                    // ]
                ]
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Not Found',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'La ressource demandée est introuvable.', 'data' => null])
    )]
    public function __invoke(
        int $id,
        ProjectRepository $projectRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $project = $projectRepository->getProjectById($id);
        if (!$project || $project->isDelete()) {
            return $apiResponse->error('Projet non trouvé.', Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($serializer->serialize($project, 'json', ['groups' => ['project:detail']]), true);
        return $apiResponse->success($data, Response::HTTP_OK, 'Détails du projet retournés avec succès.');
    }
}
