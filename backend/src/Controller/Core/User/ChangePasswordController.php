<?php

namespace App\Controller\Core\User;

use App\Repository\Core\UserRepository;
use App\Service\Core\AccessCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[OA\Tag(name: "User")]
class ChangePasswordController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private AccessCheckerService $accessChecker,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $em,
    ) {}

    #[Route('/core/user/change-password/{id<([1-9][0-9]*)>}', name: 'app_core_user_change_password', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/user/change-password/{id}',
        summary: 'Changer le mot de passe d\'un utilisateur',
        description: 'Permet de changer le mot de passe de n\'importe quel utilisateur sans nÃ©cessiter l\'ancien mot de passe.',
        tags: ['User'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant de l\'utilisateur',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['newPassword'],
                properties: [
                    new OA\Property(property: 'newPassword', type: 'string', example: 'NewSecurePass123!', description: 'Nouveau mot de passe'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Mot de passe changé avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Mot de passe changé avec succès.')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Nouveau mot de passe invalide.'),
            new OA\Response(response: 404, description: 'Utilisateur non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function changePassword(Request $request, int $id): Response
    {
        $currentUser = $this->getUser();
        $this->accessChecker->checker($currentUser, $this->isGranted('ROLE_USER'), 'ChangePassword');

        $targetUser = $this->userRepository->find($id);

        if (!$targetUser) {
            return $this->json(['code' => 404, 'message' => 'Utilisateur non trouvé.'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (empty($data['newPassword'])) {
            return $this->json(['code' => 400, 'message' => 'Le nouveau mot de passe est requis.'], 400);
        }

        try {
            // ðŸ”‘ Hash et mise Ã  jour
            $hashedPassword = $this->passwordHasher->hashPassword($targetUser, $data['newPassword']);
            $targetUser->setPassword($hashedPassword);

            // ðŸ”¹ DÃ©sactiver isFirstLogin si activÃ©
            if ($targetUser->isFirstLogin()) {
                $targetUser->setFirstLogin(false);
            }

            $this->em->flush();

            return $this->json(['code' => 200, 'message' => 'Mot de passe changé avec succès.'], 200);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}