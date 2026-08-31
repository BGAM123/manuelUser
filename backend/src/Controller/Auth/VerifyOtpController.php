<?php

namespace App\Controller\Auth;

use App\Repository\UserRepository;
use App\Service\ApiResponseFactory;
use App\Service\OtpService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\RefreshTokenService;

#[Route('/auth')]
#[OA\Tag(name: 'Authentication')]
final class VerifyOtpController extends AbstractController
{
    #[Route('/verify-otp', name: 'app_verify_otp', methods: ['POST'])]
    #[OA\Post(
        path: '/auth/verify-otp',
        summary: 'Vérifier et valider un code OTP',
        description: 'Valide le code OTP envoyé par email et génère un token JWT si valide.'
    )]
    #[OA\RequestBody(
        description: 'Payload pour vérifier le code OTP',
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            required: ['email', 'otp'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'nengue382@gmail.com'),
                new OA\Property(property: 'otp', type: 'string', pattern: '^\d{6}$', example: '583921', description: 'Code OTP à 6 chiffres')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Authentification 2FA complétée, JWT généré',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'OTP vérifié avec succès.',
                'data' => [
                    'token' => 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...',
                    'refresh_token' => 'a1b2c3d4e5f6g7h8i9j0',
                    'langue' => 'fr'
                ]
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => null])
    )]
    #[OA\Response(
        response: 404,
        description: 'Not Found',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'La ressource demandée est introuvable.', 'data' => null])
    )]
    public function verifyOtp(
        Request $request,
        UserRepository $userRepository,
        OtpService $otpService,
        JWTTokenManagerInterface $jwtManager,
        RefreshTokenService $refreshTokenService,
        ApiResponseFactory $apiResponse,
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);

        // Valider le payload
        if (!is_array($payload)) {
            return $apiResponse->error('Invalid JSON payload.', Response::HTTP_BAD_REQUEST);
        }

        $email = $payload['email'] ?? null;
        $otp = $payload['otp'] ?? null;

        if (!$email || !$otp) {
            return $apiResponse->error('Email and OTP are required.', Response::HTTP_BAD_REQUEST);
        }

        // Rechercher l'utilisateur par email
        $user = $userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            return $apiResponse->error("L'utilisateur demandé est introuvable.", Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'utilisateur a la 2FA activée
        if (!$user->isTwoFactorEnabled()) {
            return $apiResponse->error(
                'Two-factor authentication is not enabled for this user.',
                Response::HTTP_BAD_REQUEST
            );
        }

        // Valider le code OTP
        if (!$otpService->validateOtp($user, $otp)) {
            // Déterminer si le code est expiré ou simplement incorrect
            if ($user->getOtpExpiresAt() && $user->getOtpExpiresAt() < new \DateTime()) {
                return $apiResponse->error('Code OTP expiré.', Response::HTTP_BAD_REQUEST);
            }

            return $apiResponse->error('Code OTP invalide.', Response::HTTP_BAD_REQUEST);
        }

        // Supprimer le code OTP après validation réussie
        $otpService->clearOtp($user);

        // Générer le JWT
        $jwt = $jwtManager->create($user);
        $refreshToken = $refreshTokenService->createRefreshToken($user);

        return $apiResponse->success(
            [
                'token' => $jwt,
                'refresh_token' => $refreshToken->getRefreshToken(),
                'langue' => $user->getLangue(),
            ],
            Response::HTTP_OK,
            'OTP vérifié avec succès.'
        );
    }
}
