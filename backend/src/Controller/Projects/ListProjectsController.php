<?php

namespace App\Controller\Projects;

use App\Repository\ProjectRepository;
use App\Service\ApiResponseFactory;
use App\Service\PaginationFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/projects')]
#[OA\Tag(name: 'Projects')]
final class ListProjectsController extends AbstractController
{
    #[Route('', name: 'app_project_list', methods: ['GET'])]
    #[OA\Get(
        path: '/projects',
        summary: 'Lister les projets',
        description: "Retourne la liste paginée des projets actifs (non supprimés), avec un filtre de recherche simple sur le nom, la description et le responsable."
    )]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10))]
    #[OA\Parameter(name: 'exercice', in: 'query', description: 'Filtrer par exercice (année).', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'q', in: 'query', description: 'Recherche texte libre (nom, description ou responsable).', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'statut', in: 'query', description: 'PLANIFIE, EN COURS ou TERMINE', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(
        name: 'is_delete',
        in: 'query',
        description: 'false (défaut) = projets actifs uniquement. true = projets supprimés (corbeille) uniquement. all = tous.',
        schema: new OA\Schema(type: 'string', enum: ['false', 'true', 'all'], default: 'false')
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - liste paginée des projets retournée',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Liste des projets retournée avec succès.',
                'data' => [
                    [
                        'id' => 1,
                        'nom' => 'Recensement du patrimoine 2026',
                        'date_debut' => '2026-01-15',
                        'date_fin_prevue' => '2026-06-30',
                        'exercice' => '2020',
                        'statut' => 'EN COURS',
                        // 'responsables' => [
                        //     [
                        //         'id' => 4,
                        //         'firstName' => 'Manon',
                        //         'lastName' => 'Tanguy',
                        //     ]
                        // ]
                    ]
                ],
                'pagination' => ['page' => 1, 'limit' => 10, 'total' => 5, 'pages' => 1]
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
        SerializerInterface $serializer,
        PaginationFactory $paginationFactory,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 10);
        $limit = $limit < 1 ? 10 : ($limit > 200 ? 200 : $limit);
        $exercice = $request->query->get('exercice'); // Nouveau paramètre

        // Valider l'exercice si fourni
        if ($exercice !== null && !preg_match('/^\d{4}$/', $exercice)) {
            return $apiResponse->error(
                'Le paramètre exercice doit être une année valide (4 chiffres).',
                Response::HTTP_BAD_REQUEST
            );
        }

        $q = $request->query->get('q');
        $statut = $request->query->get('statut');
        $isDelete = $request->query->get('is_delete', 'false');

        $projects = $projectRepository->findPaginatedProjects($page, $limit, $q, $statut, $exercice, $isDelete);
        $total = $projectRepository->countProjects($q, $statut, $exercice, $isDelete);

        // Serialiser les projets
        $serializedProjects = json_decode($serializer->serialize($projects, 'json', ['groups' => ['project:list']]), true);

        // Construire la réponse paginée uniforme
        $paginatedData = $paginationFactory->createPaginatedResponse($serializedProjects, $page, $limit, $total);

        return $apiResponse->success($paginatedData, Response::HTTP_OK, 'Liste des projets retournée avec succès.');
    }
}
