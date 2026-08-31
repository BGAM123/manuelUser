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
final class UpdateExitTypeController extends AbstractController
{
    #[Route('/{id}', name: 'app_exit_type_update', methods: ['PUT'])]
    #[OA\Put(
        path: '/exit-types/{id}',
        summary: 'Modifier un type de sortie',
        description: 'Modifie un type de sortie existant.'
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
        example: 1
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'nom', type: 'string', example: 'Réforme'),
                new OA\Property(property: 'code', type: 'string', example: 'REFORME'),
                new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Bien réformé'),
                new OA\Property(property: 'isActive', type: 'boolean', example: true),
                new OA\Property(property: 'beneficiaire', type: 'boolean', example: false, description: 'Indique si ce type de sortie nécessite un bénéficiaire'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Type de sortie modifié',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Type de sortie modifié avec succès.',
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
    #[OA\Response(response: 400, description: 'Validation échouée')]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    #[OA\Response(response: 404, description: 'Type de sortie introuvable')]
    #[OA\Response(response: 500, description: 'Erreur serveur')]
    public function __invoke(
        ExitType $exitType,
        Request $request,
        ExitTypeService $exitTypeService,
        ExitTypeResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($exitType->isDelete()) {
            return $apiResponse->error('Ce type de sortie est supprimé.', Response::HTTP_NOT_FOUND);
        }

        try {
            $payload = json_decode($request->getContent(), true);
            
            if (!is_array($payload)) {
                return $apiResponse->error(
                    'Données invalides. Le format JSON est requis.',
                    Response::HTTP_BAD_REQUEST
                );
            }

            $exitType = $exitTypeService->update($exitType, $payload);
            
            return $apiResponse->success(
                $responseBuilder->buildDetail($exitType),
                Response::HTTP_OK,
                'Type de sortie modifié avec succès.'
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
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                return $apiResponse->error(
                    'Un type de sortie avec ces informations existe déjà.',
                    Response::HTTP_BAD_REQUEST,
                    ['general' => 'Le nom ou le code existe déjà.']
                );
            }
            
            return $apiResponse->error(
                'Une erreur est survenue lors de la modification du type de sortie.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}