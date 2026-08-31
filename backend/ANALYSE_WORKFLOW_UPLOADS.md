# 📋 ANALYSE COMPLÈTE - Workflow Upload Assets

**Date**: 2 août 2026  
**Statut**: ✅ Analyse terminée - Prêt pour correction

---

## 1. WORKFLOW POST /assets (Création) - RÉFÉRENCE

### Étape 1: CreateAssetController.__invoke()

```php
public function __invoke(
    Request $request,
    AssetManagementService $assetManagementService,
    AssetResponseBuilder $responseBuilder,
    ApiResponseFactory $apiResponse
) {
    // 1. Récupère le payload
    $payload = $request->request->all();
    
    // 2. Normalise les champs array (CSV, variantes avec [])
    $this->normalizeArrayFields($payload);
    
    // 3. Extrait les fichiers photos (multipart/form-data)
    $photos = UploadedFilesNormalizer::fromRequest($request, 'photos');
    
    // 4. Extrait les documents (piecesJointes)
    $documents = UploadedFilesNormalizer::fromRequest($request, 'piecesJointes');
    if ([] === $documents) {
        // Fallback pour compatibilité
        $documents = UploadedFilesNormalizer::fromRequest($request, 'documents');
    }
    
    // 5. Extrait les noms des documents
    $documentLabels = UploadedFilesNormalizer::nullableStringListFromRequest($request, 'piecesJointesNoms');
    if ([] === $documentLabels) {
        // Fallback pour compatibilité
        $documentLabels = UploadedFilesNormalizer::nullableStringListFromRequest($request, 'documents_labels');
    }
    
    // 6. Appelle le service métier
    $asset = $assetManagementService->create($payload, $photos, $documents, $documentLabels);
    
    // 7. Retourne la réponse
    return $apiResponse->success(
        $responseBuilder->buildDetail($asset),
        Response::HTTP_CREATED,
        'Bien créé avec succès.'
    );
}
```

**Point clé**: La normalisation des arrays est une étape CRUCIALE qui gère:
- Chaînes CSV: `"1,2,3"` → `[1, 2, 3]`
- Variantes avec brackets: `project_ids[]` → `project_ids`
- Valeurs simples: `1` → `[1]`

### Étape 2: CreateAssetController.normalizeArrayFields()

```php
private function normalizeArrayFields(array &$payload): void
{
    foreach (['project_ids', 'category_ids', 'asset_type_ids', 'etat_bien_ids', 'service_ids'] as $key) {
        if (isset($payload[$key]) && !is_array($payload[$key])) {
            // Gère CSV: "1,2,3" → array
            if (is_string($payload[$key]) && str_contains($payload[$key], ',')) {
                $payload[$key] = array_map('trim', explode(',', $payload[$key]));
            } else {
                // Gère valeur simple: 1 → [1]
                $payload[$key] = [$payload[$key]];
            }
        }
        
        // Gère variante bracket: project_ids[] → project_ids
        $bracket = $key . '[]';
        if (isset($payload[$bracket])) {
            $payload[$key] = is_array($payload[$bracket]) ? $payload[$bracket] : [$payload[$bracket]];
            unset($payload[$bracket]);
        }
    }
}
```

### Étape 3: UploadedFilesNormalizer.fromRequest()

Extrait les fichiers uploadés d'une clé (photos, piecesJointes):
- Gère toutes les variantes de clés (photos[], photos, etc.)
- Retourne array<UploadedFile>
- Filtre les doublons
- Ignore les erreurs UPLOAD_ERR_NO_FILE

```php
public static function fromRequest(Request $request, string $field): array
{
    // $field = 'photos' ou 'piecesJointes'
    // Retourne tous les fichiers uploadés sous cette clé
}
```

### Étape 4: UploadedFilesNormalizer.nullableStringListFromRequest()

Extrait les noms personnalisés des documents:
- Gère les chaînes CSV (Swagger UI envoie parfois une seule chaîne)
- Gère les variantes de clés (piecesJointesNoms[], piecesJointesNoms, etc.)
- Parse les CSV automatiquement
- Trim et nettoie les valeurs
- Retourne array<string|null> indexée

