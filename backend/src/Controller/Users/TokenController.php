<?php

namespace App\Controller\Users;

use App\Service\ApiResponseFactory;
use App\Service\RefreshTokenService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/refresh_token')]
#[OA\Tag(name: 'Authentication')]
final class TokenController extends AbstractController
{
    #[Route('', name: 'app_refresh_token', methods: ['POST'])]
    #[OA\Post(
        path: '/refresh_token',
        summary: 'Refresh JWT token',
        description: 'Génère un nouveau token JWT à partir d un refresh token valide.'
    )]
    #[OA\RequestBody(
        description: 'Refresh token payload',
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            required: ['refresh_token'],
            properties: [
                new OA\Property(property: 'refresh_token', type: 'string', example: 'a1b2c3d4...')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - Nouveau token JWT généré avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Token refreshed successfully.'),
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'token', type: 'string', example: 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...'),
                    new OA\Property(property: 'refresh_token', type: 'string', example: 'a1b2c3d4...'),
                    new OA\Property(property: 'langue', type: 'string', example: 'fr', description: 'Langue de l\'utilisateur (fr, en, es, de, it)')
                ])
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Bad Request', content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => null]))]
    #[OA\Response(response: 401, description: 'Unauthorized - Refresh token invalide ou expiré')]
    public function refreshToken(
        Request $request,
        RefreshTokenService $refreshTokenService,
        JWTTokenManagerInterface $jwtManager,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload) || empty($payload['refresh_token'])) {
            return $apiResponse->error('refresh_token is required.', Response::HTTP_BAD_REQUEST);
        }

        $refreshTokenString = (string) $payload['refresh_token'];
        $refreshToken = $refreshTokenService->getValidRefreshToken($refreshTokenString);
        if (!$refreshToken) {
            return $apiResponse->error('Invalid or expired refresh token.', Response::HTTP_UNAUTHORIZED);
        }

        $newRefreshToken = $refreshTokenService->rotateRefreshToken($refreshToken);
        $jwt = $jwtManager->create($newRefreshToken->getUser());

        return $apiResponse->success(
            [
                'token' => $jwt,
                'refresh_token' => $newRefreshToken->getRefreshToken(),
                'langue' => $newRefreshToken->getUser()->getLangue(),
            ],
            Response::HTTP_OK,
            'Token refreshed successfully.'
        );
    }
}
