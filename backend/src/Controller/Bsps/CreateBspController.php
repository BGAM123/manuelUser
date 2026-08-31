<?php

namespace App\Controller\Bsps;

use App\Entity\AssetExit;
use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use App\Service\ApiResponseFactory;
use App\Service\BspResponseBuilder;
use App\Service\BspService;
use App\Service\UploadedFilesNormalizer;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
//use Symfony\Component\Security\Core\Security;

#[Route('/asset-exits')]
#[OA\Tag(name: 'BSP')]
final class CreateBspController extends AbstractController
{
    #[Route('/{id}/bsps', name: 'app_bsp_create', methods: ['POST'])]
    #[OA\Post(
        path: '/asset-exits/{id}/bsps',
        summary: 'Créer un Bon de Sortie Provisoire pour une sortie de bien',
        description: "Création multipart/form-data. Un AssetExit peut être matérialisé par plusieurs BSP, créés au fil du temps "
            . "(un par bénéficiaire et/ou par retrait).\n\n"
            . "**Champs :** `quantiteServie` est obligatoire. `service_id` et `beneficiaire_id` sont facultatifs "
            . "(le service demandeur retombe sur celui de la sortie si non précisé).\n\n"
            . "**Stock :** si le bien de la sortie est un bien de type stock/consomptible (`Asset.quantiteStock` renseigné), "
            . "`quantiteServie` est vérifiée contre le stock disponible et le décrémente automatiquement. "
            . "Sans effet pour un bien physique unique (`quantiteStock` null).\n\n"
            . "**Uploads :** `piecesJointes[]`, `piecesJointesNoms[]` (un nom par fichier, même index)."
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, description: 'Identifiant de l\'AssetExit', schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                type: 'object',
                properties: [
                    new OA\Property(property: 'service_id', type: 'integer', nullable: true, example: 16),
                    new OA\Property(property: 'beneficiaire_id', type: 'integer', nullable: true, example: 4),
                    new OA\Property(property: 'quantiteDemandee', type: 'integer', nullable: true, example: 30),
                    new OA\Property(property: 'quantiteAccordee', type: 'integer', nullable: true, example: 30),
                    new OA\Property(property: 'quantiteServie', type: 'integer', nullable: false, example: 30),
                    new OA\Property(property: 'observations', type: 'string', nullable: true, example: 'Distribution fournitures.'),
                    new OA\Property(property: 'dateEtablissement', type: 'string', format: 'date', nullable: true, example: '2026-08-12'),
                    new OA\Property(
                        property: 'piecesJointes[]',
                        type: 'array',
                        items: new OA\Items(type: 'string', format: 'binary'),
                        description: 'Upload multiple : piecesJointes[0]=bsp-signe.pdf'
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
    #[OA\Response(response: 201, description: 'BSP créé', content: new OA\JsonContent(example: ['success' => true, 'status' => 201, 'message' => 'BSP créé avec succès.', 'data' => ['id' => 1, 'numero' => 'BSP-2026-00001']]))]
    #[OA\Response(response: 400, description: 'Validation ou stock insuffisant', content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => ['quantiteServie' => 'Stock insuffisant. Quantité disponible : 20.']]))]
    #[OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Sortie, service ou bénéficiaire introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Sortie introuvable.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        AssetExit $assetExit,
        Request $request,
        BspService $bspService,
        BspResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse,
        Security $security
    ): JsonResponse {
        if ($assetExit->isDelete()) {
            return $apiResponse->error('Cette sortie est supprimée.', Response::HTTP_NOT_FOUND);
        }

        $payload = $request->request->all();
        $documents = UploadedFilesNormalizer::fromRequest($request, 'piecesJointes');
        $documentLabels = UploadedFilesNormalizer::nullableStringListFromRequest($request, 'piecesJointesNoms');
        $user = $security->getUser();

        try {
            $bsp = $bspService->create($assetExit, $payload, $documents, $documentLabels, $user);
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
            Response::HTTP_CREATED,
            'BSP créé avec succès.'
        );
    }
}
