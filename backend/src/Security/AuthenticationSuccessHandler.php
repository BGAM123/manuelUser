<?php

namespace App\Security;

use App\Service\ApiResponseFactory;
use App\Service\OtpService;
use App\Service\RefreshTokenService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

final class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly RefreshTokenService $refreshTokenService,
        private readonly ApiResponseFactory $apiResponse,
        private readonly OtpService $otpService
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): JsonResponse
    {
        $user = $token->getUser();

        // Vérifications critiques : l'utilisateur doit être actif ET non supprimé
        if (!$user->isActive() || $user->isDelete()) {
            return $this->apiResponse->error(
                'Votre compte est désactivé ou supprimé',
                JsonResponse::HTTP_FORBIDDEN,
                null
            );
        }

        // Cas 1: L'utilisateur n'a pas la double authentification activée
        if (!$user->isTwoFactorEnabled()) {
            $jwt = $this->jwtManager->create($user);
            $refreshToken = $this->refreshTokenService->createRefreshToken($user);

            return $this->apiResponse->success(
                [
                    'token' => $jwt,
                    'refresh_token' => $refreshToken->getRefreshToken(),
                    'langue' => $user->getLangue(),
                ],
                JsonResponse::HTTP_OK,
                'Authentication successful.'
            );
        }

        // Cas 2: L'utilisateur a la double authentification activée
        // Générer et envoyer un code OTP
        $this->otpService->generateAndSendOtp($user);

        return $this->apiResponse->success(
            [
                'requires_otp' => true,
            ],
            JsonResponse::HTTP_OK,
            'Code OTP envoyé avec succès.'
        );
    }
}
