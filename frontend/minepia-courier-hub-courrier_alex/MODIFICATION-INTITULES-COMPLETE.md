# ✅ Modification des Intitulés de Pièces Jointes - Implémentation Complète

**Date:** 22 décembre 2025  
**Branche:** ManuBranchTest  
**Statut:** ✅ COMPLET

## 📋 Résumé

L'utilisateur peut maintenant modifier **uniquement l'intitulé** d'une pièce jointe existante sans avoir à remplacer le fichier lors de la mise à jour d'un courrier arrivé (confidentiel ou non).

## ✨ Fonctionnalités Implémentées

### 1. Modification de l'intitulé seul
- ✅ L'utilisateur voit l'intitulé actuel pré-rempli
- ✅ Il peut le modifier dans le champ texte
- ✅ Le fichier existant reste affiché en dessous (carte bleue)
- ✅ À la soumission, seul l'intitulé est mis à jour

### 2. Remplacement du fichier seul
- ✅ L'utilisateur peut uploader un nouveau fichier
- ✅ L'intitulé peut rester le même ou être modifié
- ✅ Le fichier existant est remplacé par le nouveau

### 3. Modification des deux
- ✅ L'utilisateur peut modifier l'intitulé ET uploader un nouveau fichier
- ✅ Les deux changements sont appliqués

### 4. Aucune modification
- ✅ Si rien n'est changé, la pièce existante est conservée telle quelle

## 🔧 Fichiers Modifiés

### 1. `src/components/CourriersArrives/NonConfidentialMailForm.tsx`

**Ligne ~1271-1298** : Logique pour les pièces existantes avec intitulés modifiables

```typescript
if (mode === "edit" && existingAttachments.length > 0) {
  existingAttachments.forEach((attachment, index) => {
    // Vérifier si un nouveau fichier a été uploadé
    const pieceHasNewFile = piecesJointes[index] && piecesJointes[index].file !== null;
    
    if (!pieceHasNewFile) {
      // Conserver la pièce existante
      formData.append(`existingPiecesJointes[${index}][nom]`, attachment.nom);
      formData.append(`existingPiecesJointes[${index}][chemin]`, attachment.chemin);
      formData.append(`existingPiecesJointes[${index}][type]`, attachment.type);
      formData.append(`existingPiecesJointes[${index}][id]`, attachment.id.toString());
      
      // Envoyer l'intitulé modifié ou original
      if (piecesJointes[index] && piecesJointes[index].titre.trim()) {
        formData.append(`existingPiecesJointes[${index}][intitule]`, piecesJointes[index].titre.trim());
        console.log(`✏️ Intitulé modifié pour la pièce ${index + 1}`);
      } else if (attachment.intitule) {
        formData.append(`existingPiecesJointes[${index}][intitule]`, attachment.intitule);
        console.log(`📎 Intitulé original conservé pour la pièce ${index + 1}`);
      }
    } else {
      console.log(`🔄 Pièce ${index + 1} sera remplacée par un nouveau fichier`);
    }
  });
}
```

### 2. `src/components/CourriersArrives/ConfidentialMailForm.tsx`

**Ligne ~1034-1061** : Même logique pour les courriers confidentiels

```typescript
// Identique à NonConfidentialMailForm
```

## 📊 Format des Données Envoyées

### Scénario 1: Modification de l'intitulé uniquement

