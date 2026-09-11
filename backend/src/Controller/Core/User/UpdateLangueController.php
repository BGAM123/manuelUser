<?php

namespace App\Controller\Core\User;

use App\Entity\Core\User;
use App\Service\Core\CrudService;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "User")]
class UpdateLangueController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/user/langue', name: 'app_core_user_update_langue', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/user/langue',
        summary: 'Modifier la langue de l\'utilisateur connecté',
        description: 'Permet à l\'utilisateur de modifier sa préférence de langue (true ou false).',
        tags: ['User'],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'langue', type: 'boolean', example: true, description: 'Préférence de langue de l\'utilisateur (true ou false)')
                ],
                required: ['langue']
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Langue mise à jour avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Langue mise à jour avec succès'),
                        new OA\Property(property: 'user', type: 'object', properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'username', type: 'string', example: 'jdupont'),
                            new OA\Property(property: 'langue', type: 'boolean', example: true),
                            new OA\Property(property: 'updatedAt', type: 'string', example: '2025-10-29 12:30:45')
                        ])
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Erreur de validation - le champ langue est requis.'),
            new OA\Response(response: 401, description: 'Non authentifié.')
        ]
    )]
    public function updateLangue(Request $request): Response
    {
        $user = $this->getUser();
        $this->accessChecker->checker($user, $this->isGranted('ROLE_USER'), 'UpdateLangue');

        if (!$user instanceof User) {
            return $this->json(['code' => 401, 'message' => 'Utilisateur non authentifié.'], 401);
        }

        $data = json_decode($request->getContent(), true);
        
        // Validation du champ langue
        if (!isset($data['langue'])) {
            return $this->json([
                'code' => 400, 
                'message' => 'Le champ langue est requis.'
            ], 400);
        }

        if (!is_bool($data['langue'])) {
            return $this->json([
                'code' => 400, 
                'message' => 'Le champ langue doit être un booléen (true ou false).'
            ], 400);
        }

        try {
            // Mise à jour uniquement du champ langue
            $user->setLangue($data['langue']);
            
            $updatedUser = $this->crudService->patchEntity($user, []);

            return $this->json([
                'message' => 'Langue mise à jour avec succès',
                'user' => [
                    'id' => $updatedUser->getId(),
                    'username' => $updatedUser->getUsername(),
                    'langue' => $updatedUser->getLangue(),
                    'updatedAt' => $updatedUser->getUpdatedAt()?->format('Y-m-d H:i:s'),
                ]
            ], 200);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}
