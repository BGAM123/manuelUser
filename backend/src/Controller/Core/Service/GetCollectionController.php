<?php

namespace App\Controller\Core\Service;

use App\Repository\Core\ServiceRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Service")]
class GetCollectionController extends AbstractController
{
    public function __construct(
        private ServiceRepository $serviceRepository,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger,
    ) {}

    #[Route('/core/service', name: 'app_core_service_get_collection', methods: ['GET'])]
    #[OA\Get(
        path: '/core/service',
        summary: 'Lister les services',
        description: 'Retourne la liste paginée des services avec filtres sur la recherche, la suppression logique et l\'activation.',
        tags: ['Service'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Filtre de recherche (nom ou sigle)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'is_delete', in: 'query', required: false, description: 'Inclure les éléments supprimés (true/false)', schema: new OA\Schema(type: 'boolean', default: false)),
            new OA\Parameter(name: 'is_active', in: 'query', required: false, description: 'Filtrer par statut actif/inactif (true/false)', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Numéro de page pour la pagination', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Nombre d\'éléments par page (0 pour tout)', schema: new OA\Schema(type: 'integer', default: 10))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste récupérée avec succès.',
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
                                    new OA\Property(property: 'nom', type: 'string', example: 'Direction Générale'),
                                    new OA\Property(property: 'sigle', type: 'string', example: 'DG'),
                                    new OA\Property(property: 'numeroOrdre', type: 'integer', example: 1, description: 'Numéro d\'ordre pour le tri'),
                                    new OA\Property(property: 'typeService', type: 'string', example: 'service', description: 'Type de service (poste ou service)'),
                                    new OA\Property(property: 'isDirection', type: 'boolean', example: false),
                                    new OA\Property(property: 'isVisibleInTransmission', type: 'boolean', example: false),
                                    new OA\Property(property: 'isActive', type: 'boolean', example: true),
                                    new OA\Property(property: 'isDelete', type: 'boolean', example: false),
                                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-01-14T09:30:00+00:00'),
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
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetServiceCollection');

        $search = $request->query->get('search');
        $isDelete = filter_var($request->query->get('is_delete', false), FILTER_VALIDATE_BOOLEAN);
        $isActive = $request->query->has('is_active') ? filter_var($request->query->get('is_active'), FILTER_VALIDATE_BOOLEAN) : null;
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = (int) $request->query->get('limit', 10);

        $qb = $this->serviceRepository->createQueryBuilder('s')
            ->where('s.isDelete = :isDelete')
            ->setParameter('isDelete', $isDelete)
            ->orderBy('s.createdAt', 'DESC');

        if ($isActive !== null) {
            $qb->andWhere('s.isActive = :isActive')->setParameter('isActive', $isActive);
        }

        if (!empty($search)) {
            $qb->andWhere('(LOWER(s.nom) LIKE LOWER(:search) OR LOWER(s.sigle) LIKE LOWER(:search))')
                ->setParameter('search', '%' . trim($search) . '%');
        }

        $total = (clone $qb)->select('COUNT(s.id)')->getQuery()->getSingleScalarResult();

        if ($limit > 0) {
            $qb->setMaxResults($limit)->setFirstResult(($page - 1) * $limit);
        }

        $results = $qb->getQuery()->getResult();

        $data = array_map(fn($s) => [
            'id' => $s->getId(),
            'nom' => $s->getNom(),
            'sigle' => $s->getSigle(),
            'idChefService' => $s->getChefService()?->getFullName(),
            'idServiceParent' => $s->getIdServiceParent()?->getNom(),
            'telephone' => $s->getTelephone(),
            'numeroOrdre' => $s->getNumeroOrdre(),
            'typeService' => $s->getTypeService(),
            'isActive' => $s->isActive(),
            'isDirection' => $s->isDirection(),
            'isVisibleInTransmission' => $s->isVisibleInTransmission(),
            'isDelete' => $s->isDelete(),
            'createdAt' => $s->getCreatedAt()?->format('Y-m-d H:i:s'),
        ], array: $results);

        // Logger la consultation de la liste avec toutes les donnÃ©es
        $this->actionLogger->logView(
            'Service',
            null,
            'Consultation de la liste des services',
            [
                'total' => (int)$total,
                'page' => $page,
                'limit' => $limit,
                'search' => $search,
                'is_delete' => $isDelete,
                'is_active' => $isActive,
                'services' => array_slice($data, 0, 100) // Limiter Ã  100 pour les logs
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