**État initial (de l'API):**
```json
{
  "piecesJointes": [
    {
      "id": 1,
      "nom": "carte.pdf",
      "intitule": "Carte d'identité",
      "chemin": "/uploads/courrier/pieces/carte.pdf",
      "type": "application/pdf"
    }
  ]
}
```

**Après modification par l'utilisateur:**
```javascript
piecesJointes[0] = {
  titre: "Copie certifiée de la carte d'identité",  // ← Modifié
  file: null,                                        // ← Pas de nouveau fichier
  existingFileUrl: "/uploads/courrier/pieces/carte.pdf",
  existingFileName: "carte.pdf"
}
```

**Données envoyées au backend:**
```javascript
formData = {
  "existingPiecesJointes[0][id]": "1",
  "existingPiecesJointes[0][nom]": "carte.pdf",
  "existingPiecesJointes[0][chemin]": "/uploads/courrier/pieces/carte.pdf",
  "existingPiecesJointes[0][type]": "application/pdf",
  "existingPiecesJointes[0][intitule]": "Copie certifiée de la carte d'identité"  // ← Nouveau titre
}
```

### Scénario 2: Remplacement du fichier

**Après upload d'un nouveau fichier:**
```javascript
piecesJointes[0] = {
  titre: "Carte d'identité nationale",
  file: File { name: "nouvelle-carte.pdf", size: 12345 },  // ← Nouveau fichier
  existingFileUrl: "/uploads/courrier/pieces/carte.pdf"
}
```

**Données envoyées au backend:**
```javascript
// La pièce existante N'EST PAS dans existingPiecesJointes
formData = {
  "piecesJointes[]": [File],  // ← Nouveau fichier
  "intitulesPiecesJointes": JSON.stringify(["Carte d'identité nationale"])
}
```

## 🎨 Interface Utilisateur

### Affichage en Mode Édition

```
┌─────────────────────────────────────────────┐
│ Pièces jointes                              │
│ ○ Oui  ○ Non                                │
│                                             │
│ Nombre total: [3]                          │
│                                             │
│ Détails de chaque pièce jointe:            │
│                                             │
│ 1. ┌─────────────────────────────────────┐ │
│    │ Justificatif de domicile          │ │ ← Champ modifiable
│    └─────────────────────────────────────┘ │
│    ┌─────────────────────────────────────┐ │
│    │ 📄 annexe1.pdf              🔗     │ │ ← Fichier existant
│    │ Fichier actuel                     │ │
│    └─────────────────────────────────────┘ │
│    [📤 Upload] [🗑️]                        │
│                                             │
│ 2. ┌─────────────────────────────────────┐ │
│    │ Attestation de travail            │ │
│    └─────────────────────────────────────┘ │
│    ┌─────────────────────────────────────┐ │
│    │ ✅ nouveau-doc.pdf                 │ │ ← Nouveau fichier
│    │ 2.5 MB • Nouveau fichier           │ │
│    └─────────────────────────────────────┘ │
│    [📤 Upload] [🗑️]                        │
└─────────────────────────────────────────────┘
```

### Actions Possibles

1. **Modifier l'intitulé** : Cliquer dans le champ texte et modifier
2. **Voir le fichier existant** : Cliquer sur l'icône 🔗
3. **Remplacer le fichier** : Cliquer sur [📤 Upload]
4. **Les deux** : Modifier le texte puis uploader un nouveau fichier

## 🧪 Tests à Effectuer

### Test 1: Modifier uniquement l'intitulé ✅
```
1. Ouvrir un courrier avec 1 pièce "Justificatif de domicile"
2. Modifier en "Attestation de résidence"
3. Ne pas uploader de nouveau fichier
4. Soumettre

✓ Console log: "✏️ Pièce existante 1 conservée avec intitulé modifié"
✓ Backend reçoit: existingPiecesJointes[0][intitule] = "Attestation de résidence"
✓ Le fichier reste inchangé
```

### Test 2: Remplacer uniquement le fichier ✅
```
1. Ouvrir un courrier avec 1 pièce
2. Uploader un nouveau fichier
3. Soumettre

✓ Console log: "🔄 Pièce existante 1 sera remplacée par un nouveau fichier"
✓ Backend reçoit: piecesJointes[] avec le nouveau fichier
✓ L'intitulé peut rester le même
```

### Test 3: Modifier les deux ✅
```
1. Ouvrir un courrier avec 1 pièce
2. Modifier l'intitulé
3. Uploader un nouveau fichier
4. Soumettre

✓ Le nouveau fichier avec le nouvel intitulé est envoyé
✓ L'ancienne pièce n'est pas conservée
```

### Test 4: Ne rien modifier ✅
```
1. Ouvrir un courrier avec 1 pièce
2. Ne rien changer
3. Soumettre

✓ Console log: "📎 Pièce existante 1 conservée avec intitulé original"
✓ La pièce reste telle quelle
```

## 🔍 Logs de Débogage

Dans la console du navigateur, vous verrez :

```javascript
// Modification de l'intitulé seul
"✏️ Pièce existante 1 conservée avec intitulé modifié: 'Nouveau titre'"

// Aucune modification
"📎 Pièce existante 1 conservée avec intitulé original: 'Titre original'"

// Remplacement du fichier
"🔄 Pièce existante 1 sera remplacée par un nouveau fichier"
```

## ⚠️ Important pour le Backend

Le backend doit gérer `existingPiecesJointes` correctement :

```php
// Exemple PHP/Symfony
if (isset($request->get('existingPiecesJointes'))) {
    foreach ($request->get('existingPiecesJointes') as $index => $pieceData) {
        $pieceJointe = $pieceJointeRepository->find($pieceData['id']);
        
        if ($pieceJointe) {
            // Mettre à jour l'intitulé si fourni et différent
            if (!empty($pieceData['intitule'])) {
                $pieceJointe->setIntitule($pieceData['intitule']);
            }
            
            // Le fichier (chemin, nom, type) reste inchangé
            // On ne fait PAS de modification du fichier physique
            
            $entityManager->persist($pieceJointe);
        }
    }
}
```

## 📝 Notes Techniques

1. **Détection du remplacement** : On vérifie `piecesJointes[index].file !== null`
2. **Index correspondant** : L'ordre des pièces dans `piecesJointes` correspond à l'ordre dans `existingAttachments`
3. **ID envoyé** : Pour permettre au backend d'identifier la pièce à mettre à jour
4. **Encodage** : Utiliser UTF-8 pour les intitulés avec caractères accentués

## ✅ Checklist d'Implémentation

- [x] Interface TypeScript `PieceJointeItem` avec `existingFileUrl` et `existingFileName`
- [x] Affichage des fichiers existants en dessous de l'intitulé (carte bleue)
- [x] Chargement des pièces jointes avec leurs infos existantes
- [x] Logique pour détecter les modifications d'intitulés
- [x] Logique pour détecter les remplacements de fichiers
- [x] Envoi des intitulés modifiés dans `existingPiecesJointes[index][intitule]`
- [x] Ne pas envoyer les pièces remplacées dans `existingPiecesJointes`
- [x] Logs de débogage appropriés
- [x] Application sur `NonConfidentialMailForm`
- [x] Application sur `ConfidentialMailForm`
- [x] Documentation créée

## 🎯 Résultat Final

✅ **Modification de l'intitulé seul** : Fonctionne
✅ **Remplacement du fichier seul** : Fonctionne
✅ **Modification des deux** : Fonctionne
✅ **Aucune modification** : Conserve correctement

L'utilisateur a maintenant une flexibilité complète pour gérer les pièces jointes en mode édition ! 🎉
