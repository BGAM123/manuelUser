<?php

namespace App\Controller\AssetMaintenances;

use App\Entity\AssetMaintenance;
use App\Entity\User;
use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
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
final class UpdateAssetMaintenanceController extends AbstractController
{
    #[Route('/{id}', name: 'app_asset_maintenance_update', methods: ['PUT'])]
    #[OA\Put(
        path: '/asset-maintenances/{id}',
        summary: 'Modifier une maintenance de bien',
        description: "Modification multipart/form-data. Tous les champs sont facultatifs.\n\n"
            . "**Permet :** modification des informations, ajout de nouvelles pièces jointes, conservation des anciennes pièces jointes.\n\n"
            . "**Uploads :** `piecesJointes[]`, `piecesJointesNoms[]` (un nom par fichier, même index).\n\n"
            . "**Exemple noms :** piecesJointes[0]=facture.pdf + piecesJointesNoms[0]=Facture d'achat ; piecesJointes[1]=garantie.pdf + piecesJointesNoms[1]=Bon de livraison.\n\n"
            . "**Sans piecesJointesNoms :** chaque document prend automatiquement UploadedFile::getClientOriginalName() (ex. facture.pdf).\n\n"
            . "**Swagger CSV :** si une seule chaîne `Facture d'achat,Bon de livraison` est envoyée, elle est découpée automatiquement en deux noms."
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 12)]
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
                    new OA\Property(property: 'dateIntervention', type: 'string', format: 'date', nullable: true, example: '2024-01-05'),
                    new OA\Property(
                        property: 'dateRecuperationPrevue',
                        type: 'string',
                        format: 'date',
                        nullable: true,
                        description: 'Date de récupération estimée. Purement indicative, ne clôture pas la maintenance.',
                        example: '2025-06-01'
                    ),
                    new OA\Property(
                        property: 'dateRecuperation',
                        type: 'string',
                        format: 'date',
                        nullable: true,
                        description: 'Date de récupération réelle. La poser clôture la maintenance (statut TERMINEE) et repasse le bien à ACTIF s\'il n\'a plus d\'autre maintenance ouverte — équivalent à PATCH /asset-maintenances/{id}/terminer.',
                        example: '2025-06-05'
                    ),
                    new OA\Property(property: 'observations', type: 'string', nullable: true, example: 'Maintenance annuelle.'),
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
        description: 'Maintenance modifiée',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Maintenance modifiée avec succès.',
                'data' => [
                    'id' => 12,
                    'etatBien' => ['id' => 2, 'nom' => 'Bon'],
                    'motif' => 'Entretien préventif',
                    'cout' => 100000,
                    'dateIntervention' => '2024-01-05',
                    'dateRecuperation' => '2025-06-05',
                    'dateRecuperationPrevue' => '2025-06-01',
                    'dateRecuperationReelle' => '2025-06-05',
                    'observations' => 'Maintenance annuelle.',
                    'statut' => 'TERMINEE',
                    'piecesJointes' => [
                        ['id' => 5, 'nom' => 'Facture', 'chemin' => '/uploads/maintenances/facture.pdf']
                    ],
                    'createdAt' => '2026-08-01 10:30:00'
                ]
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Validation', content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => ['asset_ids' => 'Bien introuvable.']]))]
    #[OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Maintenance introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Maintenance introuvable.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        AssetMaintenance $maintenance,
        Request $request,
        AssetMaintenanceService $maintenanceService,
        AssetMaintenanceResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse,
        #[CurrentUser] ?User $currentUser
    ): JsonResponse {
        $payload = $this->getPayload($request);

        $documents = UploadedFilesNormalizer::fromRequest($request, 'piecesJointes');
        $documentLabels = UploadedFilesNormalizer::parseLabelList(
            $payload['piecesJointesNoms'] ?? UploadedFilesNormalizer::nullableStringListFromRequest($request, 'piecesJointesNoms')
        );

        try {
            $maintenance = $maintenanceService->update($maintenance, $payload, $documents, $documentLabels, $currentUser);
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
            $responseBuilder->buildDetail($maintenance),
            Response::HTTP_OK,
            'Maintenance modifiée avec succès.'
        );
    }

    /**
     * PHP peuple $_POST uniquement pour les requêtes POST multipart. Pour un PUT
     * multipart (Swagger, Postman, formulaire web), Request::$request est donc vide.
     * Les champs texte sont extraits ici afin que PUT continue à fonctionner.
     * Les fichiers doivent être envoyés par POST si le client ne les fournit pas via
     * le mécanisme natif PHP.
     *
     * @return array<string, mixed>
     */
    private function getPayload(Request $request): array
    {
        $payload = $request->request->all();
        if ([] !== $payload) {
            return $payload;
        }

        $content = $request->getContent();
        if ('' === $content) {
            return [];
        }

        $contentType = (string) $request->headers->get('Content-Type', '');
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($content, true);

            return is_array($decoded) ? $decoded : [];
        }
        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            parse_str($content, $payload);

            return $payload;
        }
        if (!preg_match('/boundary=(?:"([^"]+)"|([^;\s]+))/', $contentType, $matches)) {
            return [];
        }

        $boundary = $matches[1] ?: $matches[2];
        $pairs = [];
        foreach (preg_split('/--' . preg_quote($boundary, '/') . '(?:--)?(?:\r\n|$)/', $content) ?: [] as $part) {
            [$headers, $value] = array_pad(explode("\r\n\r\n", $part, 2), 2, null);
            if (null === $value || !preg_match('/name="([^"]+)"/', $headers, $nameMatch)) {
                continue;
            }
            // Les fichiers sont volontairement laissés à Request::$files. Cette lecture
            // concerne uniquement les champs de la maintenance.
            if (str_contains($headers, 'filename=')) {
                continue;
            }
            $pairs[] = rawurlencode($nameMatch[1]) . '=' . rawurlencode(rtrim($value, "\r\n"));
        }
        parse_str(implode('&', $pairs), $payload);

        return $payload;
    }
}
