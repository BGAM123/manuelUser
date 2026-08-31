<?php

namespace App\Controller\Inputs;

use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use App\Service\ApiResponseFactory;
use App\Service\InputService;
use App\Service\InputResponseBuilder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/inputs')]
#[OA\Tag(name: 'Inputs')]
final class UpdateInputController extends AbstractController
{
    #[Route('/{id}', name: 'app_input_update', methods: ['PUT'])]
    #[OA\Put(
        path: '/inputs/{id}',
        summary: 'Modifier une valeur de champ',
        description: 'Modifie une valeur de champ existante.'
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
        description: 'ID de la valeur',
        example: 1
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'valeur', type: 'string', example: 'Végétalisé', description: 'Nouvelle valeur'),
                // ❌ SUPPRIMER ordre
                // new OA\Property(property: 'ordre', type: 'integer', nullable: true, example: 3),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Valeur modifiée avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Valeur modifiée avec succès.',
                'data' => [
                    'id' => 1,
                    'valeur' => 'Végétalisé',
                    'champ' => [
                        'id' => 1,
                        'nom' => 'Type de toit',
                    ],
                    'createdAt' => '2026-08-13 10:00:00',
                    'updatedAt' => '2026-08-13 11:00:00',
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
                'data' => ['valeur' => 'La valeur est obligatoire.']
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Valeur introuvable',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 404,
                'message' => 'La valeur demandée n\'existe pas.',
                'data' => null
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
        int $id,
        Request $request,
        InputService $inputService,
        InputResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload)) {
            return $apiResponse->error('Données invalides.', Response::HTTP_BAD_REQUEST);
        }

        try {
            // ❌ SUPPRIMER le paramètre ordre
            $input = $inputService->update(
                $id,
                $payload['valeur'] ?? null
            );
        } catch (ResourceNotFoundException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (ValidationFailedException $e) {
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $e->getErrors());
        }

        return $apiResponse->success(
            $responseBuilder->buildDetail($input),
            Response::HTTP_OK,
            'Valeur modifiée avec succès.'
        );
    }
}