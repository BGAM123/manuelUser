<?php

namespace App\Controller\Core\Role;

use App\Repository\Core\RoleRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Role")]
class GetCollectionController extends AbstractController
{
    public function __construct(
        private RoleRepository $roleRepository,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/role', name: 'app_core_role_get_collection', methods: ['GET'])]
    #[OA\Get(
        path: '/core/role',
        summary: 'Lister les rôles avec leurs permissions',
        description: 'Retourne la liste des rôles avec filtres et pagination, incluant leurs permissions.',
        tags: ['Role'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'is_delete', in: 'query', required: false, schema: new OA\Schema(type: 'boolean', default: false)),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des rôles récupérée avec succès.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function list(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'GetRoleCollection');

        $filters = [
            'is_delete' => filter_var($request->query->get('is_delete', false), FILTER_VALIDATE_BOOLEAN),
            'search' => $request->query->get('search'),
            'page' => max(1, (int)$request->query->get('page', 1)),
            'limit' => (int)$request->query->get('limit', 10),
        ];

        $baseQueryBuilder = $this->roleRepository->createQueryBuilder('r')
            ->where('r.isDelete = :isDelete')
            ->setParameter('isDelete', $filters['is_delete']);

        // ðŸ” Recherche
        if (!empty($filters['search'])) {
            $baseQueryBuilder->andWhere('LOWER(r.search) LIKE LOWER(:search)')
                         ->setParameter('search', '%' . trim($filters['search']) . '%');
        }

        // ðŸ”„ Tri
        $baseQueryBuilder->orderBy('r.createdAt', 'DESC');

        // ðŸ“„ Pagination
        $limit = $filters['limit'];
        $page = $filters['page'];
        $total = (clone $baseQueryBuilder)->select('COUNT(DISTINCT r.id)')->getQuery()->getSingleScalarResult();

        $roleIds = [];
        if ($limit > 0) {
            $idRows = (clone $baseQueryBuilder)
                ->select('r.id')
                ->setMaxResults($limit)
                ->setFirstResult(($page - 1) * $limit)
                ->getQuery()
                ->getScalarResult();
            $roleIds = array_values(array_unique(array_map(static fn ($row) => (int) $row['id'], $idRows)));
        } else {
            $idRows = (clone $baseQueryBuilder)
                ->select('r.id')
                ->getQuery()
                ->getScalarResult();
            $roleIds = array_values(array_unique(array_map(static fn ($row) => (int) $row['id'], $idRows)));
        }

        $results = [];
        if (!empty($roleIds)) {
            $queryBuilder = $this->roleRepository->createQueryBuilder('r')
                ->leftJoin('r.rolePermissions', 'rp')
                ->leftJoin('rp.idPermission', 'p')
                ->addSelect('rp', 'p')
                ->where('r.isDelete = :isDelete')
                ->andWhere('r.id IN (:roleIds)')
                ->setParameter('isDelete', $filters['is_delete'])
                ->setParameter('roleIds', $roleIds)
                ->orderBy('r.createdAt', 'DESC');

            $results = $queryBuilder->getQuery()->getResult();
        }

        $data = array_map(function($role) {
            $permissions = array_map(fn($rp) => [
                'id' => $rp->getIdPermission()->getId(),
                'nom' => $rp->getIdPermission()->getNom(),
                'description' => $rp->getIdPermission()->getDescription(),
            ], $role->getRolePermissions()->toArray());

            return [
                'id' => $role->getId(),
                'nom' => $role->getNom(),
                'description' => $role->getDescription(),
                'permissions' => $permissions,
                'createdAt' => $role->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $results);

        return $this->json([
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'data' => $data
        ], 200);
    }
}
