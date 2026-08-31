<?php

namespace App\Controller\Projects;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/projects')]
#[OA\Tag(name: 'Projects')]
final class UpdateProjectController extends AbstractController
{
    #[Route('/{id}', name: 'app_project_update', methods: ['PUT'])]
    #[OA\Put(summary: 'Mettre à jour un projet', description: "L'exercice, fixé à la création, n'est jamais modifiable via cet endpoint (ignoré s'il est envoyé).")]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'nom', type: 'string', example: 'Recensement du patrimoine 2026'),
                new OA\Property(property: 'description', type: 'string', example: 'Campagne annuelle de recensement du patrimoine du MINEPIA'),
                new OA\Property(property: 'date_debut', type: 'string', format: 'date', example: '2026-01-15'),
                new OA\Property(property: 'exercice', type: 'string', format: 'date', example: '2026'),
                new OA\Property(property: 'date_fin_prevue', type: 'string', format: 'date', example: '2026-06-30'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Projet mis à jour avec succès',
        content: new OA\JsonContent(
            example: ['success' => true, 'status' => 200, 'message' => 'Projet mis à jour avec succès.', 'data' => ['id' => 6, 'statut' => 'EN COURS']]
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
        Project $project,
        Request $request,
        ProjectRepository $projectRepository,
        UserRepository $userRepository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $apiResponse->error('Charge JSON invalide.', Response::HTTP_BAD_REQUEST);
        }

        // Interdire la modification du statut dans cet endpoint
        if (array_key_exists('statut', $payload)) {
            return $apiResponse->error(
                'Le statut ne peut pas être modifié via cet endpoint. Utilisez PATCH /projects/{id}/status à la place.',
                Response::HTTP_BAD_REQUEST
            );
        }

        $projectRepository->applyPayloadToProject($project, $payload);

        $errors = $validator->validate($project);
        if (count($errors) > 0) {
            $errorsData = json_decode($serializer->serialize($errors, 'json'), true);
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $errorsData);
        }

        // L'exercice est fixé à la création et n'est pas modifiable
        // La documentation OpenAPI indique que ce champ est ignoré s'il est envoyé

        $projectRepository->save($project);

        $data = json_decode($serializer->serialize($project, 'json', ['groups' => ['project:detail']]), true);
        return $apiResponse->success($data, Response::HTTP_OK, 'Projet mis à jour avec succès.');
    }
}
