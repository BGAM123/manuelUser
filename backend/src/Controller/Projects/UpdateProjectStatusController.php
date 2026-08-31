<?php

/**
 * UpdateProjectStatusController - Met à jour le statut d'un projet via PATCH.
 *
 * Endpoint : PATCH /projects/{id}/status
 * Body: { "statut": "EN COURS" }
 *
 * Valeurs valides : PLANIFIE, EN COURS, TERMINE
 */

namespace App\Controller\Projects;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Gère la modification du statut d'un projet.
 * Cet endpoint dédié permet de changer le statut sans risquer de
 * modifier accidentellement les autres champs du projet.
 */
#[Route('/projects')]
#[OA\Tag(name: 'Projects')]
final class UpdateProjectStatusController extends AbstractController
{
    #[Route('/{id}/status', name: 'app_project_update_status', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/projects/{id}/status',
        summary: 'Mettre à jour le statut d\'un projet',
        description: 'Modifie uniquement le statut du projet. Les autres champs ne sont pas affectés.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            required: ['statut'],
            properties: [
                new OA\Property(
                    property: 'statut',
                    type: 'string',
                    example: 'EN COURS',
                    description: 'Nouveau statut du projet. Valeurs autorisées : PLANIFIE, EN COURS, TERMINE'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Statut du projet mis à jour avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Statut du projet mis à jour avec succès.',
                'data' => [
                    'id' => 1,
                    'nom' => 'Recensement du patrimoine 2026',
                    'statut' => 'EN COURS'
                ]
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
    public function __invoke(
        Project $project,
        Request $request,
        ProjectRepository $projectRepository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $apiResponse->error('Charge JSON invalide.', Response::HTTP_BAD_REQUEST);
        }

        if (!isset($payload['statut']) || empty($payload['statut'])) {
            return $apiResponse->error('Le champ statut est obligatoire.', Response::HTTP_BAD_REQUEST);
        }

        // Mettre à jour le statut (accepte n'importe quelle valeur)
        $project->setStatut((string) $payload['statut']);
        $project->setUpdatedAt(new \DateTimeImmutable());

        // Valider le projet
        $errors = $validator->validate($project);
        if (count($errors) > 0) {
            $errorsData = json_decode($serializer->serialize($errors, 'json'), true);
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $errorsData);
        }

        // Persister
        $projectRepository->save($project);

        $data = json_decode($serializer->serialize($project, 'json', ['groups' => ['project:detail']]), true);
        return $apiResponse->success($data, Response::HTTP_OK, 'Statut du projet mis à jour avec succès.');
    }
}
