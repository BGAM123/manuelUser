# Modification de l'Intitulé d'une Pièce Jointe sans Changer le Fichier

**Date:** 22 décembre 2025  
**Statut:** ✅ IMPLÉMENTÉ

## 📋 Fonctionnalité

L'utilisateur peut maintenant modifier **uniquement l'intitulé** d'une pièce jointe existante sans avoir à remplacer le fichier.

## 🔧 Modifications Apportées

### 1. NonConfidentialMailForm.tsx

**Ligne ~1271-1290** : Ajout de la logique pour gérer les intitulés modifiés

```typescript
// En mode édition, envoyer les chemins des pièces jointes existantes à conserver
// ET leurs intitulés (qui peuvent avoir été modifiés)
// NOTE: On ne conserve que les pièces qui n'ont PAS été remplacées par un nouveau fichier
if (mode === "edit" && existingAttachments.length > 0) {
  existingAttachments.forEach((attachment, index) => {
    // Si l'utilisateur a uploadé un nouveau fichier pour cette position, on ne conserve pas l'ancien
    const pieceHasNewFile = piecesJointes[index] && piecesJointes[index].file !== null;
    
    if (!pieceHasNewFile) {
      // Pas de nouveau fichier, on conserve la pièce existante
      formData.append(`existingPiecesJointes[${index}][nom]`, attachment.nom);
      formData.append(`existingPiecesJointes[${index}][chemin]`, attachment.chemin);
      formData.append(`existingPiecesJointes[${index}][type]`, attachment.type);
      formData.append(`existingPiecesJointes[${index}][id]`, attachment.id.toString());
      
      // Si l'intitulé a été modifié dans piecesJointes, on l'envoie
      if (piecesJointes[index] && piecesJointes[index].titre.trim()) {
        formData.append(`existingPiecesJointes[${index}][intitule]`, piecesJointes[index].titre.trim());
        console.log(`✏️ Pièce existante ${index + 1} conservée avec intitulé modifié: "${piecesJointes[index].titre.trim()}"`);
      } else if (attachment.intitule) {
        // Sinon, conserver l'intitulé original
        formData.append(`existingPiecesJointes[${index}][intitule]`, attachment.intitule);
        console.log(`📎 Pièce existante ${index + 1} conservée avec intitulé original: "${attachment.intitule}"`);
      }
    } else {
      console.log(`🔄 Pièce existante ${index + 1} sera remplacée par un nouveau fichier`);
    }
  });
}
```

### 2. Logique de Fonctionnement

#### Cas 1: Modification de l'intitulé uniquement
```
État initial (de l'API):
  piecesJointes[0] = { intitule: "Carte d'identité", chemin: "/uploads/carte.pdf" }

Après modification par l'utilisateur:
  piecesJointes[0] = { 
    titre: "Copie certifiée de la carte d'identité",  // Nouveau titre
    file: null,                                        // Pas de nouveau fichier
    existingFileUrl: "/uploads/carte.pdf"              // Fichier existant
  }

Envoi au backend:
  existingPiecesJointes[0][intitule] = "Copie certifiée de la carte d'identité"
  existingPiecesJointes[0][chemin] = "/uploads/carte.pdf"
  existingPiecesJointes[0][nom] = "carte.pdf"
  existingPiecesJointes[0][type] = "application/pdf"
  existingPiecesJointes[0][id] = "1"
```

#### Cas 2: Remplacement du fichier (avec ou sans modification de l'intitulé)
```
État initial:
  piecesJointes[0] = { intitule: "Carte d'identité", chemin: "/uploads/carte.pdf" }

Après upload d'un nouveau fichier:
  piecesJointes[0] = { 
    titre: "Carte d'identité nationale",  // Peut être modifié ou pas
    file: File { name: "nouvelle-carte.pdf", size: 12345 },  // Nouveau fichier
    existingFileUrl: "/uploads/carte.pdf"  // Fichier existant (sera remplacé)
  }

Envoi au backend:
  - La pièce existante N'EST PAS envoyée dans existingPiecesJointes
  - Le nouveau fichier est envoyé dans piecesJointes[] avec son intitulé
```

