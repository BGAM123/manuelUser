<?php

namespace App\Controller\Assets;

use App\Entity\Asset;
use App\Entity\User;
use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use App\Service\ApiResponseFactory;
use App\Service\AssetManagementService;
use App\Service\AssetResponseBuilder;
use App\Service\UploadedFilesNormalizer;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/assets')]
#[OA\Tag(name: 'Assets')]
final class UpdateAssetPostController extends AbstractController
{
    #[Route('/{id}', name: 'app_asset_update_post', methods: ['POST'])]
    #[OA\Post(
        path: '/assets/{id}',
        summary: 'Mettre à jour un bien patrimonial (POST)',
        description: "Mise à jour via POST multipart/form-data. Tous les champs métier sont facultatifs.\n\n"
            . "**Uploads :** `photos[]`, `piecesJointes[]`, `piecesJointesNoms[]` (un nom par fichier, même index).\n\n"
            . "**Exemple noms :** piecesJointes[0]=facture.pdf + piecesJointesNoms[0]=Facture d'achat ; piecesJointes[1]=garantie.pdf + piecesJointesNoms[1]=Bon de livraison.\n\n"
            . "**Sans piecesJointesNoms :** chaque document prend automatiquement UploadedFile::getClientOriginalName() (ex. facture.pdf).\n\n"
            . "**Swagger CSV :** si une seule chaîne `Facture d'achat,Bon de livraison` est envoyée, elle est découpée automatiquement en deux noms.\n\n"
            . "**Fichiers :** les fichiers sont ajoutés aux existants (pas de remplacement). Les anciens fichiers sont conservés.\n\n"
            . "**Exercice :** l'exercice d'origine du bien n'est jamais modifié par cet endpoint.\n\n"
            . "**Localisation :** latitude et longitude peuvent être mis à jour individuellement si une localisation existe déjà. Pour créer une nouvelle localisation, les deux coordonnées doivent être fournies ensemble."
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\RequestBody(
        required: false,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'reference',
                        type: 'string',
                        nullable: true,
                        example: '',
                        description: 'Optionnel. Si absent, vide, null, "null" ou "undefined" → inchangé (update). Ne jamais envoyer une référence déjà existante.'
                    ),
                    new OA\Property(property: 'nom', type: 'string', nullable: true, example: 'Ordinateur Portable HP ProBook 450 G10'),
                    new OA\Property(property: 'numeroSerie', type: 'string', nullable: true, example: 'HP-PB-2026-0001'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'dateAcquisition', type: 'string', format: 'date', nullable: true, example: '2026-07-30'),
                    new OA\Property(property: 'valeur', type: 'number', nullable: true, example: 850000),
                    new OA\Property(
                        property: 'valeurInitiale',
                        type: 'number',
                        nullable: true,
                        example: 850000,
                        description: 'Mettre à jour la valeur initiale du bien (optionnel)'
                    ),
                    new OA\Property(
                        property: 'activeAmortissement',
                        type: 'boolean',
                        nullable: true,
                        example: true,
                        description: 'Activer/désactiver l\'amortissement pour ce bien'
                    ),
                    new OA\Property(
                        property: 'activeReevaluation',
                        type: 'boolean',
                        nullable: true,
                        example: true,
                        description: 'Activer/désactiver la réévaluation pour ce bien'
                    ),
                    new OA\Property(property: 'modeAcquisition', type: 'string', nullable: true, example: 'Achat'),
                    new OA\Property(property: 'statut', type: 'string', nullable: true, example: 'ACTIF'),
                    new OA\Property(property: 'latitude', type: 'number', nullable: true, example: 3.8480, description: 'Latitude pour la localisation (optionnel)'),
                    new OA\Property(property: 'longitude', type: 'number', nullable: true, example: 11.5021, description: 'Longitude pour la localisation (optionnel)'),
                    new OA\Property(property: 'typeFournisseur', type: 'string', nullable: true, example: 'ENTREPRISE'),
                    new OA\Property(property: 'fournisseurNom', type: 'string', nullable: true, example: 'CAMTEL TECHNOLOGIES'),
                    new OA\Property(property: 'fournisseurEmail', type: 'string', nullable: true, example: 'contact@camtel-technologies.cm'),
                    new OA\Property(property: 'fournisseurTelephone', type: 'string', nullable: true),
                    new OA\Property(property: 'fournisseurAdresse', type: 'string', nullable: true),
                    new OA\Property(property: 'fournisseurVille', type: 'string', nullable: true),
                    new OA\Property(property: 'fournisseurPays', type: 'string', nullable: true),
                     // ✅ Mettre à jour des valeurs existantes
                    new OA\Property(
                        property: 'champs_valeurs_update',
                        type: 'string',
                        example: '5:Rouge;6:150 m²',
                        description: 'Mettre à jour des valeurs existantes (format: input_id:nouvelle_valeur)'
                    ),
                    // ✅ Ajouter de nouvelles valeurs à des champs existants
                    new OA\Property(
                        property: 'champs_valeurs_ajout',
                        type: 'string',
                        example: '1:Bleu;2:200 m²',
                        description: 'Ajouter de nouvelles valeurs à des champs existants (format: champ_id:valeur)'
                    ),
                    new OA\Property(property: 'category_id', type: 'string', nullable: true, example: '2', description: 'Optionnel. Identifiant ou nom exact de catégorie. Ne pas envoyer si vous ne voulez pas modifier la catégorie.'),
                    new OA\Property(property: 'asset_type_id', type: 'string', nullable: true, example: '5', description: 'Optionnel. Identifiant ou nom exact de type de bien. Ne pas envoyer si vous ne voulez pas modifier le type de bien.'),
                    new OA\Property(property: 'etat_bien_id', type: 'integer', nullable: true, example: 1),
                    new OA\Property(property: 'service_id', type: 'integer', nullable: true, example: 16, description: 'ID du service (optionnel, ignoré si user_id est fourni)'),
                    new OA\Property(property: 'user_id', type: 'integer', nullable: true, example: 8, description: 'ID de l\'utilisateur (optionnel, prioritaire sur service_id)'),
                    new OA\Property(property: 'project_ids[]', type: 'array', items: new OA\Items(type: 'integer'), example: [3, 7]),
                    new OA\Property(
                        property: 'photos[]',
                        type: 'array',
                        items: new OA\Items(type: 'string', format: 'binary'),
                        description: 'Upload multiple à ajouter : photos[0]=photo1.jpg, photos[1]=photo2.jpg'
                    ),
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
        description: 'Bien mis à jour',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Bien mis à jour avec succès.',
                'data' => [
                    'id' => 1,
                    'reference' => 'PAT-2026-00001',
                    'nom' => 'Ordinateur Portable HP ProBook 450 G10',
                    // 'exercice' => 2026,
                    'photos' => [
                        ['id' => 15, 'nom' => 'photo1.jpg', 'chemin' => '/uploads/assets/photos/photo1_abc.jpg'],
                        ['id' => 16, 'nom' => 'photo2.jpg', 'chemin' => '/uploads/assets/photos/photo2_def.jpg'],
                    ],
                    'piecesJointes' => [
                        ['id' => 21, 'nom' => "Facture d'achat", 'chemin' => '/uploads/assets/documents/facture_xyz.pdf'],
                        ['id' => 22, 'nom' => 'Bon de livraison', 'chemin' => '/uploads/assets/documents/bon_uvw.pdf'],
                    ],
                ],
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Validation', content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => ['valeur' => 'Valeur invalide']]))]
    #[OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Le bien demandé est introuvable.', 'data' => null]))]
    #[OA\Response(response: 409, description: 'Conflit', content: new OA\JsonContent(example: ['success' => false, 'status' => 409, 'message' => 'Ce bien est supprimé.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        Asset $asset,
        Request $request,
        AssetManagementService $assetManagementService,
        AssetResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse,
        #[CurrentUser] ?User $currentUser
    ): JsonResponse {
        if ($asset->isDelete()) {
            return $apiResponse->error('Ce bien est supprimé.', Response::HTTP_CONFLICT);
        }

        // Même logique que CreateAssetController pour la gestion des fichiers
        $payload = $request->request->all();
        $this->normalizeArrayFields($payload);

        $photos = UploadedFilesNormalizer::fromRequest($request, 'photos');
        $documents = UploadedFilesNormalizer::fromRequest($request, 'piecesJointes');
        if ([] === $documents) {
            $documents = UploadedFilesNormalizer::fromRequest($request, 'documents');
        }
        $documentLabels = UploadedFilesNormalizer::nullableStringListFromRequest($request, 'piecesJointesNoms');
        if ([] === $documentLabels) {
            $documentLabels = UploadedFilesNormalizer::nullableStringListFromRequest($request, 'documents_labels');
        }

        // ✅ Parser les champs pour la mise à jour
        $champsValeursUpdate = $this->parseChampsValeursUpdate($payload['champs_valeurs_update'] ?? null);
        $champsValeursAjout = $this->parseChampsValeursAjout($payload['champs_valeurs_ajout'] ?? null);

        try {
            $asset = $assetManagementService->update($asset, $payload, $photos, $documents, $documentLabels, $champsValeursUpdate, $champsValeursAjout, $currentUser);
        } catch (ValidationFailedException $e) {
            $errors = $e->getErrors();
            // Doublon de référence → 409 plutôt que 400
            if (isset($errors['reference']) && str_contains((string) $errors['reference'], 'déjà utilisée')) {
                return $apiResponse->error((string) $errors['reference'], Response::HTTP_CONFLICT, $errors);
            }

            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $errors);
        } catch (ResourceNotFoundException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            $decoded = json_decode($e->getMessage(), true);
            if (is_array($decoded)) {
                return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST, $decoded);
            }
            $status = str_contains($e->getMessage(), 'déjà utilisée') ? Response::HTTP_CONFLICT : Response::HTTP_BAD_REQUEST;

            return $apiResponse->error($e->getMessage(), $status);
        }

        return $apiResponse->success(
            $responseBuilder->buildDetail($asset),
            Response::HTTP_OK,
            'Bien mis à jour avec succès.'
        );
    }

     /**
     * ✅ Parse les mises à jour de valeurs existantes
     * Format: "5:Rouge;6:150 m²"
     * @param mixed $value
     * @return array<array{input_id: int, valeur: string}>
     */
    private function parseChampsValeursUpdate($value): array
    {
        if (null === $value || '' === $value) {
            return [];
        }

        $result = [];

        if (is_string($value)) {
            // Format JSON
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (isset($item['input_id']) && isset($item['valeur'])) {
                        $result[] = [
                            'input_id' => (int) $item['input_id'],
                            'valeur' => (string) $item['valeur']
                        ];
                    }
                }
                return $result;
            }

            // Format CSV: "5:Rouge;6:150 m²"
            if (str_contains($value, ';') || str_contains($value, ':')) {
                $pairs = explode(';', $value);
                foreach ($pairs as $pair) {
                    $parts = explode(':', $pair, 2);
                    if (count($parts) === 2) {
                        $inputId = trim($parts[0]);
                        $valeur = trim($parts[1]);
                        if (is_numeric($inputId) && !empty($valeur)) {
                            $result[] = [
                                'input_id' => (int) $inputId,
                                'valeur' => $valeur
                            ];
                        }
                    }
                }
                return $result;
            }
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (isset($item['input_id']) && isset($item['valeur'])) {
                    $result[] = [
                        'input_id' => (int) $item['input_id'],
                        'valeur' => (string) $item['valeur']
                    ];
                }
            }
            return $result;
        }

        return $result;
    }

    /**
     * ✅ Parse les valeurs à ajouter à des champs existants
     * Format: "1:Bleu;2:200 m²"
     * @param mixed $value
     * @return array<array{champ_id: int, valeur: string}>
     */
    private function parseChampsValeursAjout($value): array
    {
        if (null === $value || '' === $value) {
            return [];
        }

        $result = [];

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (isset($item['champ_id']) && isset($item['valeur'])) {
                        $result[] = [
                            'champ_id' => (int) $item['champ_id'],
                            'valeur' => (string) $item['valeur']
                        ];
                    }
                }
                return $result;
            }

            if (str_contains($value, ';') || str_contains($value, ':')) {
                $pairs = explode(';', $value);
                foreach ($pairs as $pair) {
                    $parts = explode(':', $pair, 2);
                    if (count($parts) === 2) {
                        $champId = trim($parts[0]);
                        $valeur = trim($parts[1]);
                        if (is_numeric($champId) && !empty($valeur)) {
                            $result[] = [
                                'champ_id' => (int) $champId,
                                'valeur' => $valeur
                            ];
                        }
                    }
                }
                return $result;
            }
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (isset($item['champ_id']) && isset($item['valeur'])) {
                    $result[] = [
                        'champ_id' => (int) $item['champ_id'],
                        'valeur' => (string) $item['valeur']
                    ];
                }
            }
            return $result;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function normalizeArrayFields(array &$payload): void
    {
        foreach (['project_ids', 'category_ids', 'asset_type_ids', 'etat_bien_ids', 'service_ids'] as $key) {
            if (isset($payload[$key]) && !is_array($payload[$key])) {
                if (is_string($payload[$key]) && str_contains($payload[$key], ',')) {
                    $payload[$key] = array_map('trim', explode(',', $payload[$key]));
                } else {
                    $payload[$key] = [$payload[$key]];
                }
            }
            // Variante project_ids[]
            $bracket = $key . '[]';
            if (isset($payload[$bracket])) {
                $payload[$key] = is_array($payload[$bracket]) ? $payload[$bracket] : [$payload[$bracket]];
                unset($payload[$bracket]);
            }
        }
    }
}
