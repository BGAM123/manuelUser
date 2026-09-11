<?php

namespace App\Controller\Core\User;

use App\Repository\Core\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "User")]
class ResetPasswordRequestController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
    ) {}

    #[Route('/core/user/reset-password-request', name: 'app_core_user_reset_password_request', methods: ['POST'])]
    #[OA\Post(
        path: '/core/user/reset-password-request',
        summary: 'Demander une réinitialisation de mot de passe',
        description: 'Génère un token de réinitialisation pour un utilisateur et l\'envoie par email (à implémenter).',
        tags: ['User'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@minepia.cm', description: 'Email de l\'utilisateur'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Email de réinitialisation envoyé (si l\'email existe).',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Si cet email existe, un lien de réinitialisation a été envoyé.')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Email invalide ou manquant.')
        ]
    )]
    public function requestReset(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['email'])) {
            return $this->json(['code' => 400, 'message' => 'L\'email est requis.'], 400);
        }

        $user = $this->userRepository->findOneBy(['email' => $data['email']]);

        // ðŸ” Pour des raisons de sÃ©curitÃ©, on ne rÃ©vÃ¨le jamais si l'email existe ou non
        if (!$user) {
            return $this->json([
                'code' => 200,
                'message' => 'Si cet email existe, un lien de réinitialisation a été envoyé.'
            ], 200);
        }

        try {
            // ðŸ”‘ Génération du token (valide 1 heure)
            $resetToken = bin2hex(random_bytes(32));
            $expiresAt = new \DateTime('+1 hour');

            $user->setResetToken($resetToken);
            $user->setResetTokenExpiresAt($expiresAt);

            $this->em->flush();

            // ðŸ“§ TODO: Envoyer l'email avec le token
            // $resetLink = "https://votre-domaine.com/reset-password?token=$resetToken";
            // $this->mailer->send(...);

            return $this->json([
                'code' => 200,
                'message' => 'Si cet email existe, un lien de réinitialisation a été envoyé.',
                // ðŸ”¹ En dev seulement (à retirer en prod)
                'debug_token' => $this->getParameter('kernel.environment') === 'dev' ? $resetToken : null,
            ], 200);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => 'Erreur lors de la génération du token.'], 500);
        }
    }
}