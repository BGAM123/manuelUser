<?php

namespace App\Controller\Assets;

use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use App\Service\ApiResponseFactory;
use App\Service\AssetReformResponseBuilder;
use App\Service\AssetReformService;
use App\Service\UploadedFilesNormalizer;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Security;

// #[Route('/assets/reforme')]
// #[OA\Tag(name: 'Assets')]
final class CreateAssetReformRequestController extends AbstractController
{
    #[Route('/request', name: 'app_asset_reform_request_create', methods: ['POST'])]
    #[OA\Post(
        path: '/assets/reforme/request',
        summary: 'Créer une demande de réforme pour un ou plusieurs biens',
        description: "Création multipart/form-data. Une pièce justificative est obligatoire.\n\n"
            . "**Uploads :** `piecesJointes[]`, `piecesJointesNoms[]` (un nom par fichier, même index).\n\n"
            . "**Workflow :** Les biens passent à l'état 'À RÉFORMER' et une demande est créée avec le statut EN_ATTENTE.\n\n"
            . "**Validation :** Une seule demande EN_ATTENTE par bien. Un bien déjà RÉFORMÉ ne peut pas avoir de nouvelle demande."
    )]
    #[OA\RequestBody(
        required: false,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                type: 'object',
                properties: [
                    new OA\Property(property: 'asset_ids[]', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3]),
                    new OA\Property(
                        property: 'piecesJointes[]',
                        type: 'array',
                        items: new OA\Items(type: 'string', format: 'binary'),
                        description: 'Upload multiple : piecesJointes[0]=decision.pdf, piecesJointes[1]=rapport.pdf'
                    ),
                    new OA\Property(
                        property: 'piecesJointesNoms[]',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        description: "Un nom par document, même index. Ex. piecesJointesNoms[0]=Décision de réforme, piecesJointesNoms[1]=Rapport d'expertise.",
                        example: ["Décision de réforme", 'Rapport d\'expertise']
                    ),
                ]
            )
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Demandes créées avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 201,
                'message' => 'Demandes de réforme créées avec succès. Les biens sont maintenant à l\'état À RÉFORMER.',
                'data' => [
                    1 => [
                        'id' => 1,
                        'asset' => ['id' => 1, 'reference' => 'PAT-2026-00001', 'nom' => 'Ordinateur Portable'],
                        'statut' => 'EN_ATTENTE',
                        'pieceJointes' => [['id' => 5, 'nom' => 'Décision de réforme', 'chemin' => '/uploads/reforms/decision.pdf']],
                        'createdAt' => '2026-08-10 20:00:00',
                        'createdBy' => ['id' => 2, 'nom' => 'DUPONT', 'prenom' => 'Jean'],
                        'validatedAt' => null,
                        'validatedBy' => null,
                    ],
                    2 => [
                        'id' => 2,
                        'asset' => ['id' => 2, 'reference' => 'PAT-2026-00002', 'nom' => 'Imprimante'],
                        'statut' => 'EN_ATTENTE',
                        'pieceJointes' => [['id' => 5, 'nom' => 'Décision de réforme', 'chemin' => '/uploads/reforms/decision.pdf']],
                        'createdAt' => '2026-08-10 20:00:00',
                        'createdBy' => ['id' => 2, 'nom' => 'DUPONT', 'prenom' => 'Jean'],
                        'validatedAt' => null,
                        'validatedBy' => null,
                    ],
                ]
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Validation', content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => ['piecesJointes' => 'Une pièce justificative est obligatoire.']]))]
    #[OA\Response(response: 404, description: 'Bien ou état introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Bien introuvable.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        Request $request,
        AssetReformService $reformService,
        AssetReformResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse,
        Security $security
    ): JsonResponse {
        $payload = $request->request->all();
        $assetIds = $payload['asset_ids'] ?? [];
        
        if (!is_array($assetIds)) {
            $assetIds = [$assetIds];
        }
        
        $assetIds = array_values(array_unique(array_map('intval', array_filter($assetIds, fn($v) => !empty($v)))));

        $documents = UploadedFilesNormalizer::fromRequest($request, 'piecesJointes');
        $documentLabels = UploadedFilesNormalizer::nullableStringListFromRequest($request, 'piecesJointesNoms');

        $user = $security->getUser();

        try {
            $requests = $reformService->createReformRequests($assetIds, $documents, $documentLabels, $user);
        } catch (ValidationFailedException $e) {
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $e->getErrors());
        } catch (ResourceNotFoundException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $data = [];
        foreach ($requests as $assetId => $request) {
            $data[$assetId] = $responseBuilder->buildDetail($request);
        }

        return $apiResponse->success(
            $data,
            Response::HTTP_CREATED,
            'Demandes de réforme créées avec succès. Les biens sont maintenant à l\'état À RÉFORMER.'
        );
    }
}
