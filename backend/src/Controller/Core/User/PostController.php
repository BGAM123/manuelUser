<?php

namespace App\Controller\Core\User;

use App\Entity\Core\User;
use App\Repository\Core\ServiceRepository;
use App\Repository\Core\RoleRepository;
use App\Repository\Core\CorrespondantRepository;
use App\Service\Core\CrudService;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[OA\Tag(name: "User")]
class PostController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private ServiceRepository $serviceRepository,
        private RoleRepository $roleRepository,
        private CorrespondantRepository $correspondantRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private UserActionLoggerService $actionLogger,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/core/user', name: 'app_core_user_post', methods: ['POST'])]
    #[OA\Post(
        path: '/core/user',
        summary: 'Créer un nouvel utilisateur',
        tags: ['User'],
        security: [["bearerAuth" => []]],
        description: "Crée un nouvel utilisateur avec hash du mot de passe et configuration initiale.",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['username', 'email', 'password', 'lastName'],
                properties: [
                    new OA\Property(property: 'username', type: 'string', example: 'jdupont'),
                    new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@minepia.cm'),
                    new OA\Property(property: 'password', type: 'string', example: 'SecurePass123!'),
                    new OA\Property(property: 'civilite', type: 'string', example: 'M.', description: 'CivilitÃ© (optionnel)', nullable: true),
                    new OA\Property(property: 'firstName', type: 'string', example: 'Jean'),
                    new OA\Property(property: 'lastName', type: 'string', example: 'Dupont'),
                    new OA\Property(property: 'phone', type: 'string', example: '+237690123456'),
                    new OA\Property(property: 'idService', type: 'integer', example: 3, description: 'ID du service'),
                    new OA\Property(property: 'idRole', type: 'integer', example: 1, description: 'ID du rôle (optionnel)', nullable: true),
                    new OA\Property(property: 'idCorrespondant', type: 'integer', example: 5, description: 'ID du correspondant (optionnel)'),
                    new OA\Property(
                        property: 'servicesAdditionel', 
                        type: 'array', 
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'serviceId', type: 'integer', example: 1),
                                new OA\Property(property: 'userId', type: 'integer', example: 5)
                            ]
                        ),
                        example: [
                            ['serviceId' => 1, 'userId' => 5],
                            ['serviceId' => 2, 'userId' => 8]
                        ], 
                        description: 'Liste des services additionnels avec l\'ID du service et l\'ID de l\'utilisateur associé (optionnel)', 
                        nullable: true
                    ),
                    new OA\Property(property: 'isActive', type: 'boolean', example: true),
                    new OA\Property(property: 'isSignataire', type: 'boolean', example: false, description: 'Indique si l\'utilisateur est signataire (optionnel, défaut: false)'),
                    new OA\Property(property: 'isVerified', type: 'boolean', example: true),
                    new OA\Property(property: 'isFirstLogin', type: 'boolean', example: false, description: 'Indique si c\'est la première connexion (optionnel, défaut: true)'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Utilisateur crée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'username', type: 'string', example: 'jdupont'),
                        new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@minepia.cm'),
                        new OA\Property(property: 'fullName', type: 'string', example: 'Jean Dupont'),
                        new OA\Property(property: 'isActive', type: 'boolean', example: true),
                        new OA\Property(property: 'isSignataire', type: 'boolean', example: false),
                        new OA\Property(property: 'isFirstLogin', type: 'boolean', example: true),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-02-14T09:30:00+00:00'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Requête invalide.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function data(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'PostUser');

        $data = json_decode($request->getContent(), true);
        $this->functionService->validate($data);
        $data = $this->functionService->excludeFields($data, ['createdAt', 'updatedAt']);

        try {
            // ðŸ” Validation du mot de passe (le hachage sera fait par CrudService)
            if (empty($data['password'])) {
                return $this->json(['code' => 400, 'message' => 'Le mot de passe est requis.'], 400);
            }

            $user = new User();

            // ðŸ”¹ RÃ©solution des relations
            if (!empty($data['idService'])) {
                $service = $this->serviceRepository->find($data['idService']);
                if (!$service) {
                    return $this->json(['code' => 404, 'message' => 'Service introuvable.'], 404);
                }
                $data['idService'] = $service;
            }

            if (!empty($data['idRole'])) {
                $role = $this->roleRepository->find($data['idRole']);
                if (!$role) {
                    return $this->json(['code' => 404, 'message' => 'Rôle introuvable.'], 404);
                }
                $data['idRole'] = $role;
            } else {
                $data['idRole'] = null;
            }

            if (!empty($data['idCorrespondant'])) {
                $correspondant = $this->correspondantRepository->find($data['idCorrespondant']);
                if (!$correspondant) {
                    return $this->json(['code' => 404, 'message' => 'Correspondant introuvable.'], 404);
                }
                $data['idCorrespondant'] = $correspondant;
            }

            // ðŸ”¹ Gestion des services additionnels
            $servicesAdditionnelsData = null;
            if (isset($data['servicesAdditionel'])) {
                // Valider que c'est un tableau
                if (!is_array($data['servicesAdditionel'])) {
                    return $this->json(['code' => 400, 'message' => 'servicesAdditionel doit être un tableau d\'objets.'], 400);
                }
                
                // Valider la structure de chaque Ã©lÃ©ment
                $servicesAdditionnelsData = [];
                foreach ($data['servicesAdditionel'] as $index => $serviceData) {
                    // VÃ©rifier que c'est un tableau/objet
                    if (!is_array($serviceData)) {
                        return $this->json(['code' => 400, 'message' => "L'élément {$index} de servicesAdditionel doit être un objet."], 400);
                    }
                    
                    // VÃ©rifier la prÃ©sence des champs obligatoires
                    if (!isset($serviceData['serviceId']) || !isset($serviceData['userId'])) {
                        return $this->json(['code' => 400, 'message' => "L'élément {$index} de servicesAdditionel doit contenir 'serviceId' et 'userId'."], 400);
                    }
                    
                    $serviceId = (int) $serviceData['serviceId'];
                    $userId = (int) $serviceData['userId'];
                    
                    // Valider que le service existe
                    $service = $this->serviceRepository->find($serviceId);
                    if (!$service) {
                        return $this->json(['code' => 404, 'message' => "Service avec l'ID {$serviceId} introuvable."], 404);
                    }
                    
                    // Valider que l'utilisateur existe
                    $userRepo = $this->entityManager->getRepository(User::class);
                    $associatedUser = $userRepo->find($userId);
                    if (!$associatedUser) {
                        return $this->json(['code' => 404, 'message' => "Utilisateur avec l'ID {$userId} introuvable."], 404);
                    }
                    
                    // Ajouter Ã  la liste validÃ©e
                    $servicesAdditionnelsData[] = [
                        'serviceId' => $serviceId,
                        'userId' => $userId
                    ];
                }
                
                // Stocker directement les donnÃ©es validÃ©es
                $data['servicesAdditionel'] = $servicesAdditionnelsData;
            }

            // âœ… Gestion des champs boolÃ©ens
            // Mapper isActive â†’ active pour appeler setActive()
            if (isset($data['isActive'])) {
                $data['active'] = (bool) $data['isActive'];
                unset($data['isActive']); // Supprimer l'ancien champ
            }

            // Mapper isSignataire â†’ signataire pour appeler setSignataire()
            if (isset($data['isSignataire'])) {
                $data['signataire'] = (bool) $data['isSignataire'];
                unset($data['isSignataire']); // Supprimer l'ancien champ
            } else {
                // ðŸ”¹ Configuration par dÃ©faut : false
                $data['signataire'] = false;
            }

            // Mapper isVerified â†’ verified pour appeler setVerified()
            if (isset($data['isVerified'])) {
                $data['verified'] = (bool) $data['isVerified'];
                unset($data['isVerified']); // Supprimer l'ancien champ
            }

            // Mapper isFirstLogin â†’ firstLogin pour appeler setFirstLogin()
            if (isset($data['isFirstLogin'])) {
                $data['firstLogin'] = (bool) $data['isFirstLogin'];
                unset($data['isFirstLogin']); // Supprimer l'ancien champ
            } else {
                // ðŸ”¹ Configuration par dÃ©faut : false pour permettre la connexion immÃ©diate
                // Si vous voulez forcer le changement de mot de passe, mettez true
                $data['firstLogin'] = false;
            }

            // âœ… FORCER le rÃ´le ROLE_ADMIN lors de la crÃ©ation
            // Le champ 'roles' est un tableau JSON utilisÃ© par Symfony Security
            // On s'assure qu'il contient toujours ROLE_ADMIN, indÃ©pendamment de ce qui est envoyÃ©
            $data['roles'] = ['ROLE_ADMIN'];

            // ðŸ’¾ Sauvegarde
            $user = $this->crudService->postEntity($user, $data);

            // Log de la crÃ©ation
            $this->actionLogger->logCreate(
                'User',
                $user->getId(),
                'CrÃ©ation d\'un utilisateur',
                [
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                    'civilite' => $user->getCivilite(),
                    'fullName' => $user->getFullName(),
                    'firstName' => $user->getFirstName(),
                    'lastName' => $user->getLastName(),
                    'phone' => $user->getPhone(),
                    'service' => $user->getIdService() ? [
                        'id' => $user->getIdService()->getId(),
                        'nom' => $user->getIdService()->getNom(),
                    ] : null,
                    'role' => $user->getIdRole() ? [
                        'id' => $user->getIdRole()->getId(),
                        'nom' => $user->getIdRole()->getNom(),
                    ] : null,
                    'correspondant' => $user->getIdCorrespondant() ? [
                        'id' => $user->getIdCorrespondant()->getId(),
                        'nom' => $user->getIdCorrespondant()->getNom(),
                    ] : null,
                    'servicesAdditionel' => $user->getServicesAdditionel(),
                    'isActive' => $user->isActive(),
                    'isSignataire' => $user->isSignataire(),
                    'isVerified' => $user->isVerified(),
                    'isFirstLogin' => $user->isFirstLogin(),
                ]
            );

            // Enrichir les services additionnels pour la rÃ©ponse
            $servicesAdditionelEnriched = [];
            if ($user->getServicesAdditionel()) {
                foreach ($user->getServicesAdditionel() as $serviceAdditional) {
                    if (is_array($serviceAdditional) && isset($serviceAdditional['serviceId'], $serviceAdditional['userId'])) {
                        $service = $this->serviceRepository->find($serviceAdditional['serviceId']);
                        $userRepo = $this->entityManager->getRepository(User::class);
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

            return $this->json([
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'civilite' => $user->getCivilite(),
                'fullName' => $user->getFullName(),
                'isActive' => $user->isActive(),
                'isSignataire' => $user->isSignataire(),
                'isFirstLogin' => $user->isFirstLogin(),
                'service' => $user->getIdService()?->getNom(),
                'role' => $user->getIdRole()?->getNom(),
                'servicesAdditionel' => $servicesAdditionelEnriched,
                'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
            ], 201);
        } catch (\Exception $e) {
            // Log l'erreur complÃ¨te pour debug
            error_log('Erreur création utilisateur: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            return $this->json(['code' => 500, 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
        }
    }
}