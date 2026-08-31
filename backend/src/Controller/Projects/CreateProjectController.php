<?php

namespace App\Controller\Projects;

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
final class CreateProjectController extends AbstractController
{
    #[Route('', name: 'app_project_create', methods: ['POST'])]
    #[OA\Post(path: '/projects', summary: 'Créer un projet')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            required: ['nom', 'date_debut'],
            properties: [
                new OA\Property(property: 'nom', type: 'string', example: 'Recensement du patrimoine 2026'),
                new OA\Property(property: 'description', type: 'string', example: 'Campagne annuelle de recensement du patrimoine du MINEPIA'),
                new OA\Property(property: 'date_debut', type: 'string', format: 'date', example: '2026-01-15'),
                new OA\Property(property: 'date_fin_prevue', type: 'string', format: 'date', example: '2026-06-30'),
                new OA\Property(property: 'statut', type: 'string', example: 'PLANIFIE', description: 'PLANIFIE, EN COURS ou TERMINE'),
                new OA\Property(property: 'exercice', type: 'integer', nullable: true, example: 2026, description: "Exercice financier/patrimonial (année) de la source de financement représentée par ce projet. Optionnel : année courante par défaut si omis. Fixé définitivement à la création, non modifiable ensuite."),
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Created - Projet créé avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 201,
                'message' => 'Projet créé avec succès.',
                'data' => ['id' => 6, 'nom' => 'Recensement du patrimoine 2026', 'date_debut' => '2026-01-15',
                'date_fin_prevue' => '2026-06-30', 'statut' => 'PLANIFIE', 'exercice' => 2026, 'responsable_ids' => [3]]
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => null])
    )]
    public function __invoke(
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

        $nom = $payload['nom'] ?? null;
        if ($nom) {
            $existingProject = $projectRepository->findOneBy(['nom' => $nom]);
            if ($existingProject) {
                return $apiResponse->error('Un projet avec ce nom existe déjà.', Response::HTTP_BAD_REQUEST);
            }
        }

        $project = $projectRepository->buildProjectFromPayload($payload);

        $errors = $validator->validate($project);
        if (count($errors) > 0) {
            $errorsData = json_decode($serializer->serialize($errors, 'json'), true);
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $errorsData);
        }

        $projectRepository->save($project);

        $data = json_decode($serializer->serialize($project, 'json', ['groups' => ['project:detail']]), true);
        return $apiResponse->success($data, Response::HTTP_CREATED, 'Projet créé avec succès.');
    }
}
