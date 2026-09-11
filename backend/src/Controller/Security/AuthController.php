<?php

namespace App\Controller\Security;

use OpenApi\Attributes as OA;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

#[OA\Tag(name: "Login Check")]
class AuthController
{
    #[Route('/login', name: 'api_login', methods: ['POST'])]
    #[OA\Post(
        path: "/login",
        summary: "Creates a user token.",
        tags: ['Login Check'],
        description: "Creates a user token.",
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "username", type: "string", example: 'test1'),
                    new OA\Property(property: "password", type: "string", example: '1234')
                ],
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "JWT token returned.",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "token", type: "string", example: "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."),
                        new OA\Property(property: "id", type: "integer", example: 1),
                        new OA\Property(property: "username", type: "string", example: "test1"),
                        new OA\Property(property: "email", type: "string", example: "test@example.com"),
                        new OA\Property(property: "lastName", type: "string", example: "Doe"),
                        new OA\Property(property: "langue", type: "boolean", example: false),
                        new OA\Property(
                            property: "user",
                            type: "object",
                            properties: [
                                new OA\Property(property: "username", type: "string", example: "test1"),
                                new OA\Property(property: "roles", type: "array", items: new OA\Items(type: "string"), example: ["ROLE_ADMIN"]),
                                new OA\Property(property: "idService", type: "integer", example: 1, nullable: true)
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: "Invalid credentials."
            )
        ]
    )]
    public function login(): JsonResponse
    {
        // Simulation pour Swagger uniquement
        return new JsonResponse([
            'token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...',
            'id' => 1,
            'username' => 'test1',
            'email' => 'test@example.com',
            'lastName' => 'Doe',
            'langue' => false,
            'user' => [
                'username' => 'test1',
                'roles' => ['ROLE_ADMIN'],
                'idService' => 1
            ]
        ]);
    }
}
