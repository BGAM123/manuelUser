<?php

namespace App\Controller\AssetMaintenances;

use App\Entity\Asset;
use App\Entity\User;
use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use App\Repository\AssetRepository;
use App\Service\ApiResponseFactory;
use App\Service\AssetMaintenanceResponseBuilder;
use App\Service\AssetMaintenanceService;
use App\Service\UploadedFilesNormalizer;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/asset-maintenances')]
#[OA\Tag(name: 'Asset Maintenances')]
final class CreateAssetMaintenanceController extends AbstractController
{
    public function __construct(
        private readonly AssetRepository $assetRepository // ✅ Injection du repository
    ) {
    }

    #[Route('', name: 'app_asset_maintenance_create', methods: ['POST'])]
    #[OA\Post(
        path: '/asset-maintenances',
        summary: 'Créer une maintenance de bien',
        description: "Création multipart/form-data. Tous les champs sont facultatifs.\n\n"
            . "**Statut :** une maintenance créée démarre toujours EN COURS.\n\n"
            . "**Vérification du seuil :** Si le nombre total de maintenances d'un bien dépasse le seuil maximum autorisé par sa catégorie, un message d'avertissement est retourné."
    )]
    #[OA\RequestBody(
        required: false,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                type: 'object',
                properties: [
                    new OA\Property(property: 'asset_ids[]', type: 'array', items: new OA\Items(type: 'integer'), nullable: true, example: [8, 9]),
                    new OA\Property(property: 'etat_bien_id', type: 'integer', nullable: true, example: 2),
                    new OA\Property(property: 'motif', type: 'string', nullable: true, example: 'Entretien préventif'),
                    new OA\Property(property: 'cout', type: 'number', format: 'decimal', nullable: true, example: 100000),
                    new OA\Property(property: 'dateIntervention', type: 'string', format: 'date', nullable: true, example: '2026-08-20'),
                    new OA\Property(
                        property: 'dateRecuperationPrevue',
                        type: 'string',
                        format: 'date',
                        nullable: true,
                        example: '2026-08-27'
                    ),
                    new OA\Property(property: 'observations', type: 'string', nullable: true, example: 'Maintenance annuelle.'),
                    new OA\Property(
                        property: 'piecesJointes[]',
                        type: 'array',
                        items: new OA\Items(type: 'string', format: 'binary'),
                    ),
                    new OA\Property(
                        property: 'piecesJointesNoms[]',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        example: ["Facture d'achat", 'Bon de livraison']
                    ),
                ]
            )
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Maintenance créée',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 201,
                'message' => 'Maintenance créée avec succès.',
                'data' => [
                    'id' => 12,
                    'seuil_verification' => [
                        'seuil_defini' => true,
                        'seuil' => 5,
                        'nombre_maintenances' => 3,
                        'depasse' => false,
                        'restant' => 2,
                        'message' => '✓ Ce bien a 3 maintenance(s) sur 5 autorisées. Encore 2 maintenance(s) possible(s).'
                    ]
                ]
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Validation - Seuil dépassé',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 400,
                'message' => '⚠️ ATTENTION : Ce bien a déjà 6 maintenance(s). La catégorie autorise un maximum de 5 maintenance(s). Seuil dépassé de 1 maintenance(s).',
                'data' => [
                    'seuil' => 5,
                    'nombre_maintenances' => 6,
                    'restant' => 0
                ]
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    #[OA\Response(response: 404, description: 'Bien introuvable')]
    public function __invoke(
        Request $request,
        AssetMaintenanceService $maintenanceService,
        AssetMaintenanceResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse,
        #[CurrentUser] ?User $currentUser
    ): JsonResponse {
        $payload = $request->request->all();

        $documents = UploadedFilesNormalizer::fromRequest($request, 'piecesJointes');
        $documentLabels = UploadedFilesNormalizer::nullableStringListFromRequest($request, 'piecesJointesNoms');

        try {
            // ✅ VÉRIFICATION DU SEUIL AVANT CRÉATION
            $assetIds = $payload['asset_ids'] ?? [];
            if (!is_array($assetIds)) {
                $assetIds = [$assetIds];
            }

            foreach ($assetIds as $assetId) {
                // ✅ Utilisation du repository injecté
                $asset = $this->assetRepository->getActiveById((int) $assetId);
                if (!$asset) {
                    return $apiResponse->error(
                        sprintf('Bien avec l\'ID %d introuvable.', $assetId),
                        Response::HTTP_NOT_FOUND
                    );
                }

                // Vérifier le seuil
                $check = $maintenanceService->checkMaintenanceThreshold($asset);

                // if ($check['seuil_defini'] && $check['depasse']) {
                //     return $apiResponse->error(
                //         $check['message'],
                //         Response::HTTP_BAD_REQUEST,
                //         [
                //             'seuil' => $check['seuil'],
                //             'nombre_maintenances' => $check['nombre_maintenances'],
                //             'restant' => $check['restant']
                //         ]
                //     );
                // }

                // if ($check['seuil_defini'] && $check['atteint']) {  // ← ICI, bloque si ATTEINT OU DEPASSE
                //     return $apiResponse->error(
                //         $check['message'],
                //         Response::HTTP_BAD_REQUEST,
                //         [
                //             'seuil' => $check['seuil'],
                //             'nombre_maintenances' => $check['nombre_maintenances'],
                //             'restant' => $check['restant']
                //         ]
                //     );
                // }
            }

            // Créer la maintenance
            $maintenance = $maintenanceService->create($payload, $documents, $documentLabels, $currentUser);

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

        // ✅ Ajouter les informations de seuil dans la réponse
        $responseData = $responseBuilder->buildDetail($maintenance);

        $thresholdChecks = [];
        foreach ($maintenance->getAssets() as $asset) {
            $thresholdChecks[] = $maintenanceService->checkMaintenanceThreshold($asset);
        }

        if (!empty($thresholdChecks)) {
            $responseData['seuil_verification'] = count($thresholdChecks) === 1
                ? $thresholdChecks[0]
                : $thresholdChecks;
        }

        return $apiResponse->success(
            $responseData,
            Response::HTTP_CREATED,
            'Maintenance créée avec succès.'
        );
    }
}
