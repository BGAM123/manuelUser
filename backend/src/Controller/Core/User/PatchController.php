<?php

namespace App\Controller\Core\User;

use App\Entity\Core\User;
use App\Repository\Core\UserRepository;
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

#[OA\Tag(name: "User")]
class PatchController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private UserRepository $userRepository,
        private ServiceRepository $serviceRepository,
        private RoleRepository $roleRepository,
        private CorrespondantRepository $correspondantRepository,
        private UserActionLoggerService $actionLogger,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/core/user/{id<([1-9][0-9]*)>}', name: 'app_core_user_patch', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/user/{id}',
        summary: 'Met à jour un utilisateur existant',
        tags: ['User'],
        description: "Met à jour les informations d'un utilisateur. Le mot de passe ne peut pas être modifié via cette route.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Identifiant de l\'utilisateur', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'username', type: 'string', example: 'jdupont'),
                    new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@minepia.cm'),
                    new OA\Property(property: 'civilite', type: 'string', example: 'M.', description: 'Civilité (optionnel)', nullable: true),
                    new OA\Property(property: 'firstName', type: 'string', example: 'Jean'),
                    new OA\Property(property: 'lastName', type: 'string', example: 'Dupont'),
                    new OA\Property(property: 'phone', type: 'string', example: '237690123456'),
                    new OA\Property(property: 'idService', type: 'integer', example: 3),
                    new OA\Property(property: 'idRole', type: 'integer', example: 2),
                    new OA\Property(property: 'idCorrespondant', type: 'integer', example: 5),
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
                    new OA\Property(property: 'isSignataire', type: 'boolean', example: false, description: 'Indique si l\'utilisateur est signataire'),
                    new OA\Property(property: 'isVerified', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Utilisateur mis à jour avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Utilisateur mis à jour avec succès'),
                        new OA\Property(property: 'user', type: 'object', properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'username', type: 'string', example: 'jdupont'),
                            new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@minepia.cm'),
                            new OA\Property(property: 'fullName', type: 'string', example: 'Jean Dupont'),
                            new OA\Property(property: 'service', type: 'string', example: 'Service Financier'),
                            new OA\Property(property: 'role', type: 'string', example: 'ROLE_ADMIN'),
                            new OA\Property(property: 'isSignataire', type: 'boolean', example: false),
                        ])
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Utilisateur non trouvé.'),
            new OA\Response(response: 400, description: 'Erreur de validation.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function update(Request $request, int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'PatchUser');

        $user = $this->userRepository->find($id);

        if (!$user) {
            return $this->json(['code' => 404, 'message' => 'Utilisateur non trouvé.'], 404);
        }

        $data = json_decode($request->getContent(), true);
        
        // Vérifier si $data est null (erreur de parsing JSON)
        if ($data === null) {
            return $this->json(['code' => 400, 'message' => 'Corps de requête invalide ou vide.'], 400);
        }
        
        $this->functionService->validate($data);
        $data = $this->functionService->excludeFields($data, ['createdAt', 'updatedAt', 'password']);

        // Capture des anciennes donnÃ©es avant modification avec gestion des relations supprimÃ©es
        $oldServiceData = null;
        try {
            if ($user->getIdService()) {
                $oldServiceData = [
                    'id' => $user->getIdService()->getId(),
                    'nom' => $user->getIdService()->getNom(),
                ];
            }
        } catch (\Doctrine\ORM\EntityNotFoundException $e) {
            $oldServiceData = null;
        }

        $oldRoleData = null;
        try {
            if ($user->getIdRole()) {
                $oldRoleData = [
                    'id' => $user->getIdRole()->getId(),
                    'nom' => $user->getIdRole()->getNom(),
                ];
            }
        } catch (\Doctrine\ORM\EntityNotFoundException $e) {
            $oldRoleData = null;
        }

        $oldCorrespondantData = null;
        try {
            if ($user->getIdCorrespondant()) {
                $oldCorrespondantData = [
                    'id' => $user->getIdCorrespondant()->getId(),
                    'nom' => $user->getIdCorrespondant()->getNom(),
                ];
            }
        } catch (\Doctrine\ORM\EntityNotFoundException $e) {
            $oldCorrespondantData = null;
        }

        $oldData = [
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'civilite' => $user->getCivilite(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'fullName' => $user->getFullName(),
            'phone' => $user->getPhone(),
            'service' => $oldServiceData,
            'role' => $oldRoleData,
            'correspondant' => $oldCorrespondantData,
            'isActive' => $user->isActive(),
            'isSignataire' => $user->isSignataire(),
            'isVerified' => $user->isVerified(),
            'servicesAdditionel' => $user->getServicesAdditionel(),
        ];

        try {
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
            }

            if (!empty($data['idCorrespondant'])) {
                $correspondant = $this->correspondantRepository->find($data['idCorrespondant']);
                if (!$correspondant) {
                    return $this->json(['code' => 404, 'message' => 'Correspondant introuvable.'], 404);
                }
                $data['idCorrespondant'] = $correspondant;
            }

            // ðŸ”¹ Gestion des services additionnels
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

            // ï¿½ðŸ’¾ Mise Ã  jour
            $updatedUser = $this->crudService->patchEntity($user, $data);

            // Capture des nouvelles donnÃ©es aprÃ¨s modification avec gestion des relations supprimÃ©es
            $newServiceData = null;
            try {
                if ($updatedUser->getIdService()) {
                    $newServiceData = [
                        'id' => $updatedUser->getIdService()->getId(),
                        'nom' => $updatedUser->getIdService()->getNom(),
                    ];
                }
            } catch (\Doctrine\ORM\EntityNotFoundException $e) {
                $newServiceData = null;
            }

            $newRoleData = null;
            try {
                if ($updatedUser->getIdRole()) {
                    $newRoleData = [
                        'id' => $updatedUser->getIdRole()->getId(),
                        'nom' => $updatedUser->getIdRole()->getNom(),
                    ];
                }
            } catch (\Doctrine\ORM\EntityNotFoundException $e) {
                $newRoleData = null;
            }

            $newCorrespondantData = null;
            try {
                if ($updatedUser->getIdCorrespondant()) {
                    $newCorrespondantData = [
                        'id' => $updatedUser->getIdCorrespondant()->getId(),
                        'nom' => $updatedUser->getIdCorrespondant()->getNom(),
                    ];
                }
            } catch (\Doctrine\ORM\EntityNotFoundException $e) {
                $newCorrespondantData = null;
            }

            $newData = [
                'username' => $updatedUser->getUsername(),
                'email' => $updatedUser->getEmail(),
                'civilite' => $updatedUser->getCivilite(),
                'firstName' => $updatedUser->getFirstName(),
                'lastName' => $updatedUser->getLastName(),
                'fullName' => $updatedUser->getFullName(),
                'phone' => $updatedUser->getPhone(),
                'service' => $newServiceData,
                'role' => $newRoleData,
                'correspondant' => $newCorrespondantData,
                'isActive' => $updatedUser->isActive(),
                'isSignataire' => $updatedUser->isSignataire(),
                'isVerified' => $updatedUser->isVerified(),
                'servicesAdditionel' => $updatedUser->getServicesAdditionel(),
            ];

            // Log de la mise Ã  jour
            $this->actionLogger->logUpdate(
                'User',
                $updatedUser->getId(),
                'Mise Ã  jour d\'un utilisateur',
                [
                    'before' => $oldData,
                    'after' => $newData,
                ]
            );

            // Enrichir les services additionnels pour la rÃ©ponse
            $servicesAdditionelEnriched = [];
            if ($updatedUser->getServicesAdditionel()) {
                foreach ($updatedUser->getServicesAdditionel() as $serviceAdditional) {
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
                'message' => 'Utilisateur mis à  jour avec succès',
                'user' => [
                    'id' => $updatedUser->getId(),
                    'username' => $updatedUser->getUsername(),
                    'email' => $updatedUser->getEmail(),
                    'civilite' => $updatedUser->getCivilite(),
                    'fullName' => $updatedUser->getFullName(),
                    'phone' => $updatedUser->getPhone(),
                    'service' => $updatedUser->getIdService()?->getNom(),
                    'role' => $updatedUser->getIdRole()?->getNom(),
                    'isActive' => $updatedUser->isActive(),
                    'isSignataire' => $updatedUser->isSignataire(),
                    'isVerified' => $updatedUser->isVerified(),
                    'servicesAdditionel' => $servicesAdditionelEnriched,
                    'updatedAt' => $updatedUser->getUpdatedAt()?->format('Y-m-d H:i:s'),
                ]
            ], 200);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}