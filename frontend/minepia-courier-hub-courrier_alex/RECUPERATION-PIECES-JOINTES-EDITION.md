# Récupération et Affichage des Pièces Jointes en Mode Édition

**Date:** 22 décembre 2025  
**Branche:** ManuBranchTest

## 📋 Résumé des Modifications

Implémentation complète de la récupération et de l'affichage des pièces jointes existantes lors de la mise à jour d'un courrier arrivé (confidentiel ou non confidentiel).

## ✅ Problèmes Résolus

### 1. **Récupération du Nombre Total de Pièces Physiques**
- ✅ Le champ `nombrePieceJointe` est maintenant récupéré depuis l'API
- ✅ Si des pièces jointes existent, le nombre total est automatiquement défini
- ✅ Le champ "Nombre total des pièces physiques accompagnant le courrier" est pré-rempli

### 2. **Récupération des Intitulés des Pièces Jointes**
- ✅ Les intitulés de chaque pièce jointe sont récupérés depuis l'API
- ✅ Le champ "Oui/Non" pour les pièces jointes est automatiquement défini sur "Oui" si des pièces existent
- ✅ Les champs d'intitulés sont pré-remplis avec les données existantes

### 3. **Affichage des Fichiers Existants**
- ✅ **NOUVEAU:** Les fichiers existants sont affichés en dessous de leur intitulé respectif
- ✅ Carte bleue avec icône pour les fichiers existants
- ✅ Lien pour ouvrir/télécharger le fichier existant dans un nouvel onglet
- ✅ Distinction visuelle entre fichiers existants (bleu) et nouveaux fichiers (vert)

## 🔧 Fichiers Modifiés

### 1. `src/components/shared/DeclarationPiecesPhysiques.tsx`

#### a) Interface `PieceJointeItem` étendue
```typescript
export interface PieceJointeItem {
  titre: string;
  file: File | null;
  existingFileUrl?: string; // URL du fichier existant (pour le mode édition)
  existingFileName?: string; // Nom du fichier existant
}
```

#### b) Affichage du fichier existant
- **Position:** En dessous de l'intitulé de chaque pièce jointe
- **Apparence:** Carte bleue avec bordure
- **Contenu:**
  - Icône `FileText`
  - Nom du fichier
  - Label "Fichier actuel"
  - Lien externe pour voir le fichier

#### c) Affichage du nouveau fichier uploadé
- **Position:** En dessous de l'intitulé (remplace le fichier existant si uploadé)
- **Apparence:** Carte verte avec bordure
- **Contenu:**
  - Icône `CheckCircle`
  - Nom du fichier
  - Taille du fichier + "Nouveau fichier"

#### d) Bouton Upload amélioré
- **Textes adaptatifs:**
  - Si fichier existant: "Remplacer le fichier"
  - Si nouveau fichier: "Changer le fichier"
  - Si aucun fichier: "Uploader le fichier"

### 2. `src/components/CourriersArrives/NonConfidentialMailForm.tsx`

#### Transformation des pièces jointes de l'API
```typescript
const piecesJointesItems: PieceJointeItem[] = courrier.piecesJointes.map((pj: any) => ({
  titre: pj.intitule || pj.nom || "",
  file: null,
  existingFileUrl: pj.chemin || "",
  existingFileName: pj.nom || pj.nom_fichier || pj.nomFichier || "Fichier existant"
}));
```

### 3. `src/components/CourriersArrives/ConfidentialMailForm.tsx`

#### Même transformation pour les courriers confidentiels
```typescript
const piecesJointesItems: PieceJointeItem[] = courrier.piecesJointes.map((pj: any) => ({
  titre: pj.intitule || pj.nom || "",
  file: null,
  existingFileUrl: pj.chemin || "",
  existingFileName: pj.nom || pj.nom_fichier || pj.nomFichier || "Fichier existant"
}));
```

## 📊 Structure des Données de l'API

### Exemple de réponse API pour un courrier avec pièces jointes
```json
{
  "id": 1,
  "reference": "CA-00045/2025",
  "nombrePieceJointe": 3,
  "piecesJointes": [
    {
      "id": 1,
      "nom": "Annexe1.pdf",
      "intitule": "Justificatif de domicile",
      "chemin": "/uploads/courrier/pieces/annexe1.pdf",
      "type": "application/pdf"
    }
  ]
}
```

### Mapping vers l'interface frontend
- `pj.intitule` → `titre` (intitulé de la pièce)
- `pj.nom` → `existingFileName` (nom du fichier)
- `pj.chemin` → `existingFileUrl` (URL pour télécharger/voir)
- `null` → `file` (aucun nouveau fichier uploadé par défaut)

## 🎨 Interface Utilisateur

### Affichage en Mode Édition

