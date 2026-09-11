<?php

namespace App\Controller\Core\Classe;

use App\Repository\Core\ClasseCourrierRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "ClasseCourrier")]
class GetCollectionController extends AbstractController
{
    public function __construct(
        private ClasseCourrierRepository $classeCourrierRepository,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger
    ) {}

    #[Route('/core/classe-courrier', name: 'app_core_classe_courrier_get_collection', methods: ['GET'])]
    #[OA\Get(
        path: '/core/classe-courrier',
        summary: 'Lister les classes de courrier',
        description: 'Retourne la liste paginée des classes de courrier avec possibilité de recherche',
        tags: ['ClasseCourrier'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Recherche par nom', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Numéro de page', schema: new OA\Schema(type: 'integer', default: 1)),
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
                        new OA\Property(property: 'total', type: 'integer', example: 5),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'nom', type: 'string', example: 'Urgent'),
                                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                                    new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time')
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé')
        ]
    )]
    public function list(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetClasseCourrierCollection');

        $search = $request->query->get('search');
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = (int)$request->query->get('limit', 10);

        $qb = $this->classeCourrierRepository->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC');

        if (!empty($search)) {
            $qb->andWhere('LOWER(c.nom) LIKE LOWER(:search)')
                ->setParameter('search', '%' . trim($search) . '%');
        }

        $totalQb = clone $qb;
        $total = (int)$totalQb->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();

        if ($limit > 0) {
            $qb->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit);
        }

        $data = $qb->getQuery()->getResult();

        return $this->json([
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'data' => $data
        ], Response::HTTP_OK, [], [
            'groups' => ['classe_courrier:read']
        ]);
    }
}
