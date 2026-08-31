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
final class UpdateSecurityModeController extends AbstractController
{
    #[Route('/{id}', name: 'app_security_mode_update', methods: ['PUT'])]
    #[OA\Put(
        path: '/security-modes/{id}',
        summary: 'Modifier un mode de sécurisation',
        description: 'Modifie un mode de sécurisation existant. Tous les champs sont optionnels.'
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
        description: 'ID du mode de sécurisation',
        example: 1
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'nom', type: 'string', example: 'Physique renforcée', description: 'Nouveau nom du mode'),
                new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Sécurisation par barrières physiques renforcées', description: 'Nouvelle description'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Mode de sécurisation modifié avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Mode de sécurisation modifié avec succès.',
                'data' => [
                    'id' => 1,
                    'nom' => 'Physique renforcée',
                    'description' => 'Sécurisation par barrières physiques renforcées',
                    'createdAt' => '2026-08-12 10:00:00',
                    'updatedAt' => '2026-08-12 11:00:00',
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
        Request $request,
        SecurityModeService $securityModeService,
        SecurityModeResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload)) {
            return $apiResponse->error('Données invalides.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $securityMode = $securityModeService->update(
                $id,
                $payload['nom'] ?? null,
                $payload['description'] ?? null
            );
        } catch (ResourceNotFoundException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (ValidationFailedException $e) {
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $e->getErrors());
        } catch (\Exception $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return $apiResponse->success(
            $responseBuilder->buildDetail($securityMode),
            Response::HTTP_OK,
            'Mode de sécurisation modifié avec succès.'
        );
    }
}