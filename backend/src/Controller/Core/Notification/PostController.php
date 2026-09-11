<?php

namespace App\Controller\Core\Notification;

use App\Entity\Core\Notification;
use App\Entity\Core\User;
use App\Repository\Core\NotificationRepository;
use App\Repository\Core\ServiceRepository;
use App\Repository\Core\UserRepository;
use App\Service\Core\AccessCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Notification")]
class PostController extends AbstractController
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private UserRepository $userRepository,
        private ServiceRepository $serviceRepository,
        private EntityManagerInterface $entityManager,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/notification', name: 'app_core_notification_post', methods: ['POST'])]
    #[OA\Post(
        path: '/core/notification',
        summary: 'Créer une nouvelle notification',
        description: 'Crée une ou plusieurs notifications pour un utilisateur ou un service',
        tags: ['Notification'],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'titre', type: 'string', example: 'Nouveau courrier reçu'),
                    new OA\Property(property: 'message', type: 'string', example: 'Un nouveau courrier a été enregistré pour votre service'),
                    new OA\Property(property: 'type', type: 'string', example: 'courrier', nullable: true),
                    new OA\Property(property: 'data', type: 'object', example: ['courrier_id' => 123], nullable: true),
                    new OA\Property(property: 'user_ids', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3], description: 'IDs des utilisateurs destinataires'),
                    new OA\Property(property: 'service_ids', type: 'array', items: new OA\Items(type: 'integer'), example: [5, 6], description: 'IDs des services destinataires'),
                ],
                required: ['titre', 'message']
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Notification(s) crée(s) avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Notifications crées avec succès'),
                        new OA\Property(property: 'count', type: 'integer', example: 5),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Données invalides.'),
        ]
    )]
    public function create(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'CreateNotification');

        $data = json_decode($request->getContent(), true);

        // Validation des donnÃ©es
        if (!isset($data['titre']) || !isset($data['message'])) {
            return $this->json(['error' => 'Le titre et le message sont requis'], Response::HTTP_BAD_REQUEST);
        }

        if (!isset($data['user_ids']) && !isset($data['service_ids'])) {
            return $this->json(['error' => 'Au moins un utilisateur ou un service destinataire est requis'], Response::HTTP_BAD_REQUEST);
        }

        $count = 0;
        $resolvedUsers = [];

        $userIdsInput = (isset($data['user_ids']) && is_array($data['user_ids']))
            ? array_map('intval', $data['user_ids'])
            : [];
        $serviceIdsInput = (isset($data['service_ids']) && is_array($data['service_ids']))
            ? array_map('intval', $data['service_ids'])
            : [];

        if (!empty($userIdsInput)) {
            // user_ids fournis => on utilise uniquement ces valeurs
            foreach ($userIdsInput as $userId) {
                $user = $this->userRepository->find($userId);
                if ($user) {
                    $resolvedUsers[$user->getId()] = [
                        'user' => $user,
                        'service' => null,
                    ];
                }
            }
        } elseif (!empty($serviceIdsInput)) {
            // service_ids fournis seulement => on deduit les user_ids
            foreach ($serviceIdsInput as $serviceId) {
                $service = $this->serviceRepository->find($serviceId);
                if ($service) {
                    $users = $this->userRepository->findBy([
                        'idService' => $service,
                        'isActive' => true,
                        'isDelete' => false
                    ]);

                    foreach ($users as $user) {
                        if (!isset($resolvedUsers[$user->getId()])) {
                            $resolvedUsers[$user->getId()] = [
                                'user' => $user,
                                'service' => $service,
                            ];
                        }
                    }
                }
            }
        } else {
            return $this->json(['error' => 'Au moins un utilisateur ou un service destinataire est requis'], Response::HTTP_BAD_REQUEST);
        }

        foreach ($resolvedUsers as $entry) {
            $notification = new Notification();
            $notification->setTitre($data['titre']);
            $notification->setMessage($data['message']);
            $notification->setType($data['type'] ?? null);
            $notification->setData($data['data'] ?? null);
            $notification->setUser($entry['user']);
            $notification->setService($entry['service']);

            $this->entityManager->persist($notification);
            $count++;
        }
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Notifications crées avec succès',
            'count' => $count
        ], Response::HTTP_CREATED);
    }
}
