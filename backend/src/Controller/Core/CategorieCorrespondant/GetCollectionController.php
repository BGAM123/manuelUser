<?php

namespace App\Controller\Core\CategorieCorrespondant;

use App\Repository\Core\CategorieCorrespondantRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "CategorieCorrespondant")]
class GetCollectionController extends AbstractController
{
    public function __construct(
        private CategorieCorrespondantRepository $categorieRepository,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger
    ) {}

    #[Route('/core/categorie-correspondant', name: 'app_core_categorie_correspondant_get_collection', methods: ['GET'])]
    #[OA\Get(
        path: '/core/categorie-correspondant',
        summary: 'Lister les catégories de correspondants',
        description: 'Retourne la liste paginée des catégories avec possibilité de recherche et filtrage sur la suppression logique.',
        tags: ['CategorieCorrespondant'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Filtre de recherche (nom)', schema: new OA\Schema(type: 'string')),
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
                                    new OA\Property(property: 'nom', type: 'string', example: 'MinistÃ¨re'),
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
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetCategorieCorrespondantCollection');

        $search = $request->query->get('search');
        $isDelete = filter_var($request->query->get('is_delete', false), FILTER_VALIDATE_BOOLEAN);
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = (int)$request->query->get('limit', 10);

        $qb = $this->categorieRepository->createQueryBuilder('c')
            ->where('c.isDelete = :isDelete')
            ->setParameter('isDelete', $isDelete)
            ->orderBy('c.createdAt', 'DESC');

        if (!empty($search)) {
            $qb->andWhere('LOWER(c.nom) LIKE LOWER(:search)')
               ->setParameter('search', '%' . trim($search) . '%');
        }

        $total = (clone $qb)->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();

        if ($limit > 0) {
            $qb->setMaxResults($limit)->setFirstResult(($page - 1) * $limit);
        }

        $results = $qb->getQuery()->getResult();

        $data = array_map(fn($c) => [
            'id' => $c->getId(),
            'nom' => $c->getNom(),
            'isDelete' => $c->isDelete(),
            'createdAt' => $c->getCreatedAt()?->format('Y-m-d H:i:s'),
        ], $results);

        // Logger la consultation de la liste
        $this->actionLogger->logView(
            'CategorieCorrespondant',
            null,
            'Consultation de la liste des catÃ©gories de correspondants',
            [
                'total' => (int) $total,
                'page' => $page,
                'limit' => $limit,
                'search' => $search ?: null,
                'is_delete' => $isDelete,
                'categories' => array_slice($data, 0, 100) // Limiter Ã  100
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