#### 1. Si des pièces jointes existent
```
┌─────────────────────────────────────────────┐
│ 🔵 Oui ⚪ Non                               │
│                                             │
│ Nombre total: [3]                          │
│                                             │
│ 1. [Justificatif de domicile]             │
│    ┌─────────────────────────────────────┐ │
│    │ 📄 Annexe1.pdf                     🔗│ │
│    │ Fichier actuel                       │ │
│    └─────────────────────────────────────┘ │
│    [📤] [🗑️]                               │
└─────────────────────────────────────────────┘
```

#### 2. Après upload d'un nouveau fichier
```
┌─────────────────────────────────────────────┐
│ 1. [Justificatif de domicile]             │
│    ┌─────────────────────────────────────┐ │
│    │ ✅ NouveauDoc.pdf                   │ │
│    │ 2.5 MB • Nouveau fichier             │ │
│    └─────────────────────────────────────┘ │
│    [📤] [🗑️]                               │
└─────────────────────────────────────────────┘
```

## 🔄 Flux de Fonctionnement

### Mode Édition - Chargement Initial
1. **API** retourne le courrier avec `piecesJointes` et `nombrePieceJointe`
2. **Frontend** transforme les pièces en `PieceJointeItem[]` avec:
   - `titre` = intitulé existant
   - `file` = null (pas de nouveau fichier)
   - `existingFileUrl` = chemin du fichier
   - `existingFileName` = nom du fichier
3. **Composant** affiche le fichier existant en bleu sous l'intitulé

### Mode Édition - Modification
1. **Utilisateur** peut:
   - ✏️ Modifier l'intitulé uniquement (fichier existant reste)
   - 📤 Uploader un nouveau fichier (remplace le fichier existant)
   - ✏️📤 Modifier les deux
   
2. **Affichage:**
   - Fichier existant = carte bleue
   - Nouveau fichier = carte verte (remplace l'affichage bleu)

3. **Soumission:**
   - Si nouveau fichier uploadé → envoyer le nouveau fichier
   - Sinon → garder le fichier existant côté backend

## ✨ Améliorations Apportées

### Expérience Utilisateur
- ✅ Visualisation claire des fichiers existants
- ✅ Possibilité de télécharger/voir le fichier existant via le lien 🔗
- ✅ Distinction visuelle entre ancien et nouveau fichier
- ✅ Modification flexible (intitulé seul, fichier seul, ou les deux)

### Ergonomie
- ✅ Affichage en dessous de l'intitulé (logique de lecture naturelle)
- ✅ Codes couleur intuitifs (bleu = existant, vert = nouveau)
- ✅ Icônes explicites (📄 fichier, ✅ validé, 🔗 lien)

### Technique
- ✅ Support des fichiers existants dans l'interface TypeScript
- ✅ Gestion des cas où le nom du fichier peut avoir différents noms de propriétés
- ✅ Fallback élégant si les données sont incomplètes

## 🧪 Cas de Test

### Scénario 1: Édition sans modification
- Ouvrir un courrier avec 2 pièces jointes
- ✅ Vérifier que les intitulés sont affichés
- ✅ Vérifier que les fichiers existants sont affichés en bleu
- ✅ Cliquer sur le lien pour ouvrir un fichier
- Ne rien modifier, soumettre
- ✅ Les fichiers existants sont conservés

### Scénario 2: Modification de l'intitulé uniquement
- Ouvrir un courrier avec 1 pièce jointe
- Modifier l'intitulé
- Ne pas uploader de nouveau fichier
- Soumettre
- ✅ Intitulé mis à jour, fichier conservé

### Scénario 3: Remplacement d'un fichier
- Ouvrir un courrier avec 1 pièce jointe
- Uploader un nouveau fichier
- ✅ Le fichier bleu disparaît, une carte verte apparaît
- Soumettre
- ✅ Le nouveau fichier remplace l'ancien

### Scénario 4: Ajout d'une nouvelle pièce
- Ouvrir un courrier avec 1 pièce jointe
- Cliquer sur "Ajouter une pièce supplémentaire"
- Remplir l'intitulé et uploader un fichier
- Soumettre
- ✅ 2 pièces jointes au total (1 existante + 1 nouvelle)

## 📝 Notes Importantes

1. **URL des fichiers:** L'URL `existingFileUrl` doit pointer vers un endpoint accessible (chemin relatif ou absolu)

2. **Sécurité:** Le lien ouvre dans un nouvel onglet avec `rel="noopener noreferrer"` pour la sécurité

3. **Gestion des noms:** Le code gère plusieurs variantes de noms de propriétés (`nom`, `nom_fichier`, `nomFichier`)

4. **Nombre total:** Le champ `nombrePieceJointe` est maintenant correctement récupéré et affiché

## 🎯 Résultat Final

L'utilisateur peut maintenant:
- ✅ Voir toutes les pièces jointes d'un courrier en mode édition
- ✅ Modifier les intitulés des pièces existantes
- ✅ Remplacer les fichiers existants
- ✅ Télécharger/voir les fichiers existants avant modification
- ✅ Ajouter de nouvelles pièces jointes
- ✅ Visualiser le nombre total de pièces physiques

Toutes les fonctionnalités demandées sont implémentées avec une interface claire et intuitive ! ✨