#### Cas 3: Aucune modification
```
État initial:
  piecesJointes[0] = { intitule: "Carte d'identité", chemin: "/uploads/carte.pdf" }

Après chargement (pas de modification):
  piecesJointes[0] = { 
    titre: "Carte d'identité",             // Inchangé
    file: null,                            // Pas de nouveau fichier
    existingFileUrl: "/uploads/carte.pdf"  // Fichier existant
  }

Envoi au backend:
  existingPiecesJointes[0][intitule] = "Carte d'identité"  // Original
  existingPiecesJointes[0][chemin] = "/uploads/carte.pdf"
  ...
```

## 📊 Données Envoyées au Backend

### Structure de `existingPiecesJointes`

Pour chaque pièce jointe existante **non remplacée** :

```javascript
formData.append("existingPiecesJointes[0][nom]", "carte.pdf");
formData.append("existingPiecesJointes[0][chemin]", "/uploads/carte.pdf");
formData.append("existingPiecesJointes[0][type]", "application/pdf");
formData.append("existingPiecesJointes[0][id]", "1");
formData.append("existingPiecesJointes[0][intitule]", "Nouveau titre si modifié");
```

### Ce que le Backend doit faire

```php
// Exemple PHP/Symfony
if (isset($data['existingPiecesJointes'])) {
    foreach ($data['existingPiecesJointes'] as $index => $pieceData) {
        $pieceJointe = $pieceJointeRepository->find($pieceData['id']);
        
        if ($pieceJointe) {
            // Mettre à jour l'intitulé si fourni
            if (!empty($pieceData['intitule'])) {
                $pieceJointe->setIntitule($pieceData['intitule']);
            }
            // Le chemin/fichier reste inchangé
            $entityManager->persist($pieceJointe);
        }
    }
}
```

## ✅ Scénarios de Test

### Test 1: Modifier uniquement l'intitulé
1. Ouvrir un courrier avec 1 pièce jointe "Justificatif de domicile"
2. Modifier l'intitulé en "Attestation de résidence"
3. Ne pas toucher au fichier
4. Soumettre le formulaire
5. **Résultat attendu:**
   - L'intitulé est mis à jour dans la base de données
   - Le fichier reste le même
   - Console log: `✏️ Pièce existante 1 conservée avec intitulé modifié: "Attestation de résidence"`

### Test 2: Remplacer le fichier
1. Ouvrir un courrier avec 1 pièce jointe
2. Uploader un nouveau fichier
3. Soumettre
4. **Résultat attendu:**
   - La pièce existante n'est PAS dans `existingPiecesJointes`
   - Le nouveau fichier est dans `piecesJointes[]`
   - Console log: `🔄 Pièce existante 1 sera remplacée par un nouveau fichier`

### Test 3: Modifier l'intitulé ET remplacer le fichier
1. Ouvrir un courrier avec 1 pièce jointe
2. Modifier l'intitulé
3. Uploader un nouveau fichier
4. Soumettre
5. **Résultat attendu:**
   - Le nouveau fichier avec le nouvel intitulé est envoyé
   - L'ancienne pièce n'est pas conservée

### Test 4: Ne rien modifier
1. Ouvrir un courrier avec 1 pièce jointe
2. Ne rien changer
3. Soumettre
4. **Résultat attendu:**
   - La pièce existante est conservée avec son intitulé original
   - Console log: `📎 Pièce existante 1 conservée avec intitulé original: "..."`

## 🎯 Résultat Final

✅ **L'utilisateur peut modifier uniquement l'intitulé** d'une pièce jointe sans avoir à re-uploader le fichier

✅ **L'utilisateur peut remplacer uniquement le fichier** en conservant l'intitulé

✅ **L'utilisateur peut modifier les deux** (intitulé et fichier)

✅ **L'utilisateur peut ne rien modifier** et la pièce existante est conservée telle quelle

## 🔍 Console Logs pour le Débogage

Lors de la soumission, vous verrez dans la console :

```
📝 Pièce existante 1 conservée avec intitulé modifié: "Nouveau titre"
📎 Pièce existante 2 conservée avec intitulé original: "Titre original"
🔄 Pièce existante 3 sera remplacée par un nouveau fichier
```

Ces logs permettent de vérifier que la logique fonctionne correctement.

## ⚠️ Important

Le backend doit implémenter la logique pour :
1. Recevoir `existingPiecesJointes[index][intitule]`
2. Mettre à jour l'intitulé dans la base de données
3. Conserver le fichier existant (ne pas le supprimer/remplacer)

Sans cette implémentation côté backend, les modifications d'intitulés seuls ne seront pas sauvegardées.
