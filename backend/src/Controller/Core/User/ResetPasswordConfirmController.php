<?php

namespace App\Controller\Core\User;

use App\Repository\Core\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[OA\Tag(name: "User")]
class ResetPasswordConfirmController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $em,
    ) {}

    #[Route('/core/user/reset-password-confirm', name: 'app_core_user_reset_password_confirm', methods: ['POST'])]
    #[OA\Post(
        path: '/core/user/reset-password-confirm',
        summary: 'Confirmer la réinitialisation du mot de passe',
        description: 'Utilise le token reçu par email pour réinitialiser le mot de passe.',
        tags: ['User'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['token', 'newPassword'],
                properties: [
                    new OA\Property(property: 'token', type: 'string', example: 'a1b2c3d4e5f6...', description: 'Token reçu par email'),
                    new OA\Property(property: 'newPassword', type: 'string', example: 'NewSecurePass123!', description: 'Nouveau mot de passe'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Mot de passe réinitialisé avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Mot de passe réinitialisé avec succès.')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Token invalide, expiré ou nouveau mot de passe manquant.'),
        ]
    )]
    public function confirmReset(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['token']) || empty($data['newPassword'])) {
            return $this->json(['code' => 400, 'message' => 'Le token et le nouveau mot de passe sont requis.'], 400);
        }

        $user = $this->userRepository->findOneBy(['resetToken' => $data['token']]);

        if (!$user) {
            return $this->json(['code' => 400, 'message' => 'Token invalide.'], 400);
        }

        // ðŸ” VÃ©rifier si le token a expirÃ©
        $now = new \DateTime();
        if ($user->getResetTokenExpiresAt() === null || $user->getResetTokenExpiresAt() < $now) {
            return $this->json(['code' => 400, 'message' => 'Le token a expiré.'], 400);
        }

        try {
            // ðŸ”‘ Hash du nouveau mot de passe
            $hashedPassword = $this->passwordHasher->hashPassword($user, $data['newPassword']);
            $user->setPassword($hashedPassword);

            // ðŸ—‘ï¸ Supprimer le token aprÃ¨s utilisation
            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);

            // ðŸ”¹ DÃ©sactiver isFirstLogin si activÃ©
            if ($user->isFirstLogin()) {
                $user->setFirstLogin(false);
            }

            $this->em->flush();

            return $this->json(['code' => 200, 'message' => 'Mot de passe réinitialisé avec succès.'], 200);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}