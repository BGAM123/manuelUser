<?php

namespace App\Controller\SecurityModes;

use App\Exception\ResourceNotFoundException;
use App\Service\ApiResponseFactory;
use App\Service\SecurityModeService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/security-modes')]
#[OA\Tag(name: 'SecurityModes')]
final class DeleteSecurityModeController extends AbstractController
{
    #[Route('/{id}', name: 'app_security_mode_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/security-modes/{id}',
        summary: 'Supprimer un mode de sécurisation',
        description: 'Supprime logiquement un mode de sécurisation. Les sécurisations existantes ne sont pas affectées.'
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
        description: 'ID du mode de sécurisation à supprimer',
        example: 1
    )]
    #[OA\Response(
        response: 200,
        description: 'Mode de sécurisation supprimé avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Mode de sécurisation supprimé avec succès.',
                'data' => null
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Mode de sécurisation introuvable',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 404,
                'message' => 'Le mode de sécurisation demandé n\'existe pas.',
                'data' => null
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Non authentifié'
    )]
    #[OA\Response(
        response: 500,
        description: 'Erreur serveur'
    )]
    public function __invoke(
        int $id,
        SecurityModeService $securityModeService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        try {
            $securityModeService->delete($id);
        } catch (ResourceNotFoundException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_NOT_FOUND);
        }

        return $apiResponse->success(
            null,
            Response::HTTP_OK,
            'Mode de sécurisation supprimé avec succès.'
        );
    }
}