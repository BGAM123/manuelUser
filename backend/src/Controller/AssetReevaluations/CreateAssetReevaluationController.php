<?php

namespace App\Controller\AssetReevaluations;

use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use App\Service\ApiResponseFactory;
use App\Service\AssetReevaluationResponseBuilder;
use App\Service\AssetReevaluationService;
use App\Service\UploadedFilesNormalizer;
use App\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/asset-reevaluations')]
#[OA\Tag(name: 'Asset Reevaluations')]
final class CreateAssetReevaluationController extends AbstractController
{
    #[Route('', name: 'app_asset_reevaluation_create', methods: ['POST'])]
    #[OA\Post(
        path: '/asset-reevaluations',
        summary: 'Créer une réévaluation de bien',
        description: "Création multipart/form-data. Tous les champs sont facultatifs.\n\n"
            . "**Uploads :** `piecesJointes[]`, `piecesJointesNoms[]` (un nom par fichier, même index).\n\n"
            . "**Exemple noms :** piecesJointes[0]=facture.pdf + piecesJointesNoms[0]=Facture d'achat ; piecesJointes[1]=garantie.pdf + piecesJointesNoms[1]=Bon de livraison.\n\n"
            . "**Sans piecesJointesNoms :** chaque document prend automatiquement UploadedFile::getClientOriginalName() (ex. facture.pdf).\n\n"
            . "**Swagger CSV :** si une seule chaîne `Facture d'achat,Bon de livraison` est envoyée, elle est découpée automatiquement en deux noms."
    )]
    #[OA\RequestBody(
        required: false,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                type: 'object',
                properties: [
                    new OA\Property(property: 'asset_ids[]', type: 'array', items: new OA\Items(type: 'integer'), nullable: true, example: [8, 9]),
                    // new OA\Property(property: 'valeurActuelle', type: 'number', format: 'decimal', nullable: true, example: 2000000),
                    new OA\Property(property: 'nouvelleValeur', type: 'number', format: 'decimal', nullable: true, example: 2250000),
                    new OA\Property(property: 'methodeEvaluation', type: 'string', nullable: true, example: 'Expertise'),
                    new OA\Property(property: 'service_id', type: 'integer', nullable: true, example: 6),
                    new OA\Property(property: 'dateReevaluation', type: 'string', format: 'date', nullable: true, example: '2024-01-10'),
                    new OA\Property(property: 'motif', type: 'string', nullable: true, example: 'Réévaluation annuelle'),
                    new OA\Property(property: 'observations', type: 'string', nullable: true),
                    new OA\Property(
                        property: 'piecesJointes[]',
                        type: 'array',
                        items: new OA\Items(type: 'string', format: 'binary'),
                        description: 'Upload multiple : piecesJointes[0]=facture.pdf, piecesJointes[1]=garantie.pdf'
                    ),
                    new OA\Property(
                        property: 'piecesJointesNoms[]',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        description: "Un nom par document, même index. Ex. piecesJointesNoms[0]=Facture d'achat, piecesJointesNoms[1]=Bon de livraison. Si omis → nom original du fichier. Swagger peut aussi envoyer une seule chaîne CSV qui sera découpée automatiquement.",
                        example: ["Facture d'achat", 'Bon de livraison']
                    ),
                ]
            )
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Réévaluation créée',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 201,
                'message' => 'Réévaluation créée avec succès.',
                'data' => [
                    'id' => 1,
                    // 'valeurActuelle' => 2000000,
                    'nouvelleValeur' => 2250000,
                    'methodeEvaluation' => 'Expertise',
                    'service' => ['id' => 6, 'nom' => 'Direction du Patrimoine'],
                    'dateReevaluation' => '2024-01-10',
                    'motif' => 'Réévaluation annuelle',
                    'observations' => null,
                    'piecesJointes' => [],
                    'createdAt' => '2026-08-01 11:00:00'
                ]
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Validation', content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => ['asset_ids' => 'Bien introuvable.']]))]
    #[OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Bien ou Service introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Bien introuvable.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        Request $request,
        AssetReevaluationService $reevaluationService,
        AssetReevaluationResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse,
        #[CurrentUser] ?User $currentUser
    ): JsonResponse {
        $payload = $request->request->all();

        $documents = UploadedFilesNormalizer::fromRequest($request, 'piecesJointes');
        $documentLabels = UploadedFilesNormalizer::nullableStringListFromRequest($request, 'piecesJointesNoms');

        try {
            $reevaluation = $reevaluationService->create($payload, $documents, $documentLabels, $currentUser);
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
            $responseBuilder->buildDetail($reevaluation),
            Response::HTTP_CREATED,
            'Réévaluation créée avec succès.'
        );
    }
}
