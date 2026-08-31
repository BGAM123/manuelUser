# 🧪 PLAN DE TEST - Cas d'Usage PATCH /assets

**Objectif**: Vérifier que PATCH /assets reproduit EXACTEMENT le même comportement que POST /assets

**Prérequis**: 
- Serveur en cours d'exécution (Symfony dev)
- Au moins 1 asset existant (pour PATCH)
- Swagger UI accessible

---

## Cas 1: Modification sans fichiers

**Objectif**: Vérifier que la modification des métadonnées seules fonctionne (sans upload)

**Requête**:
```http
PATCH /assets/{id}
Content-Type: application/json

{
  "nom": "Nouveau nom",
  "valeur": 950000,
  "description": "Mise à jour de la valeur"
}
```

**Attentes**:
- ✅ Code 200
- ✅ Asset mis à jour avec les champs fournis
- ✅ Les anciennes photos/documents conservés
- ✅ Les fields non fournis inchangés

---

## Cas 2: Ajout d'une seule photo

**Objectif**: Vérifier que l'ajout d'une photo unique fonctionne

**Requête** (multipart/form-data):
```
PATCH /assets/{id}
Content-Type: multipart/form-data

nom=Photo ajoutée
photos[0]=@photo_nouvelle.jpg
```

**Attentes**:
- ✅ Code 200
- ✅ La nouvelle photo est dans la réponse
- ✅ Les anciennes photos sont conservées
- ✅ nom=Photo ajoutée est appliqué
- ✅ PieceJointe.kind = 'photo'
- ✅ PieceJointe.nom = 'photo_nouvelle.jpg' (nom original du fichier)

---

## Cas 3: Ajout de multiples photos

**Objectif**: Vérifier que plusieurs photos peuvent être ajoutées simultanément

**Requête** (multipart/form-data):
```
PATCH /assets/{id}
Content-Type: multipart/form-data

photos[0]=@photo1.jpg
photos[1]=@photo2.jpg
photos[2]=@photo3.jpg
```

**Attentes**:
- ✅ Code 200
- ✅ Les 3 photos sont dans la réponse
- ✅ Les anciennes photos sont conservées
- ✅ Chaque PieceJointe.nom = nom du fichier original
- ✅ Toutes les PieceJointe.kind = 'photo'
- ✅ Ordre préservé: photo1, photo2, photo3

---

## Cas 4: Ajout d'un document avec nom personnalisé

**Objectif**: Vérifier que piecesJointesNoms[i] remplace le nom du fichier

**Requête** (multipart/form-data):
```
PATCH /assets/{id}
Content-Type: multipart/form-data

piecesJointes[0]=@facture_client_2026.pdf
piecesJointesNoms[0]=Facture d'achat
```

**Attentes**:
- ✅ Code 200
- ✅ 1 PieceJointe dans la réponse
- ✅ PieceJointe.nom = "Facture d'achat" (pas "facture_client_2026.pdf")
- ✅ PieceJointe.kind = 'document'
- ✅ Les anciens documents conservés

---

## Cas 5: Ajout de multiples documents avec noms personnalisés

**Objectif**: Vérifier que piecesJointesNoms[i] aligne correctement avec piecesJointes[i]

**Requête** (multipart/form-data):
```
PATCH /assets/{id}
Content-Type: multipart/form-data

piecesJointes[0]=@file1.pdf
piecesJointes[1]=@file2.pdf
piecesJointes[2]=@file3.pdf
piecesJointesNoms[0]=Facture d'achat
piecesJointesNoms[1]=Bon de livraison
piecesJointesNoms[2]=Garantie constructeur
```

**Attentes**:
- ✅ Code 200
- ✅ 3 PieceJointe dans la réponse
- ✅ PieceJointe[0].nom = "Facture d'achat"
- ✅ PieceJointe[1].nom = "Bon de livraison"
- ✅ PieceJointe[2].nom = "Garantie constructeur"
- ✅ Tous les PieceJointe.kind = 'document'
- ✅ Ordre préservé
- ✅ Les anciens documents conservés

---

## Cas 6: Ajout d'un document sans nom personnalisé

**Objectif**: Vérifier que sans piecesJointesNoms, le nom du fichier est utilisé

**Requête** (multipart/form-data):
```
PATCH /assets/{id}
Content-Type: multipart/form-data

piecesJointes[0]=@mon_document.pdf
```

**Attentes**:
- ✅ Code 200
- ✅ 1 PieceJointe dans la réponse
- ✅ PieceJointe.nom = "mon_document.pdf" (nom original du fichier)
- ✅ PieceJointe.kind = 'document'
- ✅ Les anciens documents conservés

