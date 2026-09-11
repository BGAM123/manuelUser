<?php

namespace App\Controller\Core;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "KIAMA360 API Documentation",
    description: "Documentation complète de l'API KIAMA360 incluant tous les modules (Core, Security, Reset, etc.)"
)]
#[OA\Server(
    url: "/",
)]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT",
    description: "Enter your JWT token for authentication"
)]
#[OA\Tag(
    name: "Core",
    description: "Opérations du module Core"
)]
#[OA\Tag(
    name: "Security",
    description: "Authentification et gestion des tokens"
)]
#[OA\Tag(
    name: "Reset",
    description: "Réinitialisation de mot de passe"
)]
class CoreDocumentation
{
    // Classe vide servant uniquement pour les annotations OpenAPI globales
}
