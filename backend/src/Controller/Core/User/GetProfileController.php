<?php

namespace App\Controller\Core\User;

use App\Service\Core\AccessCheckerService;
use App\Repository\Core\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "User")]
class GetProfileController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker,
        private ServiceRepository $serviceRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    #[Route('/core/user/profile', name: 'app_core_user_get_profile', methods: ['GET'])]
    #[OA\Get(
        path: '/core/user/profile',
        summary: 'Récupérer le profil de l\'utilisateur connecté',
        tags: ['User'],
        description: "Retourne les informations du profil de l'utilisateur actuellement connecté.",
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profil récupéré avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'username', type: 'string', example: 'jdupont'),
                        new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@minepia.cm'),
                        new OA\Property(property: 'firstName', type: 'string', example: 'Jean'),
                        new OA\Property(property: 'lastName', type: 'string', example: 'Dupont'),
                        new OA\Property(property: 'fullName', type: 'string', example: 'Jean Dupont'),
                        new OA\Property(property: 'phone', type: 'string', example: '237690123456'),
                        new OA\Property(property: 'avatar', type: 'string', example: '/uploads/core/avatars/avatar_1234567890.jpg', nullable: true),
                        new OA\Property(property: 'isActive', type: 'boolean', example: true),
                        new OA\Property(property: 'isSignataire', type: 'boolean', example: false),
                        new OA\Property(property: 'isVerified', type: 'boolean', example: true),
                        new OA\Property(property: 'isFirstLogin', type: 'boolean', example: false),
                        new OA\Property(property: 'service', type: 'object', properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 3),
                            new OA\Property(property: 'nom', type: 'string', example: 'Service Financier'),
                            new OA\Property(property: 'sigle', type: 'string', example: 'SF'),
                        ]),
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
                            description: 'Liste enrichie des services additionnels selon ce qui a ete enregistre'
                        ),
                        new OA\Property(property: 'role', type: 'object', properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 2),
                            new OA\Property(property: 'nom', type: 'string', example: 'ROLE_ADMIN'),
                        ]),
                        new OA\Property(property: 'langue', type: 'boolean', example: false, description: 'Langue préférée de l\'utilisateur (false=Français, true=Anglais)'),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-02-14T09:30:00+00:00'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié.')
        ]
    )]
    public function getProfile(): Response
    {
        /** @var \App\Entity\Core\User|null $user */
        $user = $this->getUser();
        $this->accessChecker->checker($user, $this->isGranted('ROLE_USER'), 'GetProfile');

        if (!$user) {
            return $this->json(['code' => 401, 'message' => 'Utilisateur non authentifié.'], 401);
        }

        // Enrichir les services additionnels selon ce qui a ete enregistre
        $servicesAdditionelEnriched = [];
        if ($user->getServicesAdditionel()) {
            foreach ($user->getServicesAdditionel() as $serviceAdditional) {
                // Le champ est un objet avec serviceId et userId
                if (is_array($serviceAdditional) && isset($serviceAdditional['serviceId'], $serviceAdditional['userId'])) {
                    $service = $this->serviceRepository->find($serviceAdditional['serviceId']);
                    $userRepo = $this->entityManager->getRepository(\App\Entity\Core\User::class);
                    $associatedUser = $userRepo->find($serviceAdditional['userId']);

                    $servicesAdditionelEnriched[] = [
                        'serviceId' => $serviceAdditional['serviceId'],
                        'serviceName' => $service ? $this->getFormattedServiceName($service) : null,
                        'userId' => $serviceAdditional['userId'],
                        'userName' => $associatedUser ? $associatedUser->getFullName() : null,
                    ];
                }
            }
        }


        $responseData = [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'fullName' => $user->getFullName(),
            'phone' => $user->getPhone(),
            'avatar' => $user->getAvatar(),
            'isActive' => $user->isActive(),
            'isSignataire' => $user->isSignataire(),
            'isVerified' => $user->isVerified(),
            'isFirstLogin' => $user->isFirstLogin(),
            'service' => $user->getIdService() ? [
                'id' => $user->getIdService()->getId(),
                'nom' => $this->getFormattedServiceName($user->getIdService()),
                'sigle' => $user->getIdService()->getSigle(),
            ] : null,
            'servicesAdditionel' => $servicesAdditionelEnriched,
            'role' => $user->getIdRole() ? [
                'id' => $user->getIdRole()->getId(),
                'nom' => $user->getIdRole()->getNom(),
                'description' => $user->getIdRole()->getDescription(),
            ] : null,
            'langue' => $user->getLangue(),
            'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $user->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];

        return $this->json($responseData, 200);
    }

    /**
     * Formate le nom du service en concatÃ©nant avec le sigle du service parent
     */
    private function getFormattedServiceName($service): string
    {
        if (!$service) {
            return '';
        }

        $serviceName = $service->getNom() ?? '';
        
        // Si le service a un parent, concatÃ©ner avec le sigle du parent
        if ($service->getIdServiceParent()) {
            $parentSigle = $service->getIdServiceParent()->getSigle();
            if ($parentSigle) {
                return $serviceName . ' - ' . $parentSigle;
            }
        }

        return $serviceName;
    }
}