```php
public static function nullableStringListFromRequest(Request $request, string $field): array
{
    // Retourne ['Facture d\'achat', 'Bon de livraison']
    // Ou [] si aucun nom fourni
}
```

### Étape 5: AssetManagementService.create()

```php
public function create(array $payload, array $photos = [], array $documents = [], array $documentLabels = []): Asset
{
    $asset = new Asset();
    // ... (initialisation)
    
    $this->applyPayload($asset, $payload);  // Applique tous les champs métier
    $this->attachFiles($asset, $photos, $documents, $documentLabels);  // Ajoute les fichiers
    $this->assertValid($asset);  // Valide l'entité
    $this->assetRepository->save($asset);  // Persiste
    
    return $asset;
}
```

### Étape 6: AssetManagementService.attachFiles() - LE CŒUR

```php
private function attachFiles(Asset $asset, array $photos, array $documents, array $documentLabels): void
{
    // Normalise les labels (gère CSV Swagger)
    $documentLabels = UploadedFilesNormalizer::parseLabelList($documentLabels);
    
    // ===== PHOTOS =====
    foreach ($photos as $photo) {
        if (!$photo instanceof UploadedFile) continue;
        if (UPLOAD_ERR_NO_FILE === $photo->getError()) continue;
        if (!$photo->isValid()) {
            throw new ValidationFailedException(['photos' => '...']);
        }
        
        // Appelle le service d'upload
        $piece = $this->fileUploadService->upload(
            $photo,
            FileUploadService::KIND_PHOTO,
            $photo->getClientOriginalName(),  // Nom de la photo
            false
        );
        
        // IMPORTANT: Ajoute à l'asset (ne remplace pas)
        $asset->addPieceJointe($piece);
    }
    
    // ===== DOCUMENTS =====
    foreach (array_values($documents) as $index => $document) {
        if (!$document instanceof UploadedFile) continue;
        if (UPLOAD_ERR_NO_FILE === $document->getError()) continue;
        if (!$document->isValid()) {
            throw new ValidationFailedException(['piecesJointes' => '...']);
        }
        
        // Récupère le label à l'index correspondant
        $label = $documentLabels[$index] ?? null;
        
        // Si pas de label ou vide, utilise le nom original du fichier
        if (null === $label || '' === trim((string) $label)) {
            $label = $document->getClientOriginalName();
        }
        
        // Appelle le service d'upload
        $piece = $this->fileUploadService->upload(
            $document,
            FileUploadService::KIND_DOCUMENT,
            (string) $label,  // Nom personnalisé ou original
            false
        );
        
        // IMPORTANT: Ajoute à l'asset (ne remplace pas)
        $asset->addPieceJointe($piece);
    }
}
```

**Points clés**:
- **addPieceJointe() ajoute**, il ne remplace pas
- Les labels sont indexés et doivent correspondre aux documents
- Si pas de label, c'est le nom original du fichier
- Valide chaque fichier avant d'uploader

---

## 2. SWAGGER DE POST /assets - DOCUMENTATION RÉFÉRENCE

### RequestBody

```yaml
multipart/form-data:
  schema:
    type: object
    properties:
      photos[]:
        type: array
        items:
          type: string
          format: binary
        description: 'Upload multiple : photos[0]=photo1.jpg, photos[1]=photo2.jpg'
      
      piecesJointes[]:
        type: array
        items:
          type: string
          format: binary
        description: 'Upload multiple : piecesJointes[0]=facture.pdf, piecesJointes[1]=garantie.pdf'
      
      piecesJointesNoms[]:
        type: array
        items:
          type: string
        description: |
          Un nom par document, même index. 
          Ex. piecesJointesNoms[0]=Facture d'achat, piecesJointesNoms[1]=Bon de livraison.
          Si omis → nom original du fichier.
          Swagger peut aussi envoyer une seule chaîne CSV qui sera découpée automatiquement.
        example: ["Facture d'achat", "Bon de livraison"]
```

