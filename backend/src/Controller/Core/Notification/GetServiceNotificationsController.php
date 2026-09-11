<?php

namespace App\Controller\Core\Notification;

use App\Entity\Core\User;
use App\Repository\Cour\CourrierInterneRepository;
use App\Repository\Cour\TransmissionRepository;
use App\Repository\Cour\TransmissionReponseRepository;
use App\Repository\Core\NotificationRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Notification")]
class GetServiceNotificationsController extends AbstractController
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private TransmissionReponseRepository $transmissionReponseRepository,
        private CourrierInterneRepository $courrierInterneRepository,
        private TransmissionRepository $transmissionRepository,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/notification/my-service', name: 'app_core_notification_my_service', methods: ['GET'])]
    #[OA\Get(
        path: '/core/notification/my-service',
        summary: 'Lister MES notifications liées à MON service',
        description: 'Retourne uniquement les notifications de l\'utilisateur connecté qui sont liées à son service (id_user = moi ET id_service = mon service)',
        tags: ['Notification'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'order_by', in: 'query', required: false, description: 'Tri par date de création. Utiliser "ASC" ou "DESC".', schema: new OA\Schema(type: 'string', enum: ['DESC', 'ASC'], default: 'DESC')),
            new OA\Parameter(name: 'is_read', in: 'query', required: false, description: 'Filtrer par statut de lecture (true=lu, false=non lu, all=tous).', schema: new OA\Schema(type: 'string', enum: ['true', 'false', 'all'], default: 'all')),
            new OA\Parameter(name: 'type', in: 'query', required: false, description: 'Filtrer par type de notification.', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'start_date', in: 'query', required: false, description: 'Filtrer à partir de cette date (format: YYYY-MM-DD).', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, description: 'Filtrer jusqu\'à cette date (format: YYYY-MM-DD).', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Recherche dans le titre et le message.', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Numéro de page pour la pagination.', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Nombre d\'éléments par page. 0 pour tout récupérer.', schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des notifications de mon service récupérée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'page', type: 'integer', example: 1),
                        new OA\Property(property: 'limit', type: 'integer', example: 20),
                        new OA\Property(property: 'total', type: 'integer', example: 15),
                        new OA\Property(property: 'unread_count', type: 'integer', example: 5, description: 'Nombre de notifications non lues de mon service'),
                        new OA\Property(property: 'service', type: 'object', properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 3),
                            new OA\Property(property: 'nom', type: 'string', example: 'Direction des Ressources Humaines'),
                            new OA\Property(property: 'sigle', type: 'string', example: 'DRH'),
                        ]),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'titre', type: 'string', example: 'Nouveau courrier pour votre service'),
                                    new OA\Property(property: 'message', type: 'string', example: 'Un nouveau courrier a été enregistré pour le service RH'),
                                    new OA\Property(property: 'type', type: 'string', example: 'courrier', nullable: true),
                                    new OA\Property(property: 'isRead', type: 'boolean', example: false),
                                    new OA\Property(property: 'readAt', type: 'string', format: 'date-time', nullable: true),
                                    new OA\Property(property: 'data', type: 'object', nullable: true),
                                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-11-18T09:15:00+00:00'),
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé.'),
            new OA\Response(response: 404, description: 'L\'utilisateur n\'est affecté à aucun service.'),
        ]
    )]
    public function getMyServiceNotifications(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetMyServiceNotifications');

        /** @var User $user */
        $user = $this->getUser();

        // VÃ©rifier que l'utilisateur a bien un service
        if (!$user->getIdService()) {
            return $this->json([
                'error' => 'Vous n\'êtes affecté à aucun service',
                'message' => 'Cette API nécessite que vous soyez membre d\'un service'
            ], Response::HTTP_NOT_FOUND);
        }

        $userService = $user->getIdService();

        // VÃ©rifier que le service existe rÃ©ellement en base de donnÃ©es
        try {
            // Force le chargement du proxy pour vÃ©rifier l'existence
            $serviceId = $userService->getId();
            $serviceName = $userService->getNom();
        } catch (\Doctrine\ORM\EntityNotFoundException $e) {
            return $this->json([
                'error' => 'Service introuvable',
                'message' => 'Le service auquel vous êtes affecté n\'existe plus en base de donnés. Veuillez contacter un administrateur.',
                'service_id' => $user->getIdService() ? 'ID non accessible' : null
            ], Response::HTTP_NOT_FOUND);
        }

        // Récupération des paramètres de requête
        $filters = [
            'order_by' => $request->query->get('order_by', 'DESC'),
            'is_read' => $request->query->get('is_read', 'all'),
            'type' => $request->query->get('type'),
            'start_date' => $request->query->get('start_date'),
            'end_date' => $request->query->get('end_date'),
            'search' => $request->query->get('search'),
            'page' => max(1, (int)$request->query->get('page', 1)),
            'limit' => (int)$request->query->get('limit', 20),
        ];

        // Construction de la requÃªte
        // IMPORTANT: On filtre par id_user = moi ET id_service = mon service
        $queryBuilder = $this->notificationRepository->createQueryBuilder('n')
            ->leftJoin('n.user', 'u')
            ->leftJoin('n.service', 's')
            ->addSelect('u', 's')
            ->where('n.user = :user')
            ->andWhere('n.service = :service')
            ->setParameter('user', $user)
            ->setParameter('service', $userService);

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

        // Comptage des non lues pour ce service
        $unreadQueryBuilder = clone $queryBuilder;
        $unreadCount = (int) $unreadQueryBuilder
            ->select('COUNT(n.id)')
            ->andWhere('n.isRead = false')
            ->getQuery()
            ->getSingleScalarResult();

        // Pagination
        if ($filters['limit'] > 0) {
            $offset = ($filters['page'] - 1) * $filters['limit'];
            $queryBuilder->setFirstResult($offset)
                ->setMaxResults($filters['limit']);
        }

        $notifications = $queryBuilder->getQuery()->getResult();

        $numeroByTransmissionReponseId = $this->buildNumeroByTransmissionReponseIdMapFromNotifications($notifications);

        // Formatage des donnÃ©es
        $data = array_map(function ($notification) use ($numeroByTransmissionReponseId) {
            $notificationData = $notification->getData();
            $notificationData = $this->injectNumeroIntoTransmissionReponseNotificationData($notificationData, $numeroByTransmissionReponseId);

            return [
                'id' => $notification->getId(),
                'titre' => $notification->getTitre(),
                'message' => $notification->getMessage(),
                'type' => $notification->getType(),
                'isRead' => $notification->isRead(),
                'readAt' => $notification->getReadAt()?->format('c'),
                'data' => $notificationData,
                'createdAt' => $notification->getCreatedAt()->format('c'),
            ];
        }, $notifications);

        return $this->json([
            'page' => $filters['page'],
            'limit' => $filters['limit'],
            'total' => $total,
            'unread_count' => $unreadCount,
            'service' => [
                'id' => $userService->getId(),
                'nom' => $userService->getNom(),
                'sigle' => $userService->getSigle(),
            ],
            'data' => $data,
        ]);
    }

    private function injectNumeroIntoTransmissionReponseNotificationData(?array $data, array $numeroByTransmissionReponseId): ?array
    {
        if (!is_array($data)) {
            return $data;
        }

        $transmissionReponseId = $data['transmission_reponse_id'] ?? null;
        if (!is_numeric($transmissionReponseId)) {
            return $data;
        }

        $transmissionReponseId = (int) $transmissionReponseId;
        if ($transmissionReponseId <= 0) {
            return $data;
        }

        if (!empty($data['numero'])) {
            return $data;
        }

        $numero = $numeroByTransmissionReponseId[$transmissionReponseId] ?? null;
        if (is_string($numero) && $numero !== '') {
            $data['numero'] = $numero;
        }

        return $data;
    }

    private function buildNumeroByTransmissionReponseIdMapFromNotifications(array $notifications): array
    {
        $ids = [];
        foreach ($notifications as $notification) {
            if (!$notification instanceof \App\Entity\Core\Notification) {
                continue;
            }
            $data = $notification->getData();
            if (!is_array($data)) {
                continue;
            }
            $transmissionReponseId = $data['transmission_reponse_id'] ?? null;
            if (is_numeric($transmissionReponseId)) {
                $ids[] = (int) $transmissionReponseId;
            }
        }

        return $this->buildNumeroByTransmissionReponseIdMap($ids);
    }

    /**
     * @param int[] $transmissionReponseIds
     * @return array<int, string> Map [transmissionReponseId => numero]
     */
    private function buildNumeroByTransmissionReponseIdMap(array $transmissionReponseIds): array
    {
        $transmissionReponseIds = array_values(array_unique(array_map('intval', $transmissionReponseIds)));
        $transmissionReponseIds = array_values(array_filter($transmissionReponseIds, static fn (int $id) => $id > 0));

        if (empty($transmissionReponseIds)) {
            return [];
        }

        $transmissionReponses = $this->transmissionReponseRepository->findBy(['id' => $transmissionReponseIds]);

        $firstCourrierInterneIdByTransmissionReponseId = [];
        $firstTransmissionIdByTransmissionReponseId = [];

        foreach ($transmissionReponses as $transmissionReponse) {
            if (!$transmissionReponse instanceof \App\Entity\Cour\TransmissionReponse || !$transmissionReponse->getId()) {
                continue;
            }

            $transmissionReponseId = (int) $transmissionReponse->getId();

            $courrierInterneIds = $transmissionReponse->getIdCourrierInternes();
            if (is_array($courrierInterneIds) && !empty($courrierInterneIds)) {
                $firstCourrierInterneId = (int) (reset($courrierInterneIds) ?: 0);
                if ($firstCourrierInterneId > 0) {
                    $firstCourrierInterneIdByTransmissionReponseId[$transmissionReponseId] = $firstCourrierInterneId;
                }
            }

            $transmissionIds = $transmissionReponse->getIdTransmission();
            if (is_array($transmissionIds) && !empty($transmissionIds)) {
                $firstTransmissionId = (int) (reset($transmissionIds) ?: 0);
                if ($firstTransmissionId > 0) {
                    $firstTransmissionIdByTransmissionReponseId[$transmissionReponseId] = $firstTransmissionId;
                }
            }
        }

        $numeroByCourrierInterneId = [];
        $courrierInterneIds = array_values(array_unique(array_values($firstCourrierInterneIdByTransmissionReponseId)));
        if (!empty($courrierInterneIds)) {
            foreach ($this->courrierInterneRepository->findBy(['id' => $courrierInterneIds]) as $courrierInterne) {
                if ($courrierInterne instanceof \App\Entity\Cour\CourrierInterne && $courrierInterne->getId()) {
                    $numeroByCourrierInterneId[(int) $courrierInterne->getId()] = (string) ($courrierInterne->getNumero() ?? '');
                }
            }
        }

        $numeroByTransmissionId = [];
        $transmissionIds = array_values(array_unique(array_values($firstTransmissionIdByTransmissionReponseId)));
        if (!empty($transmissionIds)) {
            foreach ($this->transmissionRepository->findBy(['id' => $transmissionIds]) as $transmission) {
                if ($transmission instanceof \App\Entity\Cour\Transmission && $transmission->getId()) {
                    $numeroByTransmissionId[(int) $transmission->getId()] = (string) ($transmission->getIdCourrier()?->getNumero() ?? '');
                }
            }
        }

        $numeroByTransmissionReponseId = [];
        foreach ($transmissionReponseIds as $transmissionReponseId) {
            $numero = null;

            $courrierInterneId = $firstCourrierInterneIdByTransmissionReponseId[$transmissionReponseId] ?? null;
            if (is_int($courrierInterneId)) {
                $numero = $numeroByCourrierInterneId[$courrierInterneId] ?? null;
                $numero = is_string($numero) && $numero !== '' ? $numero : null;
            }

            if (!$numero) {
                $transmissionId = $firstTransmissionIdByTransmissionReponseId[$transmissionReponseId] ?? null;
                if (is_int($transmissionId)) {
                    $numero = $numeroByTransmissionId[$transmissionId] ?? null;
                    $numero = is_string($numero) && $numero !== '' ? $numero : null;
                }
            }

            if ($numero) {
                $numeroByTransmissionReponseId[$transmissionReponseId] = $numero;
            }
        }

        return $numeroByTransmissionReponseId;
    }
}
