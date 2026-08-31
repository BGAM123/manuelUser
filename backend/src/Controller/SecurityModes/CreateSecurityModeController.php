<?php

namespace App\Controller\SecurityModes;

use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use App\Service\ApiResponseFactory;
use App\Service\SecurityModeResponseBuilder;
use App\Service\SecurityModeService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/security-modes')]
#[OA\Tag(name: 'SecurityModes')]
final class CreateSecurityModeController extends AbstractController
{
    #[Route('', name: 'app_security_mode_create', methods: ['POST'])]
    #[OA\Post(
        path: '/security-modes',
        summary: 'Créer un mode de sécurisation',
        description: 'Crée un nouveau mode de sécurisation (ex: Physique, Électronique, Biométrique, etc.).'
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'nom', type: 'string', example: 'Physique', description: 'Nom du mode de sécurisation'),
                new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Sécurisation par barrières physiques', description: 'Description du mode'),
            ],
            required: ['nom']
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Mode de sécurisation créé avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 201,
                'message' => 'Mode de sécurisation créé avec succès.',
                'data' => [
                    'id' => 1,
                    'nom' => 'Physique',
                    'description' => 'Sécurisation par barrières physiques',
                    'createdAt' => '2026-08-12 10:00:00',
                    'updatedAt' => '2026-08-12 10:00:00',
                ]
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Validation échouée',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 400,
                'message' => 'La validation a échoué.',
                'data' => ['nom' => 'Un mode de sécurisation portant ce nom existe déjà.']
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Non authentifié',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 401,
                'message' => 'Authentification requise.',
                'data' => null
            ]
        )
    )]
    #[OA\Response(
        response: 500,
        description: 'Erreur serveur',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 500,
                'message' => 'Une erreur interne du serveur est survenue.',
                'data' => null
            ]
        )
    )]
    public function __invoke(
        Request $request,
        SecurityModeService $securityModeService,
        SecurityModeResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload) || empty($payload['nom'])) {
            return $apiResponse->error('Le nom du mode de sécurisation est obligatoire.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $securityMode = $securityModeService->create(
                $payload['nom'],
                $payload['description'] ?? null
            );
        } catch (ValidationFailedException $e) {
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $e->getErrors());
        } catch (\Exception $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return $apiResponse->success(
            $responseBuilder->buildDetail($securityMode),
            Response::HTTP_CREATED,
            'Mode de sécurisation créé avec succès.'
        );
    }
}