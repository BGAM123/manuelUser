<?php

namespace App\Controller\Core\TypeTransmission;

use App\Repository\Core\TypeTransmissionRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'TypeTransmission')]
class GetCollectionController extends AbstractController
{
    public function __construct(
        private TypeTransmissionRepository $typeTransmissionRepository,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger
    ) {}

    #[Route('/core/type-transmission', name: 'app_core_type_transmission_get_collection', methods: ['GET'])]
    #[OA\Get(
        path: '/core/type-transmission',
        summary: 'Lister les types de transmission',
        description: 'Retourne la liste paginee des types de transmission, avec possibilite de recherche globale et suppression logique.',
        tags: ['TypeTransmission'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Filtre global (nom)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'is_delete', in: 'query', required: false, description: 'Inclure les elements supprimes (true/false)', schema: new OA\Schema(type: 'boolean', default: false)),
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Numero de page', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Nombre d\'elements par page (0 pour tout)', schema: new OA\Schema(type: 'integer', default: 10)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste recuperee avec succes',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'page', type: 'integer', example: 1),
                        new OA\Property(property: 'limit', type: 'integer', example: 10),
                        new OA\Property(property: 'total', type: 'integer', example: 1),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'nom', type: 'string', example: 'Transmission physique'),
                                    new OA\Property(property: 'isDelete', type: 'boolean', example: false),
                                    new OA\Property(property: 'createdAt', type: 'string', example: '2026-04-20 12:00:00'),
                                    new OA\Property(property: 'updatedAt', type: 'string', example: '2026-04-20 12:00:00'),
                                ]
                            )
                        ),
                    ],
                    example: [
                        'page' => 1,
                        'limit' => 10,
                        'total' => 1,
                        'data' => [
                            [
                                'id' => 1,
                                'nom' => 'Transmission physique',
                                'isDelete' => false,
                                'createdAt' => '2026-04-20 12:00:00',
                                'updatedAt' => '2026-04-20 12:00:00',
                            ],
                        ],
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Acces non autorise.'),
        ]
    )]
    public function list(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetTypeTransmissionCollection');

        $search = $request->query->get('search');
        $isDelete = filter_var($request->query->get('is_delete', false), FILTER_VALIDATE_BOOLEAN);
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = (int) $request->query->get('limit', 10);

        $qb = $this->typeTransmissionRepository->createQueryBuilder('t')
            ->where('t.isDelete = :isDelete')
            ->setParameter('isDelete', $isDelete)
            ->orderBy('t.createdAt', 'DESC');

        if (is_string($search) && trim($search) !== '') {
            $search = trim($search);
            $qb->andWhere('LOWER(t.nom) LIKE LOWER(:search)')
                ->setParameter('search', '%' . $search . '%');
        } else {
            $search = null;
        }

        $total = (clone $qb)->select('COUNT(t.id)')->getQuery()->getSingleScalarResult();

        if ($limit > 0) {
            $qb->setMaxResults($limit)->setFirstResult(($page - 1) * $limit);
        }

        $results = $qb->getQuery()->getResult();

        $data = array_map(static fn($t) => [
            'id' => $t->getId(),
            'nom' => $t->getNom(),
            'isDelete' => $t->isDelete(),
            'createdAt' => $t->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $t->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ], $results);

        $this->actionLogger->logView(
            'TypeTransmission',
            null,
            'Consultation de la liste des types de transmission',
            [
                'total' => (int) $total,
                'page' => $page,
                'limit' => $limit,
                'filters' => [
                    'search' => $search,
                    'is_delete' => $isDelete,
                ],
                'types_transmission' => array_slice($data, 0, 100),
            ]
        );

        return $this->json([
            'page' => $page,
            'limit' => $limit,
            'total' => (int) $total,
            'data' => $data,
        ], 200);
    }
}
