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
final class CreateInputController extends AbstractController
{
    #[Route('', name: 'app_input_create', methods: ['POST'])]
    #[OA\Post(
        path: '/inputs',
        summary: 'Créer une valeur de champ',
        description: 'Crée une nouvelle valeur pour un champ.'
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'champ_id', type: 'integer', example: 1, description: 'ID du champ'),
                new OA\Property(property: 'valeur', type: 'string', example: 'Plat', description: 'Valeur du champ'),
                // ❌ SUPPRIMER ordre
                // new OA\Property(property: 'ordre', type: 'integer', nullable: true, example: 1, description: 'Ordre d\'affichage'),
            ],
            required: ['champ_id', 'valeur']
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Valeur créée avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 201,
                'message' => 'Valeur créée avec succès.',
                'data' => [
                    'id' => 1,
                    'valeur' => 'Plat',
                    'champ' => [
                        'id' => 1,
                        'nom' => 'Type de toit',
                    ],
                    'createdAt' => '2026-08-13 10:00:00',
                    'updatedAt' => '2026-08-13 10:00:00',
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
                'data' => ['champ_id' => 'Le champ spécifié n\'existe pas.']
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Champ introuvable',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 404,
                'message' => 'Le champ spécifié n\'existe pas.',
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
        Request $request,
        InputService $inputService,
        InputResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload)) {
            return $apiResponse->error('Données invalides.', Response::HTTP_BAD_REQUEST);
        }

        if (empty($payload['champ_id'])) {
            return $apiResponse->error('Le champ est obligatoire.', Response::HTTP_BAD_REQUEST);
        }

        if (empty($payload['valeur'])) {
            return $apiResponse->error('La valeur est obligatoire.', Response::HTTP_BAD_REQUEST);
        }

        try {
            // ❌ SUPPRIMER le paramètre ordre
            $input = $inputService->create(
                $payload['champ_id'],
                $payload['valeur']
            );
        } catch (ResourceNotFoundException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (ValidationFailedException $e) {
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $e->getErrors());
        }

        return $apiResponse->success(
            $responseBuilder->buildDetail($input),
            Response::HTTP_CREATED,
            'Valeur créée avec succès.'
        );
    }
}