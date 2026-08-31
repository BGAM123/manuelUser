<?php

namespace App\Controller\Bsps;

use App\Entity\Bsp;
use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use App\Service\ApiResponseFactory;
use App\Service\BspResponseBuilder;
use App\Service\BspService;
use App\Service\UploadedFilesNormalizer;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/bsps')]
#[OA\Tag(name: 'BSP')]
final class UpdateBspController extends AbstractController
{
    #[Route('/{id}', name: 'app_bsp_update', methods: ['PUT'])]
    #[OA\Put(
        path: '/bsps/{id}',
        summary: 'Modifier un BSP',
        description: "Modification multipart/form-data. Tous les champs sont facultatifs.\n\n"
            . "**Stock :** si `quantiteServie` change et que le bien est de type stock/consomptible, le stock du bien "
            . "est réajusté automatiquement (restitution de l'ancienne quantité puis prélèvement de la nouvelle).\n\n"
            . "**Uploads :** `piecesJointes[]`, `piecesJointesNoms[]` (un nom par fichier, même index)."
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\RequestBody(
        required: false,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                type: 'object',
                properties: [
                    new OA\Property(property: 'service_id', type: 'integer', nullable: true, example: 16),
                    new OA\Property(property: 'beneficiaire_id', type: 'integer', nullable: true, example: 4),
                    new OA\Property(property: 'quantiteDemandee', type: 'integer', nullable: true, example: 30),
                    new OA\Property(property: 'quantiteAccordee', type: 'integer', nullable: true, example: 30),
                    new OA\Property(property: 'quantiteServie', type: 'integer', nullable: true, example: 30),
                    new OA\Property(property: 'observations', type: 'string', nullable: true, example: 'Distribution fournitures.'),
                    new OA\Property(property: 'dateEtablissement', type: 'string', format: 'date', nullable: true, example: '2026-08-12'),
                    new OA\Property(
                        property: 'piecesJointes[]',
                        type: 'array',
                        items: new OA\Items(type: 'string', format: 'binary'),
                        description: 'Upload multiple à ajouter'
                    ),
                    new OA\Property(
                        property: 'piecesJointesNoms[]',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        description: 'Un nom par document, même index.',
                        example: ['BSP signé']
                    ),
                ]
            )
        )
    )]
    #[OA\Response(response: 200, description: 'BSP modifié', content: new OA\JsonContent(example: ['success' => true, 'status' => 200, 'message' => 'BSP modifié avec succès.', 'data' => ['id' => 1, 'numero' => 'BSP-2026-00001']]))]
    #[OA\Response(response: 400, description: 'Validation', content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => ['quantiteServie' => 'La quantité servie doit être supérieure à 0.']]))]
    #[OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'BSP introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'BSP introuvable.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        Bsp $bsp,
        Request $request,
        BspService $bspService,
        BspResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($bsp->isDelete()) {
            return $apiResponse->error('Ce BSP est supprimé.', Response::HTTP_CONFLICT);
        }

        $payload = $request->request->all();
        $documents = UploadedFilesNormalizer::fromRequest($request, 'piecesJointes');
        $documentLabels = UploadedFilesNormalizer::nullableStringListFromRequest($request, 'piecesJointesNoms');

        try {
            $bsp = $bspService->update($bsp, $payload, $documents, $documentLabels);
        } catch (ValidationFailedException $e) {
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $e->getErrors());
        } catch (ResourceNotFoundException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            $decoded = json_decode($e->getMessage(), true);
            if (is_array($decoded)) {
                return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $decoded);
            }
            return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return $apiResponse->success(
            $responseBuilder->buildDetail($bsp),
            Response::HTTP_OK,
            'BSP modifié avec succès.'
        );
    }
}
