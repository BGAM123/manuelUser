<?php

namespace App\Controller\Core\PieceJointe;

use App\Repository\Cour\PieceJointeRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "PieceJointe")]
class GetCollectionController extends AbstractController
{
    public function __construct(
        private PieceJointeRepository $pieceJointeRepository,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/piece-jointe', name: 'app_core_piece_jointe_get_collection', methods: ['GET'])]
    #[OA\Get(
        path: '/core/piece-jointe',
        summary: 'Lister toutes les pièces jointes',
        description: 'Retourne la liste de toutes les pièces jointes avec filtres et pagination.',
        tags: ['PieceJointe'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'type_parent', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'Courrier')),
            new OA\Parameter(name: 'id_parent', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 5)),
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'application/pdf')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'is_delete', in: 'query', required: false, schema: new OA\Schema(type: 'boolean', default: false)),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des pièces jointes récupérées avec succès.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function list(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetPieceJointeCollection');

        $filters = [
            'type_parent' => $request->query->get('type_parent'),
            'id_parent' => $request->query->get('id_parent'),
            'type' => $request->query->get('type'),
            'is_delete' => filter_var($request->query->get('is_delete', false), FILTER_VALIDATE_BOOLEAN),
            'search' => $request->query->get('search'),
            'page' => max(1, (int)$request->query->get('page', 1)),
            'limit' => (int)$request->query->get('limit', 10),
        ];

        $queryBuilder = $this->pieceJointeRepository->createQueryBuilder('pj')
            ->where('pj.isDelete = :isDelete')
            ->setParameter('isDelete', $filters['is_delete']);

        // ðŸ” Recherche
        if (!empty($filters['search'])) {
            $queryBuilder->andWhere('LOWER(pj.search) LIKE LOWER(:search)')
                         ->setParameter('search', '%' . trim($filters['search']) . '%');
        }

        // ðŸ§© Filtres
        if (!empty($filters['type_parent'])) {
            $queryBuilder->andWhere('pj.typeParent = :typeParent')
                         ->setParameter('typeParent', $filters['type_parent']);
        }
        if (!empty($filters['id_parent'])) {
            $queryBuilder->andWhere('pj.idParent = :idParent')
                         ->setParameter('idParent', $filters['id_parent']);
        }
        if (!empty($filters['type'])) {
            $queryBuilder->andWhere('pj.type = :type')
                         ->setParameter('type', $filters['type']);
        }

        // ðŸ”„ Tri
        $queryBuilder->orderBy('pj.createdAt', 'DESC');

        // ðŸ“„ Pagination
        $limit = $filters['limit'];
        $page = $filters['page'];
        $total = (clone $queryBuilder)->select('COUNT(pj.id)')->getQuery()->getSingleScalarResult();

        if ($limit > 0) {
            $queryBuilder->setMaxResults($limit)->setFirstResult(($page - 1) * $limit);
        }

        $results = $queryBuilder->getQuery()->getResult();

        $data = array_map(fn($pj) => [
            'id' => $pj->getId(),
            'nom' => $pj->getNom(),
            'chemin' => $pj->getChemin(),
            'type' => $pj->getType(),
            'idParent' => $pj->getIdParent(),
            'typeParent' => $pj->getTypeParent(),
            'createdAt' => $pj->getCreatedAt()?->format('Y-m-d H:i:s'),
        ], $results);

        return $this->json([
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'data' => $data
        ], 200);
    }
}