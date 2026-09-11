<?php

namespace App\Controller\Core\User;

use App\Service\Core\CrudService;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "User")]
class PatchProfileController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/user/profile', name: 'app_core_user_patch_profile', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/user/profile',
        summary: 'Modifier le profil de l\'utilisateur connecté',
        description: 'Permet à l\'utilisateur de modifier son propre profil (champs limités).',
        tags: ['User'],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'username', type: 'string', example: 'jdupont'),
                    new OA\Property(property: 'firstName', type: 'string', example: 'Jean'),
                    new OA\Property(property: 'lastName', type: 'string', example: 'Dupont'),
                    new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@minepia.cm'),
                    new OA\Property(property: 'phone', type: 'string', example: '237690123456'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profil mis Ã  jour avec succÃ¨s.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Profil mis à jour avec succès'),
                        new OA\Property(property: 'user', type: 'object', properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'username', type: 'string', example: 'jdupont'),
                            new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@minepia.cm'),
                            new OA\Property(property: 'fullName', type: 'string', example: 'Jean Dupont'),
                            new OA\Property(property: 'phone', type: 'string', example: '237690123456'),
                        ])
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Erreur de validation.'),
            new OA\Response(response: 401, description: 'Non authentifié.')
        ]
    )]
    public function updateProfile(Request $request): Response
    {
        /** @var \App\Entity\Core\User|null $user */
        $user = $this->getUser();
        $this->accessChecker->checker($user, $this->isGranted('ROLE_USER'), 'PatchProfile');

        if (!$user) {
            return $this->json(['code' => 401, 'message' => 'Utilisateur non authentifié.'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $this->functionService->validate($data);

        // ðŸ”’ Autoriser uniquement certains champs (sÃ©curitÃ©)
        $allowedFields = ['username', 'firstName', 'lastName', 'email', 'phone'];
        $data = array_intersect_key($data, array_flip($allowedFields));

        // ðŸ”¹ Exclure les champs systÃ¨me
        $data = $this->functionService->excludeFields($data, ['createdAt', 'updatedAt', 'password', 'isActive', 'isVerified', 'isFirstLogin', 'idService', 'idRole']);

        try {
            $updatedUser = $this->crudService->patchEntity($user, $data);

            return $this->json([
                'message' => 'Profil mis à jour avec succès',
                'user' => [
                    'id' => $updatedUser->getId(),
                    'username' => $updatedUser->getUsername(),
                    'email' => $updatedUser->getEmail(),
                    'fullName' => $updatedUser->getFullName(),
                    'phone' => $updatedUser->getPhone(),
                    'updatedAt' => $updatedUser->getUpdatedAt()?->format('Y-m-d H:i:s'),
                ]
            ], 200);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}