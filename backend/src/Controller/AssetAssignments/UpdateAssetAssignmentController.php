<?php

namespace App\Controller\AssetAssignments;

use App\Entity\AssetAssignment;
use App\Entity\User;
use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use App\Service\ApiResponseFactory;
use App\Service\AssetAssignmentResponseBuilder;
use App\Service\AssetAssignmentService;
use App\Service\UploadedFilesNormalizer;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/asset-assignments')]
#[OA\Tag(name: 'Asset Assignments')]
final class UpdateAssetAssignmentController extends AbstractController
{
    #[Route('/{id}', name: 'app_asset_assignment_update', methods: ['PUT'])]
    #[OA\Put(
        path: '/asset-assignments/{id}',
        summary: 'Modifier une affectation de bien',
        description: "Modification multipart/form-data. Tous les champs sont facultatifs.\n\n"
            . "**Permet :** modification des informations, ajout de nouvelles pièces jointes, conservation des anciennes pièces jointes.\n\n"
            . "**Uploads :** `piecesJointes[]`, `piecesJointesNoms[]` (un nom par fichier, même index).\n\n"
            . "**Exemple noms :** piecesJointes[0]=facture.pdf + piecesJointesNoms[0]=Facture d'achat ; piecesJointes[1]=garantie.pdf + piecesJointesNoms[1]=Bon de livraison.\n\n"
            . "**Sans piecesJointesNoms :** chaque document prend automatiquement UploadedFile::getClientOriginalName() (ex. facture.pdf).\n\n"
            . "**Swagger CSV :** si une seule chaîne `Facture d'achat,Bon de livraison` est envoyée, elle est découpée automatiquement en deux noms."
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\RequestBody(
        required: false,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                type: 'object',
                properties: [
                    new OA\Property(property: 'asset_id', type: 'integer', nullable: true, example: 8),
                    new OA\Property(property: 'user_id', type: 'integer', nullable: true, example: 5, description: 'ID de l\'utilisateur (prioritaire sur service_id)'),
                    new OA\Property(property: 'service_id', type: 'integer', nullable: true, example: 16),
                    new OA\Property(property: 'typeAffectation', type: 'string', nullable: true, example: 'AFFECTATION'),
                    new OA\Property(property: 'dateDebut', type: 'string', format: 'date', nullable: true, example: '2026-08-01'),
                    new OA\Property(property: 'dateFin', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'commentaire', type: 'string', nullable: true, example: 'Affectation pour le suivi des activités terrain.'),
                    new OA\Property(
                        property: 'piecesJointes[]',
                        type: 'array',
                        items: new OA\Items(type: 'string', format: 'binary'),
                        description: 'Upload multiple à ajouter : piecesJointes[0]=facture.pdf, piecesJointes[1]=garantie.pdf'
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
        response: 200,
        description: 'Affectation modifiée',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Affectation modifiée avec succès.',
                'data' => [
                    'id' => 1,
                    'typeAffectation' => 'AFFECTATION',
                    'dateDebut' => '2026-08-01',
                    'dateFin' => null,
                    'commentaire' => 'Affectation pour le suivi des activités terrain.',
                    'service' => [
                        'id' => 16,
                        'nom' => 'Service de la Santé Animale'
                    ],
                    'localisation' => [
                        'region' => ['id' => 1, 'nom' => 'Centre'],
                        'departement' => ['id' => 5, 'nom' => 'Mfoundi'],
                        'arrondissement' => ['id' => 12, 'nom' => 'Yaoundé I']
                    ],
                    'utilisateur' => [
                        'id' => 5,
                        'firstName' => 'Claire',
                        'lastName' => 'MVONDO'
                    ],
                    'utilisateurDuService' => [
                        'id' => 8,
                        'firstName' => 'Pierre',
                        'lastName' => 'DUPONT'
                    ],
                    'piecesJointes' => [
                        [
                            'id' => 21,
                            'nom' => 'PV de remise',
                            'chemin' => '/uploads/assignments/pv-remise.pdf'
                        ]
                    ],
                    'createdAt' => '2026-08-01 09:15:00'
                ]
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Validation', content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => ['asset_id' => 'Bien introuvable.']]))]
    #[OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Affectation introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Affectation introuvable.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        AssetAssignment $assignment,
        Request $request,
        AssetAssignmentService $assignmentService,
        AssetAssignmentResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse,
        #[CurrentUser] ?User $currentUser
    ): JsonResponse {
        if ($assignment->isDelete()) {
            return $apiResponse->error('Cette affectation est supprimée.', Response::HTTP_CONFLICT);
        }

        $payload = $request->request->all();

        $documents = UploadedFilesNormalizer::fromRequest($request, 'piecesJointes');
        $documentLabels = UploadedFilesNormalizer::nullableStringListFromRequest($request, 'piecesJointesNoms');

        try {
            $assignment = $assignmentService->update($assignment, $payload, $documents, $documentLabels, $currentUser);
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
            $responseBuilder->buildDetail($assignment),
            Response::HTTP_OK,
            'Affectation modifiée avec succès.'
        );
    }
}