---

## Cas 7: Vérification que les anciens fichiers sont conservés

**Objectif**: Vérifier que addPieceJointe() ajoute, ne remplace pas

**Étapes**:
1. Récupérer l'asset AVANT (GET /assets/{id})
2. Noter le nombre de photos/documents: ex. 2 photos
3. Appliquer PATCH avec 1 nouvelle photo
4. Récupérer l'asset APRÈS (GET /assets/{id})
5. Vérifier le nombre: doit être 3 photos (2 + 1)

**Requête** (multipart/form-data):
```
PATCH /assets/{id}
Content-Type: multipart/form-data

photos[0]=@nouvelle_photo.jpg
```

**Attentes**:
- ✅ AVANT: asset.photos.length = 2
- ✅ APRÈS: asset.photos.length = 3
- ✅ La nouvelle photo est la 3ème (index 2)
- ✅ Les 2 anciennes photos sont inchangées (mêmes chemins, mêmes noms)
- ✅ Les documents ne sont pas affectés

---

## Cas 8 (Bonus): CSV Swagger pour piecesJointesNoms

**Objectif**: Vérifier que CSV est automatiquement découpé

**Requête** (multipart/form-data via Swagger):
```
PATCH /assets/{id}

piecesJointes[0]=@file1.pdf
piecesJointes[1]=@file2.pdf
piecesJointesNoms[0]=Facture d'achat,Bon de livraison
```

**Attentes**:
- ✅ Code 200
- ✅ PieceJointe[0].nom = "Facture d'achat"
- ✅ PieceJointe[1].nom = "Bon de livraison"
- ✅ (Alternative: si Swagger envoie 1 seul piecesJointesNoms avec CSV)

---

## Cas 9 (Bonus): Variantes de syntaxe array

**Objectif**: Vérifier que project_ids accepte CSV, [], et valeurs simples

**Requête 1** (CSV):
```json
PATCH /assets/{id}
Content-Type: application/json

{ "project_ids": "1,2,3" }
```

**Requête 2** (Array JSON):
```json
PATCH /assets/{id}
Content-Type: application/json

{ "project_ids": [1, 2, 3] }
```

**Requête 3** (Valeur simple):
```json
PATCH /assets/{id}
Content-Type: application/json

{ "project_ids": 1 }
```

**Attentes**:
- ✅ Cas 1: CSV découpé en [1, 2, 3]
- ✅ Cas 2: Array préservé [1, 2, 3]
- ✅ Cas 3: Valeur simple convertie en [1]
- ✅ Tous les cas créent les mêmes relations project

---

## Résumé des Validations

| Cas | Description | Type | Priorité |
|-----|-------------|------|----------|
| 1 | Modification sans fichiers | JSON | ⭐⭐⭐ |
| 2 | Ajout 1 photo | multipart | ⭐⭐⭐ |
| 3 | Ajout multiples photos | multipart | ⭐⭐⭐ |
| 4 | Document + nom personnalisé | multipart | ⭐⭐⭐ |
| 5 | Multiples documents + noms | multipart | ⭐⭐⭐ |
| 6 | Document sans nom personnalisé | multipart | ⭐⭐ |
| 7 | Conservation des anciens fichiers | POST/GET | ⭐⭐⭐ |
| 8 | CSV Swagger | multipart | ⭐⭐ |
| 9 | Variantes project_ids | JSON | ⭐⭐ |

---

## Exécution des Tests

### Via cURL

```bash
# Cas 1: JSON modification
curl -X PATCH http://localhost:8000/assets/1 \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"nom":"Test", "valeur": 1000}'

# Cas 2: Photo simple
curl -X PATCH http://localhost:8000/assets/1 \
  -H "Authorization: Bearer $TOKEN" \
  -F "photos[0]=@/path/to/photo.jpg"

# Cas 4: Document + nom
curl -X PATCH http://localhost:8000/assets/1 \
  -H "Authorization: Bearer $TOKEN" \
  -F "piecesJointes[0]=@/path/to/file.pdf" \
  -F "piecesJointesNoms[0]=Facture d'achat"
```

### Via Swagger UI

1. Ouvrir http://localhost:8000/api/doc
2. Chercher "PATCH /assets/{id}"
3. Cliquer "Try it out"
4. Remplir les champs
5. Cliquer "Execute"
6. Vérifier code 200 et données dans réponse

---

**Status**: Tests prêts à exécuter
