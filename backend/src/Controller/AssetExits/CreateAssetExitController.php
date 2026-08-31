<?php

namespace App\Controller\AssetExits;

use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use App\Service\ApiResponseFactory;
use App\Service\AssetExitResponseBuilder;
use App\Service\AssetExitService;
use App\Service\UploadedFilesNormalizer;
use App\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/asset-exits')]
#[OA\Tag(name: 'Asset Exits')]
final class CreateAssetExitController extends AbstractController
{
    #[Route('', name: 'app_asset_exit_create', methods: ['POST'])]
    #[OA\Post(
        path: '/asset-exits',
        summary: 'Créer une sortie de bien',
        description: "Création multipart/form-data. Tous les champs sont facultatifs sauf asset_id.\n\n"
            . "**Cas d'usage :**\n"
            . "- Si vous envoyez `reforme: true`, la sortie sera automatiquement considérée comme une **RÉFORME**.\n"
            . "- Le statut du bien passera automatiquement à `SORTIS`.\n"
            . "- L'état du bien passera automatiquement à `Réformé`.\n"
            . "- Le type de sortie sera automatiquement défini à `Réforme`.\n\n"
            . "**Uploads :** `piecesJointes[]`, `piecesJointesNoms[]` (un nom par fichier, même index).\n\n"
            . "**Exemple noms :** piecesJointes[0]=pv.pdf + piecesJointesNoms[0]=PV de réforme ; piecesJointes[1]=facture.pdf + piecesJointesNoms[1]=Facture.\n\n"
            . "**Sans piecesJointesNoms :** chaque document prend automatiquement UploadedFile::getClientOriginalName() (ex. pv.pdf).\n\n"
            . "**Motifs de sortie :** REFORME, DON, CESSION, PERTE, VOL, DESTRUCTION, LITIGE, MISE_AU_REBUT, AUTRE."
    )]
    #[OA\RequestBody(
        required: false,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                type: 'object',
                properties: [
                    new OA\Property(property: 'asset_id', type: 'integer', nullable: false, example: 5),
                    new OA\Property(property: 'service_id', type: 'integer', nullable: true, example: 16),
                    new OA\Property(property: 'user_id', type: 'integer', nullable: true, example: 15, description: 'ID de l\'utilisateur destinataire (optionnel)'),
                    new OA\Property(property: 'motifSortie', type: 'string', nullable: true, example: 'REFORME'),
                    new OA\Property(property: 'exit_type_id', type: 'integer', nullable: true, example: 1, description: 'ID du type de sortie (optionnel)'),
                    new OA\Property(property: 'reforme', type: 'boolean', nullable: true, example: true, description: 'Si true, la sortie est une réforme (statut SORTIS, état Réformé)'),
                    new OA\Property(property: 'dateSortie', type: 'string', format: 'date', nullable: true, example: '2026-08-01'),
                    new OA\Property(property: 'protocoleReference', type: 'string', nullable: true, example: 'REF-2026-001'),
                    new OA\Property(property: 'observations', type: 'string', nullable: true, example: 'Matériel obsolète.'),
                    new OA\Property(
                        property: 'piecesJointes[]',
                        type: 'array',
                        items: new OA\Items(type: 'string', format: 'binary'),
                        description: 'Upload multiple : piecesJointes[0]=pv.pdf, piecesJointes[1]=facture.pdf'
                    ),
                    new OA\Property(
                        property: 'piecesJointesNoms[]',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        description: "Un nom par document, même index.",
                        example: ["PV de réforme", 'Facture']
                    ),
                ]
            )
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Sortie enregistrée',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 201,
                'message' => 'Sortie enregistrée avec succès.',
                'data' => [
                    'id' => 1,
                    'motifSortie' => 'REFORME',
                    'exitType' => [
                        'id' => 1,
                        'nom' => 'Réforme',
                        'code' => 'REFORME'
                    ],
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
                    'bspsCount' => 0,
                    'bsps' => [],
                    'createdAt' => '2026-08-01 10:00:00'
                ]
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Validation', content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => ['asset_id' => 'Ce bien possède déjà une sortie.']]))]
    #[OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Bien, Service ou Type de sortie introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Bien introuvable.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        Request $request,
        AssetExitService $exitService,
        AssetExitResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse,
        #[CurrentUser] ?User $currentUser
    ): JsonResponse {
        $payload = $request->request->all();

        $documents = UploadedFilesNormalizer::fromRequest($request, 'piecesJointes');
        $documentLabels = UploadedFilesNormalizer::nullableStringListFromRequest($request, 'piecesJointesNoms');

        try {
            $exit = $exitService->create($payload, $documents, $documentLabels, $currentUser);
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
            Response::HTTP_CREATED,
            'Sortie enregistrée avec succès.'
        );
    }
}