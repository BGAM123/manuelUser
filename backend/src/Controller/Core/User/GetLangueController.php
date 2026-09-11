<?php

namespace App\Controller\Core\User;

use App\Entity\Core\User;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "User")]
class GetLangueController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/user/langue', name: 'app_core_user_get_langue', methods: ['GET'])]
    #[OA\Get(
        path: '/core/user/langue',
        summary: 'Récupérer la langue de l\'utilisateur connecté',
        description: 'Permet de récupérer la préférence de langue de l\'utilisateur connecté.',
        tags: ['User'],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Langue récupérée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'user', type: 'object', properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'username', type: 'string', example: 'jdupont'),
                            new OA\Property(property: 'langue', type: 'boolean', example: true, description: 'Préférence de langue de l\'utilisateur')
                        ])
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié.')
        ]
    )]
    public function getLangue(): Response
    {
        $user = $this->getUser();
        $this->accessChecker->checker($user, $this->isGranted('ROLE_USER'), 'GetLangue');

        if (!$user instanceof User) {
            return $this->json(['code' => 401, 'message' => 'Utilisateur non authentifié.'], 401);
        }

        return $this->json([
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'langue' => $user->getLangue(),
            ]
        ], 200);
    }
}
