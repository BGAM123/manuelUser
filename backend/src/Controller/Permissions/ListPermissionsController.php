<?php

namespace App\Controller\Permissions;

use App\Repository\PermissionRepository;
use App\Service\ApiResponseFactory;
use App\Service\PaginationFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/permissions')]
#[OA\Tag(name: 'Permissions')]
final class ListPermissionsController extends AbstractController
{
    #[Route('', name: 'app_permission_list', methods: ['GET'])]
    #[OA\Get(
        path: '/permissions',
        summary: 'Lister les permissions',
        description: "Retourne la liste paginée des permissions actives (non supprimées), avec un filtre de recherche simple sur le nom et la description."
    )]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10))]
    #[OA\Parameter(name: 'q', in: 'query', description: 'Recherche texte libre (nom ou description).', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'is_active', in: 'query', schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(
        name: 'is_delete',
        in: 'query',
        schema: new OA\Schema(type: 'string', enum: ['false', 'true', 'all'], default: 'false'),
        description: 'false (défaut) = permissions actives uniquement. true = permissions supprimées (corbeille) uniquement. all = toutes.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - liste paginée des permissions retournée',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Liste des permissions retournée avec succès.',
                'data' => [
                    ['id' => 1, 'nom' => 'gerer_biens', 'description' => 'Gérer les biens du patrimoine', 'is_active' => true]
                ],
                'pagination' => ['page' => 1, 'limit' => 10, 'total' => 4, 'pages' => 1]
            ]
        )
    )]
    public function __invoke(
        Request $request,
        PermissionRepository $permissionRepository,
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

        $permissions = $permissionRepository->findPaginatedPermissions($page, $limit, $q, $isActive, $isDelete);
        $total = $permissionRepository->countPermissions($q, $isActive, $isDelete);

        // Serialiser les permissions
        $serializedPermissions = json_decode($serializer->serialize($permissions, 'json', ['groups' => ['permission:list']]), true);

        // Construire la réponse paginée uniforme
        $paginatedData = $paginationFactory->createPaginatedResponse($serializedPermissions, $page, $limit, $total);

        return $apiResponse->success($paginatedData, Response::HTTP_OK, 'Liste des permissions retournée avec succès.');
    }
}
