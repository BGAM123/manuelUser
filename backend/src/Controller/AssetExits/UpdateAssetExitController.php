<?php

namespace App\Controller\AssetExits;

use App\Entity\AssetExit;
use App\Entity\User;
use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use App\Service\ApiResponseFactory;
use App\Service\AssetExitResponseBuilder;
use App\Service\AssetExitService;
use App\Service\UploadedFilesNormalizer;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/asset-exits')]
#[OA\Tag(name: 'Asset Exits')]
final class UpdateAssetExitController extends AbstractController
{
    #[Route('/{id}', name: 'app_asset_exit_update', methods: ['POST'])]
    #[OA\Post(
        path: '/asset-exits/{id}',
        summary: 'Modifier une sortie de bien',
        description: "Modification multipart/form-data. Tous les champs sont facultatifs.\n\n"
            . "**Permet :** modification des informations, ajout de nouvelles pièces jointes, conservation des anciennes pièces jointes.\n\n"
            . "**Uploads :** `piecesJointes[]`, `piecesJointesNoms[]` (un nom par fichier, même index).\n\n"
            . "**Exemple noms :** piecesJointes[0]=pv.pdf + piecesJointesNoms[0]=PV de réforme ; piecesJointes[1]=facture.pdf + piecesJointesNoms[1]=Facture.\n\n"
            . "**Sans piecesJointesNoms :** chaque document prend automatiquement UploadedFile::getClientOriginalName() (ex. pv.pdf).\n\n"
            . "**Swagger CSV :** si une seule chaîne `PV de réforme,Facture` est envoyée, elle est découpée automatiquement en deux noms."
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\RequestBody(
        required: false,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                type: 'object',
                properties: [
                    new OA\Property(property: 'asset_id', type: 'integer', nullable: true, example: 5),
                    new OA\Property(property: 'service_id', type: 'integer', nullable: true, example: 16),
                    new OA\Property(property: 'user_id', type: 'integer', nullable: true, example: 15, description: 'ID de l\'utilisateur destinataire (optionnel)'),
                    new OA\Property(property: 'exit_type_id', type: 'integer', nullable: true, example: 1),
                    new OA\Property(property: 'reforme', type: 'boolean', nullable: true, example: true, description: 'Si true, la sortie est une réforme (statut SORTIS, état Réformé)'),
                    new OA\Property(property: 'motifSortie', type: 'string', nullable: true, example: 'REFORME'),
                    new OA\Property(property: 'dateSortie', type: 'string', format: 'date', nullable: true, example: '2026-08-01'),
                    new OA\Property(property: 'protocoleReference', type: 'string', nullable: true, example: 'REF-2026-001'),
                    new OA\Property(property: 'observations', type: 'string', nullable: true, example: 'Matériel obsolète.'),
                    new OA\Property(
                        property: 'piecesJointes[]',
                        type: 'array',
                        items: new OA\Items(type: 'string', format: 'binary'),
                        description: 'Upload multiple à ajouter : piecesJointes[0]=pv.pdf, piecesJointes[1]=facture.pdf'
                    ),
                    new OA\Property(
                        property: 'piecesJointesNoms[]',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        description: "Un nom par document, même index. Ex. piecesJointesNoms[0]=PV de réforme, piecesJointesNoms[1]=Facture. Si omis → nom original du fichier. Swagger peut aussi envoyer une seule chaîne CSV qui sera découpée automatiquement.",
                        example: ["PV de réforme", 'Facture']
                    ),
                ]
            )
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Sortie modifiée',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Sortie modifiée avec succès.',
                'data' => [
                    'id' => 1,
                    'exitType' => [
                        'id' => 1,
                        'nom' => 'Réforme',
                        'code' => 'REFORME'
                    ],
                    'motifSortie' => 'REFORME',
                    'dateSortie' => '2026-08-01',
                    'protocoleReference' => 'REF-2026-001',
                    'observations' => 'Matériel obsolète.',
                    'asset' => [
                        'id' => 5,
                        'reference' => 'PAT-2026-00001',
                        'nom' => 'Ordinateur HP'
                    ],
                    'service' => [
                        'id' => 16,
                        'nom' => 'Comptabilité'
                    ],
                    "detenteur"=>  [
                        "id"=>  65,
                        "nom"=>  "Nengue",
                        "prenom"=>  "Alex",
                        "matricule"=>  "mat-800-df",
                        "service"=>  [
                            "id"=>  16,
                            "nom"=>  "Direction du Développement des Productions et des Industries Animales"
                            ]
                     ],
                    'piecesJointes' => [
                        [
                            'id' => 21,
                            'nom' => 'PV de réforme',
                            'chemin' => '/uploads/exits/pv-reforme.pdf'
                        ]
                    ],
                    'bspsCount' => 2,
                    'bsps' => [
                        ['id' => 1, 'numero' => 'BSP-2026-00001', 'quantiteServie' => 30, 'beneficiaire' => ['id' => 4, 'nom' => 'NGUEMA', 'prenom' => 'Paul']],
                        ['id' => 2, 'numero' => 'BSP-2026-00002', 'quantiteServie' => 40, 'beneficiaire' => null],
                    ],
                    'createdAt' => '2026-08-01 10:00:00'
                ]
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Validation', content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => ['asset_id' => 'Bien introuvable.']]))]
    #[OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Sortie introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Sortie introuvable.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        AssetExit $exit,
        Request $request,
        AssetExitService $exitService,
        AssetExitResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse,
        #[CurrentUser] ?User $currentUser
    ): JsonResponse {
        if ($exit->isDelete()) {
            return $apiResponse->error('Cette sortie est supprimée.', Response::HTTP_CONFLICT);
        }

        // ✅ Récupération complète du payload
        $payload = $request->request->all();

        $documents = UploadedFilesNormalizer::fromRequest($request, 'piecesJointes');
        $documentLabels = UploadedFilesNormalizer::nullableStringListFromRequest($request, 'piecesJointesNoms');

        try {
            $exit = $exitService->update($exit, $payload, $documents, $documentLabels, $currentUser);
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
            $responseBuilder->buildDetail($exit),
            Response::HTTP_OK,
            'Sortie modifiée avec succès.'
        );
    }
}