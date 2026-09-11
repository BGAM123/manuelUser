<?php

namespace App\Controller\Core\TypeCourrier;

use App\Repository\Core\TypeCourrierRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "TypeCourrier")]
class GetCollectionController extends AbstractController
{
    public function __construct(
        private TypeCourrierRepository $typeCourrierRepository,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger
    ) {}

    #[Route('/core/type-courrier', name: 'app_core_type_courrier_get_collection', methods: ['GET'])]
    #[OA\Get(
        path: '/core/type-courrier',
        summary: 'Lister les types de courrier',
        description: 'Retourne la liste paginée des types de courrier, avec possibilité de recherche globale, filtrage par type, catégorie, classe et suppression logique.',
        tags: ['TypeCourrier'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Filtre global (nom, type, classe, catégorie)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'type', in: 'query', required: false, description: 'Filtrer par type (ex: Entrant / Sortant)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'classe_courrier', in: 'query', required: false, description: 'Filtrer par classe de courrier (ex: Urgent, Normal)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'categorie', in: 'query', required: false, description: 'Filtrer par nom de catégorie associée', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'parent', in: 'query', required: false, description: 'Filtrer par ID du type parent (parent=null pour racines)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'is_delete', in: 'query', required: false, description: 'Inclure les éléments supprimés (true/false)', schema: new OA\Schema(type: 'boolean', default: false)),
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Numéro de page pour la pagination', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Nombre d\'éléments par page (0 pour tout)', schema: new OA\Schema(type: 'integer', default: 10))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste récupérée avec succès',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'page', type: 'integer', example: 1),
                        new OA\Property(property: 'limit', type: 'integer', example: 10),
                        new OA\Property(property: 'total', type: 'integer', example: 25),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'nom', type: 'string', example: 'Courrier administratif'),
                                    new OA\Property(property: 'type', type: 'string', example: 'Entrant'),
                                    new OA\Property(property: 'classeCourrier', type: 'string', example: 'Urgent'),
                                    new OA\Property(property: 'categorie', type: 'string', example: 'Administration'),
                                    new OA\Property(property: 'isDelete', type: 'boolean', example: false),
                                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-02-14T09:30:00+00:00'),
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function list(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetTypeCourrierCollection');

        $search = $request->query->get('search');
        $type = $request->query->get('type');
        $classeCourrier = $request->query->get('classe_courrier');
        $categorie = $request->query->get('categorie');
        $parent = $request->query->get('parent');
        $isDelete = filter_var($request->query->get('is_delete', false), FILTER_VALIDATE_BOOLEAN);
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = (int)$request->query->get('limit', 10);

        $qb = $this->typeCourrierRepository->createQueryBuilder('t')
            ->leftJoin('t.categories', 'c')
            ->leftJoin('t.idTypeParent', 'p')
            ->addSelect('c', 'p')
            ->where('t.isDelete = :isDelete')
            ->setParameter('isDelete', $isDelete)
            ->orderBy('t.createdAt', 'DESC');

        if (!empty($search)) {
            $qb->andWhere('LOWER(t.nom) LIKE LOWER(:search) 
                        OR LOWER(t.type) LIKE LOWER(:search)
                        OR LOWER(t.classeCourrier) LIKE LOWER(:search)
                        OR LOWER(c.nom) LIKE LOWER(:search)')
                ->setParameter('search', '%' . trim($search) . '%');
        }

        if (!empty($type)) {
            $qb->andWhere('LOWER(t.type) = LOWER(:type)')
                ->setParameter('type', trim($type));
        }

        if (!empty($classeCourrier)) {
            $qb->andWhere('LOWER(t.classeCourrier) LIKE LOWER(:classeCourrier)')
                ->setParameter('classeCourrier', '%' . trim($classeCourrier) . '%');
        }

        if (!empty($categorie)) {
            $qb->andWhere('LOWER(c.nom) LIKE LOWER(:categorie)')
                ->setParameter('categorie', '%' . trim($categorie) . '%');
        }

        if ($parent !== null) {
            if ($parent === 'null') {
                $qb->andWhere('t.idTypeParent IS NULL');
            } else {
                $qb->andWhere('p.id = :parentId')
                    ->setParameter('parentId', (int) $parent);
            }
        }

        $total = (clone $qb)->select('COUNT(t.id)')->getQuery()->getSingleScalarResult();

        if ($limit > 0) {
            $qb->setMaxResults($limit)->setFirstResult(($page - 1) * $limit);
        }

        $results = $qb->getQuery()->getResult();

        $data = array_map(fn($t) => [
            'id' => $t->getId(),
            'nom' => $t->getNom(),
            'type' => $t->getType(),
            'classeCourrier' => $t->getClasseCourrier(),
            'categories' => $t->getCategories()->map(fn($cat) => [
                'id' => $cat->getId(),
                'nom' => $cat->getNom(),
            ])->toArray(),
            'parent' => $t->getIdTypeParent()?->getNom(),
            'parentId' => $t->getIdTypeParent()?->getId(),
            'isDelete' => $t->isDelete(),
            'createdAt' => $t->getCreatedAt()?->format('Y-m-d H:i:s'),
        ], $results);

        // Log de la consultation de la collection
        $this->actionLogger->logView(
            'TypeCourrier',
            null,
            'Consultation de la liste des types de courrier',
            [
                'total' => (int) $total,
                'page' => $page,
                'limit' => $limit,
                'filters' => [
                    'search' => $search,
                    'type' => $type,
                    'classe_courrier' => $classeCourrier,
                    'categorie' => $categorie,
                    'parent' => $parent,
                    'is_delete' => $isDelete,
                ],
                'types_courrier' => array_slice($data, 0, 100) // Limiter Ã  100 pour Ã©viter surcharge
            ]
        );

        return $this->json([
            'page' => $page,
            'limit' => $limit,
            'total' => (int) $total,
            'data' => $data
        ], 200);
    }
}
