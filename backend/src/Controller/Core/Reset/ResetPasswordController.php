<?php

namespace App\Controller\Core\Reset;

use OpenApi\Attributes as OA;
use App\Service\Core\FunctionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Reset")]
class ResetPasswordController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private FunctionService $functionService,
        private JWTTokenManagerInterface $jwtManager,
    ) {
    }

    #[Route('/core/reset-password', name: 'reset_password', methods: ['POST'])]
    #[OA\Post(
        path: '/core/reset-password',
        summary: 'Reset password with JWT token.',
        tags: ['Reset'],
        description: "Reset the user's password using the JWT token received after OTP verification. The token must be passed in the Authorization header as Bearer token.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'password', type: 'string', example: 'NewSecurePassword123!'),
                    new OA\Property(property: 'confirmPassword', type: 'string', example: 'NewSecurePassword123!')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Password reset successfully.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Password reset successfully.'),
                        new OA\Property(property: 'token', type: 'string', example: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...'),
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'username', type: 'string', example: 'john_doe'),
                        new OA\Property(property: 'email', type: 'string', example: 'john@example.com'),
                        new OA\Property(property: 'lastName', type: 'string', example: 'Doe'),
                        new OA\Property(property: 'langue', type: 'boolean', example: false),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Error 400: Invalid request or passwords do not match.'),
            new OA\Response(response: 401, description: 'Error 401: Unauthorized or invalid token.')
        ]
    )]
    public function resetPassword(Request $request): JsonResponse
    {
        // RÃ©cupÃ©rer l'utilisateur authentifiÃ© via le token JWT
        /** @var \App\Entity\Core\User|null $user */
        $user = $this->getUser();

        // VÃ©rifier si l'utilisateur est authentifiÃ©
        if (!$user) {
            return $this->json([
                'code' => 401, 
                'message' => 'Unauthorized. Invalid or missing token.'
            ], 401);
        }

        // RÃ©cupÃ©rer les donnÃ©es
        $data = json_decode($request->getContent(), true);

        // VÃ©rification si les champs sont prÃ©sents
        $this->functionService->entityValide($data, ['password', 'confirmPassword']);

        // VÃ©rifier si les donnÃ©es des champs sont valides
        $this->functionService->validate($data);

        // Autoriser uniquement certains champs modifiables
        $data = $this->functionService->allowFields($data, ['password', 'confirmPassword']);

        // VÃ©rifier que les mots de passe correspondent
        if ($data['password'] !== $data['confirmPassword']) {
            return $this->json([
                'code' => 400, 
                'message' => 'Password and confirmation password do not match.'
            ], 400);
        }

        // VÃ©rifier la longueur minimale du mot de passe (optionnel)
        if (strlen($data['password']) < 6) {
            return $this->json([
                'code' => 400, 
                'message' => 'Password must be at least 6 characters long.'
            ], 400);
        }

        try {
            // Hasher le nouveau mot de passe
            $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
            $user->setPassword($hashedPassword);

            // Nettoyer les tokens de rÃ©initialisation
            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);

            // DÃ©sactiver isFirstLogin si activÃ©
            if ($user->isFirstLogin()) {
                $user->setFirstLogin(false);
            }

            $this->em->flush();

            // GÃ©nÃ©rer un nouveau token JWT pour connecter automatiquement l'utilisateur
            $token = $this->jwtManager->create($user);

            return $this->json([
                'code' => 200, 
                'message' => 'Password reset successfully.',
                'token' => $token,
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'lastName' => $user->getLastName(),
                'langue' => $user->getLangue(),
            ], 200);

        } catch (\Exception $e) {
            return $this->json([
                'code' => 500, 
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

}
