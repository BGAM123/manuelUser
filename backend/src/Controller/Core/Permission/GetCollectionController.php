<?php

namespace App\Controller\Core\Permission;

use App\Repository\Core\PermissionRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Permission")]
class GetCollectionController extends AbstractController
{
    public function __construct(
        private PermissionRepository $permissionRepository,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/permission', name: 'app_core_permission_get_collection', methods: ['GET'])]
    #[OA\Get(
        path: '/core/permission',
        summary: 'Lister toutes les permissions',
        description: 'Retourne la liste de toutes les permissions disponibles.',
        tags: ['Permission'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 50)),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des permissions récupérée avec succès.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function list(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'GetPermissionCollection');

        $filters = [
            'search' => $request->query->get('search'),
            'page' => max(1, (int)$request->query->get('page', 1)),
            'limit' => (int)$request->query->get('limit', 50),
        ];

        $queryBuilder = $this->permissionRepository->createQueryBuilder('p');

        // ðŸ” Recherche
        if (!empty($filters['search'])) {
            $queryBuilder->andWhere('LOWER(p.nom) LIKE LOWER(:search) OR LOWER(p.description) LIKE LOWER(:search)')
                         ->setParameter('search', '%' . trim($filters['search']) . '%');
        }

        // ðŸ”„ Tri alphabÃ©tique
        $queryBuilder->orderBy('p.nom', 'ASC');

        // ðŸ“„ Pagination
        $limit = $filters['limit'];
        $page = $filters['page'];
        $total = (clone $queryBuilder)->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();

        if ($limit > 0) {
            $queryBuilder->setMaxResults($limit)->setFirstResult(($page - 1) * $limit);
        }

        $results = $queryBuilder->getQuery()->getResult();

        $data = array_map(fn($p) => [
            'id' => $p->getId(),
            'nom' => $p->getNom(),
            'description' => $p->getDescription(),
        ], $results);

        return $this->json([
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'data' => $data
        ], 200);
    }
}