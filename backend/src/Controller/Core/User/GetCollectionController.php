<?php

namespace App\Controller\Core\User;

use App\Repository\Core\UserRepository;
use App\Repository\Core\ServiceRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "User")]
class GetCollectionController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private ServiceRepository $serviceRepository,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/core/user', name: 'app_core_user_get_collection', methods: ['GET'])]
    #[OA\Get(
        path: '/core/user',
        summary: 'Lister les utilisateurs avec filtres et pagination',
        description: 'Retourne la liste des utilisateurs filtrÃ©s par service, rÃ´le, statut, etc.',
        tags: ['User'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'order_by', in: 'query', required: false, description: 'Specify the sort direction relative to the creation date. Use "ASC" or "DESC".', schema: new OA\Schema(type: 'string', enum: ['DESC', 'ASC'], default: 'DESC')),
            new OA\Parameter(name: 'start_date', in: 'query', required: false, description: 'Filter from this date (format: YYYY-MM-DD HH:MM:SS).', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, description: 'Filter to this date (format: YYYY-MM-DD HH:MM:SS).', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'service', in: 'query', required: false, description: 'Filter by service ID.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'role', in: 'query', required: false, description: 'Filter by role ID.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'is_active', in: 'query', required: false, description: 'Filter by active status. By default, only active users (true) are returned.', schema: new OA\Schema(type: 'boolean', default: true)),
            new OA\Parameter(name: 'is_signataire', in: 'query', required: false, description: 'Filter by signataire status.', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'is_verified', in: 'query', required: false, description: 'Filter by verified status.', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'The page number for pagination.', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'The number of items per page. 0 to retrieve all.', schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'is_delete', in: 'query', required: false, description: 'Filter deleted users.', schema: new OA\Schema(type: 'boolean', default: false)),
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Search string in username, email, firstName, lastName, etc.', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des utilisateurs récupérée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'page', type: 'integer', example: 1),
                        new OA\Property(property: 'limit', type: 'integer', example: 10),
                        new OA\Property(property: 'total', type: 'integer', example: 45),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'username', type: 'string', example: 'jdupont'),
                                    new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@minepia.cm'),
                                    new OA\Property(property: 'civilite', type: 'string', example: 'M.', nullable: true),
                                    new OA\Property(property: 'fullName', type: 'string', example: 'Jean Dupont'),
                                    new OA\Property(property: 'service', type: 'integer', example: 1, description: 'ID du service de l\'utilisateur'),
                                    new OA\Property(property: 'role', type: 'integer', example: 2, description: 'ID du rôle de l\'utilisateur'),
                                    new OA\Property(
                                        property: 'servicesAdditionel', 
                                        type: 'array', 
                                        items: new OA\Items(
                                            type: 'object',
                                            properties: [
                                                new OA\Property(property: 'serviceId', type: 'integer', example: 1),
                                                new OA\Property(property: 'serviceName', type: 'string', example: 'Service Informatique'),
                                                new OA\Property(property: 'userId', type: 'integer', example: 5),
                                                new OA\Property(property: 'userName', type: 'string', example: 'Jean Dupont')
                                            ]
                                        ),
                                        description: 'Liste enrichie des services additionnels avec informations complètes'
                                    ),
                                    new OA\Property(property: 'isActive', type: 'boolean', example: true),
                                    new OA\Property(property: 'isSignataire', type: 'boolean', example: false),
                                    new OA\Property(property: 'isVerified', type: 'boolean', example: true),
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
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetUserCollection');

        $filters = [
            'order_by' => $request->query->get('order_by', 'DESC'),
            'start_date' => $request->query->get('start_date'),
            'end_date' => $request->query->get('end_date'),
            'service' => $request->query->get('service'),
            'role' => $request->query->get('role'),
            'is_active' => $request->query->has('is_active') ? filter_var($request->query->get('is_active'), FILTER_VALIDATE_BOOLEAN) : true,
            'is_signataire' => $request->query->has('is_signataire') ? filter_var($request->query->get('is_signataire'), FILTER_VALIDATE_BOOLEAN) : null,
            'is_verified' => $request->query->has('is_verified') ? filter_var($request->query->get('is_verified'), FILTER_VALIDATE_BOOLEAN) : null,
            'is_delete' => filter_var($request->query->get('is_delete', false), FILTER_VALIDATE_BOOLEAN),
            'search' => $request->query->get('search'),
            'page' => max(1, (int)$request->query->get('page', 1)),
            'limit' => (int)$request->query->get('limit', 10),
        ];

        $queryBuilder = $this->userRepository->createQueryBuilder('u')
            ->leftJoin('u.idService', 's')
            ->leftJoin('u.idRole', 'r')
            ->addSelect('s', 'r')
            ->where('u.isDelete = :isDelete')
            ->setParameter('isDelete', $filters['is_delete']);

        // ðŸ” Recherche texte
        if (!empty($filters['search'])) {
            $queryBuilder->andWhere('LOWER(u.search) LIKE LOWER(:search)')
                         ->setParameter('search', '%' . trim($filters['search']) . '%');
        }

        // ðŸ“† Filtres de date
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $queryBuilder->andWhere('u.createdAt BETWEEN :start AND :end')
                         ->setParameter('start', new \DateTime($filters['start_date']))
                         ->setParameter('end', new \DateTime($filters['end_date']));
        }

        // ðŸ§© Filtres additionnels
        if (!empty($filters['service'])) {
            $queryBuilder->andWhere('u.idService = :service')
                         ->setParameter('service', $filters['service']);
        }
        if (!empty($filters['role'])) {
            $queryBuilder->andWhere('u.idRole = :role')
                         ->setParameter('role', $filters['role']);
        }
        if ($filters['is_active'] !== null) {
            $queryBuilder->andWhere('u.isActive = :isActive')
                         ->setParameter('isActive', $filters['is_active']);
        }
        if ($filters['is_signataire'] !== null) {
            $queryBuilder->andWhere('u.isSignataire = :isSignataire')
                         ->setParameter('isSignataire', $filters['is_signataire']);
        }
        if ($filters['is_verified'] !== null) {
            $queryBuilder->andWhere('u.isVerified = :isVerified')
                         ->setParameter('isVerified', $filters['is_verified']);
        }

        // ðŸ”„ Tri
        $queryBuilder->orderBy('u.createdAt', $filters['order_by']);

        // ðŸ“„ Pagination
        $limit = $filters['limit'];
        $page = $filters['page'];
        $total = (clone $queryBuilder)->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();

        if ($limit > 0) {
            $queryBuilder->setMaxResults($limit)->setFirstResult(($page - 1) * $limit);
        }

        $results = $queryBuilder->getQuery()->getResult();

        $data = array_map(function($u) {
            // Enrichir les services additionnels avec toutes les informations
            $servicesAdditionelEnriched = [];
            if ($u->getServicesAdditionel()) {
                foreach ($u->getServicesAdditionel() as $serviceAdditional) {
                    // Le champ est maintenant un objet avec serviceId et userId
                    if (is_array($serviceAdditional) && isset($serviceAdditional['serviceId'], $serviceAdditional['userId'])) {
                        $service = $this->serviceRepository->find($serviceAdditional['serviceId']);
                        $userRepo = $this->entityManager->getRepository(\App\Entity\Core\User::class);
                        $associatedUser = $userRepo->find($serviceAdditional['userId']);
                        
                        $servicesAdditionelEnriched[] = [
                            'serviceId' => $serviceAdditional['serviceId'],
                            'serviceName' => $service ? $service->getNom() : null,
                            'userId' => $serviceAdditional['userId'],
                            'userName' => $associatedUser ? $associatedUser->getFullName() : null,
                        ];
                    }
                }
            }

            return [
                'id' => $u->getId(),
                'username' => $u->getUsername(),
                'email' => $u->getEmail(),
                'civilite' => $u->getCivilite(),
                'fullName' => $u->getFullName(),
                'firstName' => $u->getFirstName(),
                'lastName' => $u->getLastName(),
                'phone' => $u->getPhone(),
                'service' => $u->getIdService()?->getId(),
                'role' => $u->getIdRole()?->getId(),
                'servicesAdditionel' => $servicesAdditionelEnriched,
                'isActive' => $u->isActive(),
                'isSignataire' => $u->isSignataire(),
                'isVerified' => $u->isVerified(),
                'isFirstLogin' => $u->isFirstLogin(),
                'createdAt' => $u->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $results);

        // Log de la consultation de la collection
        $this->actionLogger->logView(
            'User',
            null,
            'Consultation de la liste des utilisateurs',
            [
                'total' => (int)$total,
                'page' => $page,
                'limit' => $limit,
                'filters' => $filters,
                'users' => array_slice($data, 0, 100) // Limiter Ã  100
            ]
        );

        return $this->json([
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'data' => $data
        ], 200);
    }
}