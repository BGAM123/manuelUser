<?php

namespace App\Controller\Core\Auth;

use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: "Refresh Token")]
class RefreshTokenController extends AbstractController
{
    /**
     * RafraÃ®chir le token JWT
     * 
     * FLUX D'AUTHENTIFICATION :
     * 1. L'utilisateur se connecte via POST /login (username + password)
     * 2. Le systÃ¨me retourne un JWT token (valide 2 minutes) + un refresh_token (valide 3 minutes)
     * 3. L'utilisateur utilise le JWT token pour accÃ©der aux ressources protÃ©gÃ©es
     * 4. Quand le JWT expire (aprÃ¨s 2 minutes), l'utilisateur a 1 minute pour appeler cette API
     * 5. Un nouveau JWT token est gÃ©nÃ©rÃ© (valide 2 minutes) + un nouveau refresh_token (valide 3 minutes)
     * 6. L'utilisateur continue d'utiliser le nouveau token sans avoir Ã  se reconnecter
     * 
     * STRATÃ‰GIE DE SESSION ACTIVE :
     * - JWT valide : 2 minutes
     * - Refresh Token valide : 3 minutes
     * - FenÃªtre de grÃ¢ce : 1 minute aprÃ¨s expiration du JWT
     * - Si l'utilisateur n'appelle pas refresh dans les 3 minutes : DÃ‰CONNEXION
     * 
     * RECOMMANDATION FRONTEND :
     * Appeler automatiquement cette API toutes les 1 min 50 sec pour maintenir la session active.
     */
    #[Route('/api/token/refresh', name: 'app_auth_refresh_token', methods: ['POST'])]
    #[OA\Post(
        path: '/api/token/refresh',
        summary: 'Rafraichir le token JWT expiré',
        description: 'Renouvelle automatiquement le token JWT sans redemander les identifiants. à utiliser quand le token expire (aprÃ¨s 2 minutes).',
        tags: ['Refresh Token'],
        requestBody: new OA\RequestBody(
            required: true,
            description: '🔑 Le refresh_token obtenu lors de la connexion initiale (POST /login)',
            content: new OA\JsonContent(
                required: ['refresh_token'],
                properties: [
                    new OA\Property(
                        property: 'refresh_token',
                        type: 'string',
                        description: 'Refresh token reçu lors du login (valide 30 jours)',
                        example: 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1f2'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Token rafraîchi avec succès - Nouveau JWT généré',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'token',
                            type: 'string',
                            description: '🔑 Nouveau token JWT (valide 2 minutes)',
                            example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJpYXQiOjE3MzE5MjY0MDAsImV4cCI6MTczMTkyNjUyMCwicm9sZXMiOlsiUk9MRV9VU0VSIl0sInVzZXJuYW1lIjoidGVzdDEifQ.signature'
                        ),
                        new OA\Property(
                            property: 'refresh_token',
                            type: 'string',
                            description: '🔑 Nouveau refresh token (valide 30 jours)',
                            example: 'x9y8z7w6v5u4t3s2r1q0p9o8n7m6l5k4j3i2h1g0f9e8d7c6b5a4z3y2x1w0v9u8'
                        )
                    ],
                    example: [
                        'token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJpYXQiOjE3MzE5MjY0MDAsImV4cCI6MTczMTkyNjUyMCwicm9sZXMiOlsiUk9MRV9VU0VSIl0sInVzZXJuYW1lIjoidGVzdDEifQ.signature',
                        'refresh_token' => 'x9y8z7w6v5u4t3s2r1q0p9o8n7m6l5k4j3i2h1g0f9e8d7c6b5a4z3y2x1w0v9u8'
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Refresh token invalide, expiré ou révoqué',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'code',
                            type: 'integer',
                            example: 401
                        ),
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'An authentication exception occurred.'
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Paramètre refresh_token manquant dans la requête',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'code',
                            type: 'integer',
                            example: 400
                        ),
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Parameter "refresh_token" is missing'
                        )
                    ]
                )
            )
        ]
    )]
    public function refresh(Request $request): JsonResponse
    {
        // â„¹ï¸ Cette mÃ©thode ne sera jamais exÃ©cutÃ©e en production.
        // Le bundle GesdinetJWTRefreshTokenBundle intercepte automatiquement
        // les requÃªtes vers /core/token/refresh et gÃ¨re le rafraÃ®chissement du token.
        // 
        // Ce contrÃ´leur existe uniquement pour documenter l'API dans Swagger UI.
        // 
        // FLUX TECHNIQUE :
        // 1. Symfony intercepte la route /api/token/refresh (voir config/packages/security.yaml)
        // 2. Le firewall "refresh" traite la requÃªte
        // 3. GesdinetJWTRefreshTokenBundle valide le refresh_token
        // 4. Un nouveau JWT + refresh_token sont gÃ©nÃ©rÃ©s et retournÃ©s
        
        return new JsonResponse([
            'message' => 'Endpoint géré automatiquement par GesdinetJWTRefreshTokenBundle',
            'info' => 'Ce contrôleur sert uniquement à la documentation Swagger'
        ], Response::HTTP_OK);
    }
}
