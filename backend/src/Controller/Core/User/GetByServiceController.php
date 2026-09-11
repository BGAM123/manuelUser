<?php

namespace App\Controller\Core\User;

use App\Repository\Core\UserRepository;
use App\Repository\Core\ServiceRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "User")]
class GetByServiceController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private ServiceRepository $serviceRepository,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/user/by-service/{serviceId<([1-9][0-9]*)>}', name: 'app_core_user_get_by_service', methods: ['GET'])]
    #[OA\Get(
        path: '/core/user/by-service/{serviceId}',
        summary: 'Lister les utilisateurs d\'un service spécifique',
        tags: ['User'],
        description: "Retourne la liste des utilisateurs appartenant à un service donné.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'serviceId',
                in: 'path',
                required: true,
                description: 'ID du service',
                schema: new OA\Schema(type: 'integer', example: 3)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des utilisateurs r?cup?r?e avec succ?s.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'service', type: 'object', properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 3),
                            new OA\Property(property: 'nom', type: 'string', example: 'Service Financier'),
                            new OA\Property(property: 'sigle', type: 'string', example: 'SF'),
                        ]),
                        new OA\Property(property: 'total', type: 'integer', example: 12),
                        new OA\Property(
                            property: 'users',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'username', type: 'string', example: 'jdupont'),
                                    new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@minepia.cm'),
                                    new OA\Property(property: 'fullName', type: 'string', example: 'Jean Dupont'),
                                    new OA\Property(property: 'role', type: 'string', example: 'ROLE_ADMIN'),
                                    new OA\Property(property: 'isActive', type: 'boolean', example: true),
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Service non trouv?.'),
            new OA\Response(response: 401, description: 'Acc?s non autoris?.')
        ]
    )]
    public function getUsersByService(int $serviceId): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetUsersByService');

        $service = $this->serviceRepository->find($serviceId);

        if (!$service) {
            return $this->json(['code' => 404, 'message' => 'Service non trouv?.'], 404);
        }

        $users = $this->userRepository->findBy(
            ['idService' => $service, 'isDelete' => false],
            ['lastName' => 'ASC']
        );

        $data = array_map(fn($u) => [
            'id' => $u->getId(),
            'username' => $u->getUsername(),
            'email' => $u->getEmail(),
            'fullName' => $u->getFullName(),
            'phone' => $u->getPhone(),
            'role' => $u->getIdRole()?->getNom(),
            'isActive' => $u->isActive(),
            'isVerified' => $u->isVerified(),
        ], $users);

        return $this->json([
            'service' => [
                'id' => $service->getId(),
                'nom' => $service->getNom(),
                'sigle' => $service->getSigle(),
            ],
            'total' => count($data),
            'users' => $data
        ], 200);
    }
}