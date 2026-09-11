<?php

namespace App\Controller\Core\Notification;

use App\Entity\Core\User;
use App\Repository\Core\NotificationRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Notification")]
class GetCollectionController extends AbstractController
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/notification', name: 'app_core_notification_get_collection', methods: ['GET'])]
    #[OA\Get(
        path: '/core/notification',
        summary: 'Lister les notifications de l\'utilisateur connecté',
        description: 'Retourne la liste des notifications de l\'utilisateur et/ou de son service avec filtres et pagination',
        tags: ['Notification'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'order_by', in: 'query', required: false, description: 'Tri par date de création. Utiliser "ASC" ou "DESC".', schema: new OA\Schema(type: 'string', enum: ['DESC', 'ASC'], default: 'DESC')),
            new OA\Parameter(name: 'is_read', in: 'query', required: false, description: 'Filtrer par statut de lecture (true=lu, false=non lu, null=tous).', schema: new OA\Schema(type: 'string', enum: ['true', 'false', 'all'], default: 'all')),
            new OA\Parameter(name: 'type', in: 'query', required: false, description: 'Filtrer par type de notification.', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'scope', in: 'query', required: false, description: 'Scope de notifications : "user" (seulement utilisateur), "service" (seulement service), "all" (utilisateur + service).', schema: new OA\Schema(type: 'string', enum: ['user', 'service', 'all'], default: 'all')),
            new OA\Parameter(name: 'start_date', in: 'query', required: false, description: 'Filtrer à partir de cette date (format: YYYY-MM-DD).', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, description: 'Filtrer jusqu\'à cette date (format: YYYY-MM-DD).', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Recherche dans le titre et le message.', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Numéro de page pour la pagination.', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Nombre d\'éléments par page. 0 pour tout récupérer.', schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des notifications récupérée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'page', type: 'integer', example: 1),
                        new OA\Property(property: 'limit', type: 'integer', example: 20),
                        new OA\Property(property: 'total', type: 'integer', example: 45),
                        new OA\Property(property: 'unread_count', type: 'integer', example: 12, description: 'Nombre total de notifications non lues'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'titre', type: 'string', example: 'Nouveau courrier reçu'),
                                    new OA\Property(property: 'message', type: 'string', example: 'Un nouveau courrier a été enregistré pour votre service'),
                                    new OA\Property(property: 'type', type: 'string', example: 'courrier', nullable: true),
                                    new OA\Property(property: 'isRead', type: 'boolean', example: false),
                                    new OA\Property(property: 'readAt', type: 'string', format: 'date-time', example: '2025-11-18T10:30:00+00:00', nullable: true),
                                    new OA\Property(property: 'data', type: 'object', example: ['courrier_id' => 123], nullable: true),
                                    new OA\Property(property: 'user', type: 'object', properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 5),
                                        new OA\Property(property: 'username', type: 'string', example: 'jdupont'),
                                    ]),
                                    new OA\Property(property: 'service', type: 'object', nullable: true, properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 3),
                                        new OA\Property(property: 'nom', type: 'string', example: 'Direction des Ressources Humaines'),
                                    ]),
                                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-11-18T09:15:00+00:00'),
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
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetNotificationCollection');

        /** @var User $user */
        $user = $this->getUser();

        // RÃ©cupÃ©ration des paramÃ¨tres de requÃªte
        $filters = [
            'order_by' => $request->query->get('order_by', 'DESC'),
            'is_read' => $request->query->get('is_read', 'all'),
            'type' => $request->query->get('type'),
            'scope' => $request->query->get('scope', 'all'),
            'start_date' => $request->query->get('start_date'),
            'end_date' => $request->query->get('end_date'),
            'search' => $request->query->get('search'),
            'page' => max(1, (int)$request->query->get('page', 1)),
            'limit' => (int)$request->query->get('limit', 20),
        ];

        // Construction de la requÃªte de base
        $queryBuilder = $this->notificationRepository->createQueryBuilder('n')
            ->leftJoin('n.user', 'u')
            ->leftJoin('n.service', 's')
            ->addSelect('u', 's');

        // Filtrage par scope (utilisateur, service, ou les deux)
        if ($filters['scope'] === 'user') {
            $queryBuilder->andWhere('n.user = :user')
                ->setParameter('user', $user);
        } elseif ($filters['scope'] === 'service' && $user->getIdService() !== null) {
            $queryBuilder->andWhere('n.service = :service')
                ->setParameter('service', $user->getIdService());
        } else {
            // Par dÃ©faut, rÃ©cupÃ©rer les notifications de l'utilisateur ET de son service
            $queryBuilder->andWhere('n.user = :user')
                ->setParameter('user', $user);
            
            if ($user->getIdService() !== null) {
                $queryBuilder->orWhere('n.service = :service')
                    ->setParameter('service', $user->getIdService());
            }
        }

        // Filtre par statut de lecture
        if ($filters['is_read'] === 'true') {
            $queryBuilder->andWhere('n.isRead = true');
        } elseif ($filters['is_read'] === 'false') {
            $queryBuilder->andWhere('n.isRead = false');
        }

        // Filtre par type
        if ($filters['type']) {
            $queryBuilder->andWhere('n.type = :type')
                ->setParameter('type', $filters['type']);
        }

        // Filtre par date de dÃ©but
        if ($filters['start_date']) {
            try {
                $startDate = new \DateTimeImmutable($filters['start_date']);
                $queryBuilder->andWhere('n.createdAt >= :startDate')
                    ->setParameter('startDate', $startDate);
            } catch (\Exception $e) {
                // Date invalide, on ignore le filtre
            }
        }

        // Filtre par date de fin
        if ($filters['end_date']) {
            try {
                $endDate = new \DateTimeImmutable($filters['end_date'] . ' 23:59:59');
                $queryBuilder->andWhere('n.createdAt <= :endDate')
                    ->setParameter('endDate', $endDate);
            } catch (\Exception $e) {
                // Date invalide, on ignore le filtre
            }
        }

        // Recherche textuelle
        if ($filters['search']) {
            $queryBuilder->andWhere('n.titre LIKE :search OR n.message LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        // Tri
        $queryBuilder->orderBy('n.createdAt', $filters['order_by']);

        // Comptage total
        $totalQueryBuilder = clone $queryBuilder;
        $total = (int) $totalQueryBuilder->select('COUNT(n.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Pagination
        if ($filters['limit'] > 0) {
            $offset = ($filters['page'] - 1) * $filters['limit'];
            $queryBuilder->setFirstResult($offset)
                ->setMaxResults($filters['limit']);
        }

        $notifications = $queryBuilder->getQuery()->getResult();

        // Comptage des notifications non lues
        $unreadCount = $this->notificationRepository->countUnreadByUserOrService($user);

        // Formatage des donnÃ©es
        $data = array_map(function ($notification) {
            return [
                'id' => $notification->getId(),
                'titre' => $notification->getTitre(),
                'message' => $notification->getMessage(),
                'type' => $notification->getType(),
                'isRead' => $notification->isRead(),
                'readAt' => $notification->getReadAt()?->format('c'),
                'data' => $notification->getData(),
                'user' => [
                    'id' => $notification->getUser()->getId(),
                    'username' => $notification->getUser()->getUsername(),
                    'fullName' => trim($notification->getUser()->getFirstName() . ' ' . $notification->getUser()->getLastName()),
                ],
                'service' => $notification->getService() ? [
                    'id' => $notification->getService()->getId(),
                    'nom' => $notification->getService()->getNom(),
                    'sigle' => $notification->getService()->getSigle(),
                ] : null,
                'createdAt' => $notification->getCreatedAt()->format('c'),
            ];
        }, $notifications);

        return $this->json([
            'page' => $filters['page'],
            'limit' => $filters['limit'],
            'total' => $total,
            'unread_count' => $unreadCount,
            'data' => $data,
        ]);
    }
}
