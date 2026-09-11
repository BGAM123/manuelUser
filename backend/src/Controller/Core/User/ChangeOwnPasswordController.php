<?php

namespace App\Controller\Core\User;

use App\Service\Core\AccessCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[OA\Tag(name: "User")]
class ChangeOwnPasswordController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $em,
    ) {}

    #[Route('/core/user/change-own-password', name: 'app_core_user_change_own_password', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/user/change-own-password',
        summary: 'Changer son propre mot de passe',
        description: 'Permet à l\'utilisateur connecté de changer son propre mot de passe en fournissant l\'ancien mot de passe et en confirmant le nouveau.',
        tags: ['User'],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['oldPassword', 'newPassword', 'confirmPassword'],
                properties: [
                    new OA\Property(property: 'oldPassword', type: 'string', example: 'OldPassword123!', description: 'Ancien mot de passe'),
                    new OA\Property(property: 'newPassword', type: 'string', example: 'NewSecurePass123!', description: 'Nouveau mot de passe'),
                    new OA\Property(property: 'confirmPassword', type: 'string', example: 'NewSecurePass123!', description: 'Confirmation du nouveau mot de passe'),
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
            new OA\Response(
                response: 400,
                description: 'Erreur de validation.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'L\'ancien mot de passe est incorrect.')
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié.')
        ]
    )]
    public function changeOwnPassword(Request $request): Response
    {
        /** @var \App\Entity\Core\User|null $user */
        $user = $this->getUser();
        $this->accessChecker->checker($user, $this->isGranted('ROLE_USER'), 'ChangeOwnPassword');

        if (!$user) {
            return $this->json(['code' => 401, 'message' => 'Utilisateur non authentifié.'], 401);
        }

        $data = json_decode($request->getContent(), true);

        // ðŸ”¹ Validation des champs requis
        if (empty($data['oldPassword'])) {
            return $this->json(['code' => 400, 'message' => 'L\'ancien mot de passe est requis.'], 400);
        }

        if (empty($data['newPassword'])) {
            return $this->json(['code' => 400, 'message' => 'Le nouveau mot de passe est requis.'], 400);
        }

        if (empty($data['confirmPassword'])) {
            return $this->json(['code' => 400, 'message' => 'La confirmation du mot de passe est requise.'], 400);
        }

        // ðŸ”¹ VÃ©rifier que l'ancien mot de passe est correct
        if (!$this->passwordHasher->isPasswordValid($user, $data['oldPassword'])) {
            return $this->json(['code' => 400, 'message' => 'L\'ancien mot de passe est incorrect.'], 400);
        }

        // ðŸ”¹ VÃ©rifier que le nouveau mot de passe et la confirmation correspondent
        if ($data['newPassword'] !== $data['confirmPassword']) {
            return $this->json(['code' => 400, 'message' => 'Le nouveau mot de passe et la confirmation ne correspondent pas.'], 400);
        }

        // ðŸ”¹ VÃ©rifier que le nouveau mot de passe est diffÃ©rent de l'ancien
        if ($this->passwordHasher->isPasswordValid($user, $data['newPassword'])) {
            return $this->json(['code' => 400, 'message' => 'Le nouveau mot de passe doit être différent de l\'ancien.'], 400);
        }

        try {
            // ðŸ”‘ Hash et mise Ã  jour du mot de passe
            $hashedPassword = $this->passwordHasher->hashPassword($user, $data['newPassword']);
            $user->setPassword($hashedPassword);

            // ðŸ”¹ DÃ©sactiver isFirstLogin si activÃ©
            if ($user->isFirstLogin()) {
                $user->setFirstLogin(false);
            }

            $this->em->flush();

            return $this->json(['code' => 200, 'message' => 'Mot de passe changé avec succès.'], 200);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}
