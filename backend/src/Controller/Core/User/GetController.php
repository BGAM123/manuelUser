<?php

namespace App\Controller\Core\User;

use App\Repository\Core\UserRepository;
use App\Repository\Core\ServiceRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "User")]
class GetController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private ServiceRepository $serviceRepository,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/core/user/{id}', name: 'app_core_user_get', methods: ['GET'], requirements: ['id' => '\d+'])]  // âœ… Ajout de requirements
    #[OA\Get(
        path: '/core/user/{id}',
        summary: 'Récupérer un utilisateur par son ID',
        tags: ['User'],
        description: "Retourne les détails complets d'un utilisateur, incluant son service, rôle et correspondant.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de l\'utilisateur à récupérer',
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Utilisateur récupéré avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'username', type: 'string', example: 'jdupont'),
                        new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@minepia.cm'),
                        new OA\Property(property: 'civilite', type: 'string', example: 'M.', nullable: true),
                        new OA\Property(property: 'firstName', type: 'string', example: 'Jean'),
                        new OA\Property(property: 'lastName', type: 'string', example: 'Dupont'),
                        new OA\Property(property: 'fullName', type: 'string', example: 'Jean Dupont'),
                        new OA\Property(property: 'phone', type: 'string', example: '237690123456'),
                        new OA\Property(property: 'avatar', type: 'string', example: '/uploads/core/avatars/avatar_1234567890.jpg', nullable: true),
                        new OA\Property(property: 'isActive', type: 'boolean', example: true),
                        new OA\Property(property: 'isSignataire', type: 'boolean', example: false),
                        new OA\Property(property: 'isVerified', type: 'boolean', example: true),
                        new OA\Property(property: 'isFirstLogin', type: 'boolean', example: false),
                        new OA\Property(property: 'idService', type: 'object', properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 3),
                            new OA\Property(property: 'nom', type: 'string', example: 'Service Financier'),
                            new OA\Property(property: 'sigle', type: 'string', example: 'SF'),
                        ]),
                        new OA\Property(property: 'idRole', type: 'object', properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 2),
                            new OA\Property(property: 'nom', type: 'string', example: 'ROLE_ADMIN'),
                            new OA\Property(property: 'description', type: 'string', example: 'Administrateur système'),
                        ]),
                        new OA\Property(property: 'idCorrespondant', type: 'object', properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 5),
                            new OA\Property(property: 'nom', type: 'string', example: 'Jean Dupont'),
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
                            description: 'Liste enrichie des services additionnels avec informations complètes'
                        ),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-02-14T09:30:00+00:00'),
                        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2025-02-14T09:30:00+00:00'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Utilisateur non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function show(int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetUser');

        $user = $this->userRepository->find($id);

        if (!$user) {
            return $this->json(['code' => 404, 'message' => 'Utilisateur non trouvé.'], 404);
        }

        // Préparer les données de réponse avec gestion des relations supprimées
        $serviceData = null;
        try {
            if ($user->getIdService()) {
                $serviceData = [
                    'id' => $user->getIdService()->getId(),
                    'nom' => $user->getIdService()->getNom(),
                    'sigle' => $user->getIdService()->getSigle(),
                ];
            }
        } catch (\Doctrine\ORM\EntityNotFoundException $e) {
            // Service supprimÃ©, on retourne null
            $serviceData = null;
        }

        $roleData = null;
        try {
            if ($user->getIdRole()) {
                $roleData = [
                    'id' => $user->getIdRole()->getId(),
                    'nom' => $user->getIdRole()->getNom(),
                    'description' => $user->getIdRole()->getDescription(),
                ];
            }
        } catch (\Doctrine\ORM\EntityNotFoundException $e) {
            // RÃ´le supprimÃ©, on retourne null
            $roleData = null;
        }

        $correspondantData = null;
        try {
            if ($user->getIdCorrespondant()) {
                $correspondantData = [
                    'id' => $user->getIdCorrespondant()->getId(),
                    'nom' => $user->getIdCorrespondant()->getNom(),
                ];
            }
        } catch (\Doctrine\ORM\EntityNotFoundException $e) {
            // Correspondant supprimÃ©, on retourne null
            $correspondantData = null;
        }

        // Enrichir les services additionnels avec toutes les informations
        $servicesAdditionelEnriched = [];
        if ($user->getServicesAdditionel()) {
            foreach ($user->getServicesAdditionel() as $serviceAdditional) {
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

        $responseData = [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'civilite' => $user->getCivilite(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'fullName' => $user->getFullName(),
            'phone' => $user->getPhone(),
            'avatar' => $user->getAvatar(),
            'isActive' => $user->isActive(),
            'isSignataire' => $user->isSignataire(),
            'isVerified' => $user->isVerified(),
            'isFirstLogin' => $user->isFirstLogin(),
            'idService' => $serviceData,
            'idRole' => $roleData,
            'idCorrespondant' => $correspondantData,
            'servicesAdditionel' => $servicesAdditionelEnriched,
            'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $user->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];

        // Log de la consultation
        $this->actionLogger->logView(
            'User',
            $user->getId(),
            'Consultation d\'un utilisateur',
            $responseData
        );

        return $this->json($responseData, 200);
    }
}