### Exemple de Requête Swagger

```
POST /assets

Content-Type: multipart/form-data

nom=Ordinateur Portable
valeur=850000
category_id=2
asset_type_id=5
photos[0]=@photo1.jpg
photos[1]=@photo2.jpg
piecesJointes[0]=@facture.pdf
piecesJointes[1]=@bon_livraison.pdf
piecesJointesNoms[0]=Facture d'achat
piecesJointesNoms[1]=Bon de livraison
```

---

## 3. PROBLÈMES DANS UpdateAssetController

### Problème 1: normalizeArrayFields() est incomplète

**Actuellement dans UpdateAssetController**:
```php
foreach (['project_ids', ...] as $key) {
    if (isset($payload[$key]) && !is_array($payload[$key])) {
        $payload[$key] = [$payload[$key]];  // ❌ Gère pas CSV
    }
    // ❌ Pas de gestion des variantes avec []
}
```

**Devrait être** (comme dans CreateAssetController):
```php
if (is_string($payload[$key]) && str_contains($payload[$key], ',')) {
    $payload[$key] = array_map('trim', explode(',', $payload[$key]));
} else {
    $payload[$key] = [$payload[$key]];
}

$bracket = $key . '[]';
if (isset($payload[$bracket])) {
    $payload[$key] = is_array($payload[$bracket]) ? $payload[$bracket] : [$payload[$bracket]];
    unset($payload[$bracket]);
}
```

### Problème 2: Normalisation pas appliquée pour JSON

**Actuellement**:
```php
if (str_contains($contentType, 'application/json')) {
    $payload = json_decode($request->getContent(), true);
    // ❌ PAS d'appel à normalizeArrayFields()
}
```

**Devrait être**:
```php
if (str_contains($contentType, 'application/json')) {
    $payload = json_decode($request->getContent(), true);
    $this->normalizeArrayFields($payload);  // ✅ AJOUTER
}
```

### Problème 3: Swagger incomplète

La documentation Swagger de PATCH n'inclut pas:
- Tous les champs de POST (numeroSerie, dateAcquisition, sourceFinancement, etc.)
- Description complète multipart/form-data comme POST
- Exemple de réponse complète avec photos et piecesJointes

---

## 4. SOLUTION - CE QUE JE VAIS FAIRE

### Modification 1: Ajouter normalizeArrayFields() à UpdateAssetController

Copier la méthode de CreateAssetController telle quelle.

### Modification 2: Utiliser normalizeArrayFields()

- Pour multipart: `$this->normalizeArrayFields($payload);`
- Pour JSON: `$this->normalizeArrayFields($payload);`

### Modification 3: Mettre à jour la Swagger

- Inclure TOUS les champs de POST (pas seulement quelques-uns)
- Inclure description multipart complète
- Inclure exemple de réponse complète
- Inclure tous les cas d'erreur

### Modification 4: Verifier que le code métier est identique

- CreateAssetController appelle: `create($payload, $photos, $documents, $documentLabels)`
- UpdateAssetController appelle: `update($asset, $payload, $photos, $documents, $documentLabels)`
- Tous deux utilisent: `attachFiles()` qui ajoute les fichiers

✅ C'est déjà correct!

---

## 5. VÉRIFICATION PRÉ-MODIFICATION

### Fichier à modifier
- `src/Controller/Assets/UpdateAssetController.php`

### Ce qui ne doit PAS changer
- La logique des services (AssetManagementService, FileUploadService)
- La méthode attachFiles() 
- Le comportement d'ajout de fichiers (addPieceJointe())
- Les exceptions gérées

### Ce qui DOIT changer
- Ajouter la méthode `normalizeArrayFields()` identique à CreateAssetController
- Appeler cette méthode pour JSON ET multipart
- Mettre à jour la documentation Swagger

---

**Status**: ✅ Analyse complète - Prêt pour implémentation
