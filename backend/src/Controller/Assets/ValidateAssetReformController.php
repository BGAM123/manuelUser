<?php

namespace App\Controller\Assets;

use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use App\Service\ApiResponseFactory;
use App\Service\AssetReformService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Security;

// #[Route('/assets/reforme')]
// #[OA\Tag(name: 'Assets')]
final class ValidateAssetReformController extends AbstractController
{
    #[Route('/validate', name: 'app_asset_reform_validate', methods: ['POST'])]
    #[OA\Post(
        path: '/assets/reforme/validate',
        summary: 'Valider ou rejeter une ou plusieurs demandes de réforme',
        description: "Valide ou rejette les demandes de réforme en attente.\n\n"
            . "**Validation :** Les demandes passent à VALIDEE et les biens passent à RÉFORMÉ.\n\n"
            . "**Rejet :** Les demandes passent à REJETEE et les biens reviennent à leur état précédent (avant À RÉFORMER).\n\n"
            . "**Transaction :** L'opération est atomique pour garantir la cohérence entre la demande et l'état du bien.\n\n"
            . "**Validation :** Seules les demandes EN_ATTENTE peuvent être traitées. Une demande déjà traitée ne sera pas retraitée."
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['asset_ids', 'statut'],
            properties: [
                new OA\Property(property: 'asset_ids', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3]),
                new OA\Property(property: 'statut', type: 'string', enum: ['VALIDEE', 'REJETEE'], example: 'VALIDEE', description: 'Statut à appliquer : VALIDEE pour valider, REJETEE pour rejeter'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Demandes validées avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Demandes de réforme validées avec succès. Les biens sont maintenant à l\'état RÉFORMÉ.',
                'data' => [
                    1 => [
                        'requestId' => 1,
                        'assetId' => 1,
                        'assetReference' => 'PAT-2026-00001',
                        'validatedAt' => '2026-08-10 20:30:00',
                        'validatedBy' => 'DUPONT Jean',
                    ],
                    2 => [
                        'requestId' => 2,
                        'assetId' => 2,
                        'assetReference' => 'PAT-2026-00002',
                        'validatedAt' => '2026-08-10 20:30:00',
                        'validatedBy' => 'DUPONT Jean',
                    ],
                ]
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Validation', content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => ['asset_2' => 'Aucune demande de réforme en attente n\'a été trouvée pour ce bien.']]))]
    #[OA\Response(response: 404, description: 'Bien ou état introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'État RÉFORMÉ introuvable.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        Request $request,
        AssetReformService $reformService,
        ApiResponseFactory $apiResponse,
        Security $security
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $apiResponse->error('Invalid JSON payload.', Response::HTTP_BAD_REQUEST);
        }

        if (!isset($payload['asset_ids']) || !is_array($payload['asset_ids'])) {
            return $apiResponse->error('asset_ids est requis et doit être un tableau.', Response::HTTP_BAD_REQUEST);
        }

        if (!isset($payload['statut']) || !in_array($payload['statut'], ['VALIDEE', 'REJETEE'], true)) {
            return $apiResponse->error('statut est requis et doit être VALIDEE ou REJETEE.', Response::HTTP_BAD_REQUEST);
        }

        $assetIds = array_values(array_unique(array_map('intval', array_filter($payload['asset_ids'], fn($v) => !empty($v)))));
        $statut = $payload['statut'];

        if (empty($assetIds)) {
            return $apiResponse->error('Au moins un ID de bien est requis.', Response::HTTP_BAD_REQUEST);
        }

        $user = $security->getUser();
        if (!$user) {
            return $apiResponse->error('Authentification requise.', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $results = $reformService->validateReformRequests($assetIds, $statut, $user);
        } catch (ValidationFailedException $e) {
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $e->getErrors());
        } catch (ResourceNotFoundException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $message = $statut === 'VALIDEE' 
            ? 'Demandes de réforme validées avec succès. Les biens sont maintenant à l\'état RÉFORMÉ.'
            : 'Demandes de réforme rejetées avec succès. Les biens sont revenus à leur état précédent.';

        return $apiResponse->success(
            $results,
            Response::HTTP_OK,
            $message
        );
    }
}
