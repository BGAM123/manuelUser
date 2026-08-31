<?php

namespace App\Controller\Roles;

use App\Repository\RoleRepository;
use App\Service\ApiResponseFactory;
use App\Service\PaginationFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/roles')]
#[OA\Tag(name: 'Roles')]
final class ListRolesController extends AbstractController
{
    #[Route('', name: 'app_role_list', methods: ['GET'])]
    #[OA\Get(
        path: '/roles',
        summary: 'Lister les rôles',
        description: "Retourne la liste paginée des rôles actifs (non supprimés), avec un filtre de recherche simple sur le nom et la description."
    )]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10))]
    #[OA\Parameter(name: 'q', in: 'query', description: 'Recherche texte libre (nom ou description).', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'is_active', in: 'query', schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(
        name: 'is_delete',
        in: 'query',
        schema: new OA\Schema(type: 'string', enum: ['false', 'true', 'all'], default: 'false'),
        description: 'false (défaut) = rôles actifs uniquement. true = rôles supprimés (corbeille) uniquement. all = tous.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - liste paginée des rôles retournée',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Liste des rôles retournée avec succès.',
                'data' => [
                    [
                        'id' => 1,
                        'nom' => 'Administrateur',
                        'description' => 'Accès complet à la plateforme',
                        'is_active' => true,
                        'permissions' => [
                            ['id' => 1, 'nom' => 'Créer un utilisateur'],
                            ['id' => 2, 'nom' => 'Modifier un utilisateur']
                        ]
                    ]
                ],
                'pagination' => ['page' => 1, 'limit' => 10, 'total' => 6, 'pages' => 1]
            ]
        )
    )]
    public function __invoke(
        Request $request,
        RoleRepository $roleRepository,
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

        $roles = $roleRepository->findPaginatedRoles($page, $limit, $q, $isActive, $isDelete);
        $total = $roleRepository->countRoles($q, $isActive, $isDelete);

        // Normaliser les rôles avec le contexte _role_list pour inclure les permissions
        $serializedRoles = [];
        foreach ($roles as $role) {
            $serializedRoles[] = $serializer->normalize($role, null, ['_role_list' => true]);
        }

        // Construire la réponse paginée uniforme
        $paginatedData = $paginationFactory->createPaginatedResponse($serializedRoles, $page, $limit, $total);

        return $apiResponse->success($paginatedData, Response::HTTP_OK, 'Liste des rôles retournée avec succès.');
    }
}
