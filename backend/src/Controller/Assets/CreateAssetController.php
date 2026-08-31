<?php

namespace App\Controller\Assets;

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
final class CreateAssetController extends AbstractController
{
    #[Route('', name: 'app_asset_create', methods: ['POST'])]
    #[OA\Post(
        path: '/assets',
        summary: 'Créer un bien patrimonial',
        description: "Création multipart/form-data. Tous les champs métier sont facultatifs.\n\n"
            . "**Exercice :** optionnel, année courante par défaut si non fourni.\n\n"
            . "**Uploads :** `photos[]`, `piecesJointes[]`, `piecesJointesNoms[]` (un nom par fichier, même index).\n\n"
            . "**Exemple noms :** piecesJointes[0]=facture.pdf + piecesJointesNoms[0]=Facture d'achat ; piecesJointes[1]=garantie.pdf + piecesJointesNoms[1]=Bon de livraison.\n\n"
            . "**Sans piecesJointesNoms :** chaque document prend automatiquement UploadedFile::getClientOriginalName() (ex. facture.pdf).\n\n"
            . "**Swagger CSV :** si une seule chaîne `Facture d'achat,Bon de livraison` est envoyée, elle est découpée automatiquement en deux noms.\n\n"
            . "**Référence :** si absente / vide / null / \"null\" / \"undefined\" → auto PAT-YYYY-NNNNN."
    )]
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
                        description: 'Optionnel. Si absent, vide, null, "null" ou "undefined" → génération auto PAT-YYYY-NNNNN. Ne jamais envoyer une référence déjà existante.'
                    ),
                    // ✅ Champs existants (JSON ou CSV)
                    new OA\Property(
                        property: 'champs_existants',
                        type: 'string',
                        example: '1,2,3',
                        description: 'IDs des champs existants (ex: 1,2,3 ou [1,2,3])'
                    ),
                    // ✅ Nouveaux champs (format CSV ou JSON)
                    new OA\Property(
                        property: 'champs_nouveaux',
                        type: 'string',
                        example: 'Couleur:Bleu;Matériau:Bois;Surface:120 m²',
                        description: 'Nouveaux champs à créer. Format: nom:valeur;nom:valeur (ex: Couleur:Bleu;Matériau:Bois)'
                    ),
                    // ✅ Ajouter des valeurs à des champs existants
                    new OA\Property(
                        property: 'champs_valeurs',
                        type: 'string',
                        example: '1:Rouge;2:150 m²',
                        description: 'Ajouter des valeurs à des champs existants (format: champ_id:valeur;champ_id:valeur)'
                    ),
                    new OA\Property(property: 'nom', type: 'string', nullable: true, example: 'Ordinateur Portable HP ProBook 450 G10'),
                    new OA\Property(property: 'numeroSerie', type: 'string', nullable: true, example: 'HP-PB-2026-0001'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'dateAcquisition', type: 'string', format: 'date', nullable: true, example: '2026-07-30'),
                    // new OA\Property(property: 'exercice', type: 'integer', nullable: true, example: 2026, description: 'Optionnel. Année de l\'exercice patrimonial. Si non fourni, année courante par défaut.'),
                    new OA\Property(property: 'valeur', type: 'number', nullable: true, example: 850000),
                    new OA\Property(
                        property: 'activeAmortissement',
                        type: 'boolean',
                        nullable: true,
                        example: true,
                        default: true,
                        description: 'Activer/désactiver l\'amortissement pour ce bien'
                    ),
                    new OA\Property(
                        property: 'activeReevaluation',
                        type: 'boolean',
                        nullable: true,
                        example: false,
                        default: false,
                        description: 'Activer/désactiver la réévaluation pour ce bien'
                    ),
                    new OA\Property(property: 'prixMercurial', type: 'number', nullable: true, example: 750000, description: 'Prix Mercurial du bien'),
                    new OA\Property(property: 'code', type: 'string', nullable: true, example: 'HP-PB-450-G10', description: 'Code du bien'),
                    new OA\Property(property: 'lien', type: 'string', nullable: true, example: 'https://example.com/search?q=HP+ProBook+450', description: 'Lien vers une recherche externe'),
                    new OA\Property(property: 'modeAcquisition', type: 'string', nullable: true, example: 'Achat'),
                    new OA\Property(property: 'statut', type: 'string', nullable: true, example: 'ACTIF'),
                    new OA\Property(property: 'quantiteStock', type: 'integer', nullable: true, example: 100, description: 'Optionnel. À renseigner uniquement pour un bien de type stock/consomptible (fournitures, matériel de réunion, etc.). Décrémenté automatiquement par les sorties BSP.'),
                    new OA\Property(property: 'typeFournisseur', type: 'string', nullable: true, example: 'ENTREPRISE'),
                    new OA\Property(property: 'fournisseurNom', type: 'string', nullable: true, example: 'CAMTEL TECHNOLOGIES'),
                    new OA\Property(property: 'fournisseurEmail', type: 'string', nullable: true, example: 'contact@camtel-technologies.cm'),
                    new OA\Property(property: 'fournisseurTelephone', type: 'string', nullable: true),
                    new OA\Property(property: 'fournisseurAdresse', type: 'string', nullable: true),
                    new OA\Property(property: 'fournisseurVille', type: 'string', nullable: true),
                    new OA\Property(property: 'fournisseurPays', type: 'string', nullable: true),
                    new OA\Property(property: 'category_id', type: 'string', nullable: true, example: '2', description: 'Optionnel. Identifiant ou nom exact de catégorie. Si absent, la catégorie par défaut est utilisée.'),
                    new OA\Property(property: 'asset_type_id', type: 'string', nullable: true, example: '5', description: 'Optionnel. Identifiant ou nom exact de type de bien. Si absent, le type de bien par défaut est utilisé.'),
                    new OA\Property(property: 'etat_bien_id', type: 'integer', nullable: true, example: 1),
                    new OA\Property(property: 'service_id', type: 'integer', nullable: true, example: 16, description: 'ID du service (optionnel, ignoré si user_id est fourni)'),
                    new OA\Property(property: 'user_id', type: 'integer', nullable: true, example: 8, description: 'ID de l\'utilisateur (optionnel, prioritaire sur service_id)'),
                    new OA\Property(property: 'project_ids[]', type: 'array', items: new OA\Items(type: 'integer'), example: [3, 7]),
                    new OA\Property(
                        property: 'photos[]',
                        type: 'array',
                        items: new OA\Items(type: 'string', format: 'binary'),
                        description: 'Upload multiple : photos[0]=photo1.jpg, photos[1]=photo2.jpg'
                    ),
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
                    new OA\Property(property: 'latitude', type: 'number', format: 'float', nullable: true, example: 48.8566, description: 'Optionnel. Doit être fourni avec longitude. Plage: -90 à 90.'),
                    new OA\Property(property: 'longitude', type: 'number', format: 'float', nullable: true, example: 2.3522, description: 'Optionnel. Doit être fourni avec latitude. Plage: -180 à 180.'),
                    new OA\Property(property: 'securityMode', type: 'string', nullable: true, example: 'ARMOIRE', description: 'Mode de sécurisation du bien. Si fourni, crée automatiquement une sécurisation avec la date du jour.'),
                ]
            )
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Bien créé',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 201,
                'message' => 'Bien créé avec succès.',
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
    #[OA\Response(response: 400, description: 'Validation', content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => ['fournisseurEmail' => "L'email du fournisseur n'est pas valide."]]))]
    #[OA\Response(
        response: 401,
        description: 'Non authentifié',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null])
    )]
    #[OA\Response(response: 404, description: 'Relation introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'La catégorie demandée est introuvable.', 'data' => null]))]
    #[OA\Response(response: 409, description: 'Conflit — référence déjà utilisée', content: new OA\JsonContent(example: ['success' => false, 'status' => 409, 'message' => 'Cette référence de bien est déjà utilisée.', 'data' => ['reference' => 'Cette référence de bien est déjà utilisée.']]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        Request $request,
        AssetManagementService $assetManagementService,
        AssetResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse,
        #[CurrentUser] ?User $currentUser
    ): JsonResponse {
        $payload = $request->request->all();
        $this->normalizeArrayFields($payload);

         // ✅ Récupérer et parser les champs existants
        $champsExistants = $this->parseChampsExistants($payload['champs_existants'] ?? null);

        $champsValeurs = $this->parseChampsValeurs($payload['champs_valeurs'] ?? null);
        
        // ✅ Récupérer et parser les nouveaux champs (supporte CSV et JSON)
        $champsNouveaux = $this->parseChampsNouveaux($payload['champs_nouveaux'] ?? null);


        $photos = UploadedFilesNormalizer::fromRequest($request, 'photos');
        $documents = UploadedFilesNormalizer::fromRequest($request, 'piecesJointes');
        if ([] === $documents) {
            $documents = UploadedFilesNormalizer::fromRequest($request, 'documents');
        }
        $documentLabels = UploadedFilesNormalizer::nullableStringListFromRequest($request, 'piecesJointesNoms');
        if ([] === $documentLabels) {
            $documentLabels = UploadedFilesNormalizer::nullableStringListFromRequest($request, 'documents_labels');
        }

        try {
            $asset = $assetManagementService->create($payload, $photos, $documents, $documentLabels, $champsExistants, $champsNouveaux, $champsValeurs, $currentUser);
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
            Response::HTTP_CREATED,
            'Bien créé avec succès.'
        );
    }

     /**
     * ✅ Parse les champs existants (supporte JSON et CSV)
     * @param mixed $value
     * @return array<int>
     */
    private function parseChampsExistants($value): array
    {
        if (null === $value || '' === $value) {
            return [];
        }

        if (is_array($value)) {
            return array_map('intval', $value);
        }

        if (is_int($value)) {
            return [$value];
        }

        if (is_string($value)) {
            // Si c'est un nombre en string
            if (is_numeric($value)) {
                return [(int) $value];
            }
            
            // Si c'est du JSON
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_map('intval', $decoded);
            }
            
            // Si c'est une liste séparée par des virgules
            if (str_contains($value, ',')) {
                return array_map('intval', array_map('trim', explode(',', $value)));
            }
        }

        return [];
    }

    /**
     * ✅ Parse les valeurs à ajouter à des champs existants
     * Format: "1:Rouge;2:150 m²;3:Bois"
     * @param mixed $value
     * @return array<array{champ_id: int, valeur: string}>
     */
    private function parseChampsValeurs($value): array
    {
        if (null === $value || '' === $value) {
            return [];
        }

        $result = [];

        // Si c'est une chaîne
        if (is_string($value)) {
            // Format JSON
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

            // Format CSV: "1:Rouge;2:150 m²"
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

        // Si c'est déjà un tableau
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
     * ✅ Parse les nouveaux champs (supporte JSON et format CSV)
     * @param mixed $value
     * @return array<array{nom: string, valeur: string}>
     */
    private function parseChampsNouveaux($value): array
    {
        if (null === $value || '' === $value) {
            return [];
        }

        // Si c'est déjà un tableau (format JSON)
        if (is_array($value)) {
            foreach ($value as $item) {
                if (!isset($item['nom']) || !isset($item['valeur'])) {
                    return [];
                }
            }
            return $value;
        }

        // Si c'est une chaîne
        if (is_string($value)) {
            // ✅ ESSAYER LE FORMAT JSON D'ABORD
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (isset($item['nom']) && isset($item['valeur'])) {
                        return $decoded;
                    }
                }
            }

            // ✅ FORMAT CSV : "nom:valeur;nom:valeur;nom:valeur"
            if (str_contains($value, ';') || str_contains($value, ':')) {
                $result = [];
                $pairs = explode(';', $value);
                foreach ($pairs as $pair) {
                    $parts = explode(':', $pair, 2);
                    if (count($parts) === 2) {
                        $nom = trim($parts[0]);
                        $valeur = trim($parts[1]);
                        if (!empty($nom) && !empty($valeur)) {
                            $result[] = ['nom' => $nom, 'valeur' => $valeur];
                        }
                    }
                }
                return $result;
            }
        }

        return [];
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
