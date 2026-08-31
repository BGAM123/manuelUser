<?php

namespace App\Controller\ExitTypes;

use App\Entity\ExitType;
use App\Exception\ValidationFailedException;
use App\Service\ApiResponseFactory;
use App\Service\ExitTypeResponseBuilder;
use App\Service\ExitTypeService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/exit-types')]
#[OA\Tag(name: 'Exit Types')]
final class CreateExitTypeController extends AbstractController
{
    #[Route('', name: 'app_exit_type_create', methods: ['POST'])]
    #[OA\Post(
        path: '/exit-types',
        summary: 'Créer un type de sortie',
        description: 'Crée un nouveau type de sortie.'
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'nom', type: 'string', example: 'Réforme'),
                new OA\Property(property: 'code', type: 'string', example: 'REFORME'),
                new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Bien réformé'),
                new OA\Property(property: 'isActive', type: 'boolean', default: true, example: true),
                new OA\Property(property: 'beneficiaire', type: 'boolean', default: false, example: false, description: 'Indique si ce type de sortie nécessite un bénéficiaire'),
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Type de sortie créé',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 201,
                'message' => 'Type de sortie créé avec succès.',
                'data' => [
                    'id' => 1,
                    'nom' => 'Réforme',
                    'code' => 'REFORME',
                    'description' => 'Bien réformé',
                    'isActive' => true,
                    'beneficiaire' => false,
                    'createdAt' => '2026-08-12 10:00:00',
                    'updatedAt' => '2026-08-12 10:00:00'
                ]
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Validation échouée',
        content: new OA\JsonContent(
            examples: [
                new OA\Examples(
                    example: 'nom_existant',
                    summary: 'Nom déjà utilisé',
                    value: [
                        'success' => false,
                        'status' => 400,
                        'message' => 'La validation a échoué.',
                        'data' => ['nom' => 'Un type de sortie avec ce nom existe déjà.']
                    ]
                ),
                new OA\Examples(
                    example: 'code_existant',
                    summary: 'Code déjà utilisé',
                    value: [
                        'success' => false,
                        'status' => 400,
                        'message' => 'La validation a échoué.',
                        'data' => ['code' => 'Un type de sortie avec ce code existe déjà.']
                    ]
                )
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    #[OA\Response(response: 500, description: 'Erreur serveur')]
    public function __invoke(
        Request $request,
        ExitTypeService $exitTypeService,
        ExitTypeResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        try {
            $payload = json_decode($request->getContent(), true);
            
            // Vérifier que les données sont valides
            if (!is_array($payload)) {
                return $apiResponse->error(
                    'Données invalides. Le format JSON est requis.',
                    Response::HTTP_BAD_REQUEST
                );
            }

            $exitType = $exitTypeService->create($payload);
            
            return $apiResponse->success(
                $responseBuilder->buildDetail($exitType),
                Response::HTTP_CREATED,
                'Type de sortie créé avec succès.'
            );
            
        } catch (ValidationFailedException $e) {
            return $apiResponse->error(
                'La validation a échoué.',
                Response::HTTP_BAD_REQUEST,
                $e->getErrors()
            );
        } catch (\InvalidArgumentException $e) {
            return $apiResponse->error(
                $e->getMessage(),
                Response::HTTP_BAD_REQUEST
            );
        } catch (\Exception $e) {
            // Gérer les erreurs de base de données (contrainte d'unicité)
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                return $apiResponse->error(
                    'Un type de sortie avec ces informations existe déjà.',
                    Response::HTTP_BAD_REQUEST,
                    ['general' => 'Le nom ou le code existe déjà.']
                );
            }
            
            // Log l'erreur pour le débogage
            // $this->logger->error($e->getMessage());
            
            return $apiResponse->error(
                'Une erreur est survenue lors de la création du type de sortie.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}