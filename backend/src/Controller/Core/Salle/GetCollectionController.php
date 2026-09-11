<?php

namespace App\Controller\Core\Salle;

use App\Repository\Core\SalleRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Salle")]
class GetCollectionController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker,
        private SalleRepository $salleRepository,
    ) {}

    #[Route('/core/salle', name: 'app_core_salle_get_collection', methods: ['GET'])]
    #[OA\Get(
        path: '/core/salle',
        summary: 'Lister les salles',
        tags: ['Salle'],
        description: "Récupère la liste de toutes les salles avec pagination et filtres.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'page',
                in: 'query',
                required: false,
                description: 'Numéro de la page',
                schema: new OA\Schema(type: 'integer', default: 1)
            ),
            new OA\Parameter(
                name: 'limit',
                in: 'query',
                required: false,
                description: 'Nombre d\'éléments par page (max 100)',
                schema: new OA\Schema(type: 'integer', default: 10)
            ),
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Recherche par nom de salle',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'is_active',
                in: 'query',
                required: false,
                description: 'Filtrer par statut actif',
                schema: new OA\Schema(type: 'boolean')
            ),
            new OA\Parameter(
                name: 'is_delete',
                in: 'query',
                required: false,
                description: 'Inclure les salles supprimées',
                schema: new OA\Schema(type: 'boolean', default: false)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des salles récupérée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'page', type: 'integer', example: 1),
                        new OA\Property(property: 'limit', type: 'integer', example: 10),
                        new OA\Property(property: 'total', type: 'integer', example: 25),
                        new OA\Property(property: 'totalPages', type: 'integer', example: 3),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'nom', type: 'string', example: 'Salle de réunion A'),
                                    new OA\Property(property: 'isActive', type: 'boolean', example: true),
                                    new OA\Property(property: 'isDelete', type: 'boolean', example: false),
                                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-12-02T10:30:00+00:00'),
                                    new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2025-12-02T10:30:00+00:00'),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function collection(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetCollectionSalle');

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(100, (int) $request->query->get('limit', 10)));
        $search = $request->query->get('search');
        $isActive = $request->query->get('is_active');
        $isDelete = $request->query->getBoolean('is_delete', false);

        $queryBuilder = $this->salleRepository->createQueryBuilder('s');

        // Filtre de suppression
        if (!$isDelete) {
            $queryBuilder->andWhere('s.isDelete = :isDelete')
                ->setParameter('isDelete', false);
        }

        // Filtre actif
        if ($isActive !== null) {
            $queryBuilder->andWhere('s.isActive = :isActive')
                ->setParameter('isActive', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
        }

        // Recherche par nom
        if ($search) {
            $queryBuilder->andWhere('s.nom LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        // Comptage total
        $total = (int) (clone $queryBuilder)
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Pagination
        $queryBuilder->orderBy('s.nom', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $salles = $queryBuilder->getQuery()->getResult();

        $totalPages = ceil($total / $limit);

        $formattedData = array_map(function ($salle) {
            return [
                'id' => $salle->getId(),
                'nom' => $salle->getNom(),
                'isActive' => $salle->isActive(),
                'isDelete' => $salle->isDelete(),
                'createdAt' => $salle->getCreatedAt()?->format('c'),
                'updatedAt' => $salle->getUpdatedAt()?->format('c'),
            ];
        }, $salles);

        return $this->json([
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => $totalPages,
            'data' => $formattedData,
        ], 200);
    }
}
