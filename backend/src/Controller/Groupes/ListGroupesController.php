<?php

namespace App\Controller\Groupes;

use App\Repository\GroupeRepository;
use App\Service\ApiResponseFactory;
use App\Service\PaginationFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/groupes')]
#[OA\Tag(name: 'Groupes')]
final class ListGroupesController extends AbstractController
{
    #[Route('', name: 'app_groupe_list', methods: ['GET'])]
    #[OA\Get(
        path: '/groupes',
        summary: 'Lister les groupes',
        description: "Retourne la liste paginée des groupes actifs (non supprimés), avec un filtre de recherche simple sur le nom et la description."
    )]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10))]
    #[OA\Parameter(name: 'q', in: 'query', description: 'Recherche texte libre (nom ou description).', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'is_active', in: 'query', schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(
        name: 'is_delete',
        in: 'query',
        schema: new OA\Schema(type: 'string', enum: ['false', 'true', 'all'], default: 'false'),
        description: 'false (défaut) = groupes actifs uniquement. true = groupes supprimés (corbeille) uniquement. all = tous.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - liste paginée des groupes retournée',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Liste des groupes retournée avec succès.',
                'data' => [
                    [
                        'id' => 1,
                        'nom' => 'SECRETAIRE',
                        'description' => 'Socle commun de permissions',
                        'is_active' => true
                    ]
                ],
                'pagination' => ['page' => 1, 'limit' => 10, 'total' => 3, 'pages' => 1]
            ]
        )
    )]
    public function __invoke(
        Request $request,
        GroupeRepository $groupeRepository,
        SerializerInterface $serializer,
        PaginationFactory $paginationFactory,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 10);
        $limit = $limit < 1 ? 10 : ($limit > 200 ? 200 : $limit);

        $q = $request->query->get('q');
        $isActiveParam = $request->query->get('is_active');
        $isActive = null !== $isActiveParam
            ? filter_var($isActiveParam, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : null;
        $isDelete = $request->query->get('is_delete', 'false');

        $groupes = $groupeRepository->findPaginatedGroupes($page, $limit, $q, $isActive, $isDelete);
        $total = $groupeRepository->countGroupes($q, $isActive, $isDelete);

        $serialized = json_decode($serializer->serialize($groupes, 'json', ['groups' => ['groupe:list']]), true);
        $paginatedData = $paginationFactory->createPaginatedResponse($serialized, $page, $limit, $total);

        return $apiResponse->success($paginatedData, Response::HTTP_OK, 'Liste des groupes retournée avec succès.');
    }
}