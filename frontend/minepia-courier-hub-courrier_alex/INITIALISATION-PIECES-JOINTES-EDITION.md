# Initialisation des Pièces Jointes en Mode Édition

## 📋 Résumé des Modifications

Ce document décrit les modifications apportées au formulaire de mise à jour d'un courrier départ pour pré-remplir automatiquement la section "Pièces jointes" lorsque le courrier en possède déjà.

## 🎯 Objectif

Lorsqu'un utilisateur édite un courrier départ qui possède déjà des pièces jointes :

1. ✅ La case "Pièce jointe" doit être cochée sur **OUI** par défaut
2. ✅ Le **nombre de pièces jointes** doit être pré-rempli
3. ✅ Les **intitulés** et **fichiers existants** doivent être affichés
4. ✅ L'utilisateur peut modifier, ajouter ou supprimer des pièces jointes

## 🔧 Modifications Apportées

### Fichier : `src/components/CourriersDepart/CourrierDepartForm.tsx`

#### Fonction `fetchCourrierDetails()` - Ligne ~289-324

**Ancienne version :**
```typescript
// Récupérer les pièces jointes existantes
if (courrierDepart.piecesJointes && courrierDepart.piecesJointes.length > 0) {
  setExistingAttachments(courrierDepart.piecesJointes);
  console.log("📎 Pièces jointes trouvées:", courrierDepart.piecesJointes);
  console.log("📎 IDs des pièces jointes:", courrierDepart.piecesJointes.map((pj: any) => pj.id));
} else {
  console.log("ℹ️ Aucune pièce jointe pour ce courrier");
}
```

**Nouvelle version :**
```typescript
// Récupérer les pièces jointes existantes
if (courrierDepart.piecesJointes && courrierDepart.piecesJointes.length > 0) {
  setExistingAttachments(courrierDepart.piecesJointes);
  console.log("📎 Pièces jointes trouvées:", courrierDepart.piecesJointes);
  console.log("📎 IDs des pièces jointes:", courrierDepart.piecesJointes.map((pj: any) => pj.id));
  
  // ✅ Initialiser le composant DeclarationPiecesPhysiques avec les pièces jointes existantes
  const piecesJointesFormatees: PieceJointeItem[] = courrierDepart.piecesJointes.map((pj: any) => ({
    titre: pj.intitule || pj.nom || '', // Utiliser l'intitulé ou le nom du fichier
    file: null, // Pas de nouveau fichier à uploader (déjà existant)
    existingFileUrl: pj.chemin ? `${API_URL}${pj.chemin}` : undefined,
    existingFileName: pj.nom || pj.nom_fichier || pj.nomFichier || ''
  }));
  
  setPiecesJointes(piecesJointesFormatees);
  
  // Initialiser le nombre de pièces jointes (ou utiliser celui fourni par l'API si disponible)
  const nombrePieces = courrierDepart.nombrePieceJointe || courrierDepart.piecesJointes.length;
  setNombrePieceJointe(nombrePieces);
  
  console.log("📎 ✅ Pièces jointes initialisées pour DeclarationPiecesPhysiques:", piecesJointesFormatees);
  console.log("📎 ✅ Nombre de pièces jointes initialisé:", nombrePieces);
} else {
  console.log("ℹ️ Aucune pièce jointe pour ce courrier (piecesJointes:", courrierDepart.piecesJointes, ")");
  // Réinitialiser les états si aucune pièce jointe
  setPiecesJointes([]);
  setNombrePieceJointe(0);
}
```

## 📊 Flux de Données

### 1. Réception des Données de l'API

L'API retourne un objet courrier départ avec :

```json
{
  "id": 123,
  "numeroReference": "2025-11-005",
  "nombrePieceJointe": 3,
  "piecesJointes": [
    {
      "id": 1,
      "nom": "annexe.pdf",
      "intitule": "Rapport financier",
      "chemin": "/uploads/courrier_depart/pieces/65ff44c4a8b1f.pdf",
      "type": "application/pdf"
    },
    {
      "id": 2,
      "nom": "tableau.xlsx",
      "intitule": "Données statistiques",
      "chemin": "/uploads/courrier_depart/pieces/65ff44d5b9c2g.xlsx",
      "type": "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
    }
  ],
  ...
}
```

### 2. Transformation des Données

Chaque pièce jointe API est transformée en `PieceJointeItem` :

```typescript
interface PieceJointeItem {
  titre: string;              // ← pj.intitule || pj.nom
  file: File | null;          // ← null (fichier déjà existant)
  existingFileUrl?: string;   // ← API_URL + pj.chemin
  existingFileName?: string;  // ← pj.nom
}
```

### 3. Initialisation du Composant

Le composant `DeclarationPiecesPhysiques` reçoit :

```tsx
<DeclarationPiecesPhysiques
  onPiecesChange={setPiecesJointes}
  onNombreTotalChange={setNombrePieceJointe}
  initialPieces={piecesJointes}        // ← Tableau formaté
  initialNombreTotal={nombrePieceJointe} // ← 3
  className="w-full"
/>
```

### 4. État du Composant `DeclarationPiecesPhysiques`

Le composant détecte automatiquement qu'il y a des pièces initiales :

```typescript
const initialHasPieces = initialPieces.length > 0 ? "oui" : "non"; // ✅ "oui"
const [hasPiecesJointes, setHasPiecesJointes] = useState<string>(initialHasPieces);
```

## ✅ Résultat Attendu

Lorsque l'utilisateur ouvre le formulaire d'édition d'un courrier départ avec 3 pièces jointes :

### Interface Utilisateur

```
┌─────────────────────────────────────────────────────────────────┐
│ 📎 Pièces jointes                                               │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ Y a-t-il des pièces jointes à ce courrier ?                    │
│                                                                 │
│ ⦿ Oui   ○ Non                                    [✓ PRÉ-COCHÉ] │
│                                                                 │
│ ┌───────────────────────────────────────────────────────────┐  │
│ │ Nombre total de pièces jointes : [3]                      │  │
│ └───────────────────────────────────────────────────────────┘  │
│                                                                 │
│ Pièce 1/3                                                       │
│ ├─ Intitulé : [Rapport financier              ]                │
│ └─ Fichier  : annexe.pdf ✓ [Voir] [Télécharger]                │
│                                                                 │
│ Pièce 2/3                                                       │
│ ├─ Intitulé : [Données statistiques           ]                │
│ └─ Fichier  : tableau.xlsx ✓ [Voir] [Télécharger]              │
│                                                                 │
│ Pièce 3/3                                                       │
│ ├─ Intitulé : [Photo événement                ]                │
│ └─ Fichier  : photo.jpg ✓ [Voir] [Télécharger]                 │
│                                                                 │
│ [+ Ajouter une pièce jointe]                                    │
└─────────────────────────────────────────────────────────────────┘
```

## 🎨 Fonctionnalités Disponibles

### En Mode Édition avec Pièces Jointes Existantes

1. **Visualisation** : L'utilisateur voit toutes les pièces jointes avec leurs intitulés
2. **Téléchargement** : Chaque fichier peut être téléchargé via le lien "Télécharger"
3. **Modification de l'intitulé** : L'utilisateur peut changer le titre d'une pièce
4. **Remplacement du fichier** : L'utilisateur peut uploader un nouveau fichier
5. **Suppression** : L'utilisateur peut supprimer des pièces jointes
6. **Ajout** : L'utilisateur peut ajouter de nouvelles pièces jointes

## 🔍 Points Clés

### 1. Compatibilité avec Différents Formats API

Le code gère plusieurs variantes de noms de champs :

```typescript
existingFileName: pj.nom || pj.nom_fichier || pj.nomFichier || ''
```

### 2. Gestion du Nombre de Pièces

Priorité donnée au champ `nombrePieceJointe` de l'API, sinon calcul basé sur le tableau :

```typescript
const nombrePieces = courrierDepart.nombrePieceJointe || courrierDepart.piecesJointes.length;
```

### 3. Gestion de l'Absence de Pièces Jointes

Si le courrier n'a pas de pièces jointes, les états sont réinitialisés :

```typescript
setPiecesJointes([]);
setNombrePieceJointe(0);
```

Cela garantit que le composant reste en mode "NON" par défaut.

## 🧪 Tests à Effectuer

### Scénario 1 : Courrier avec Pièces Jointes
1. ✅ Ouvrir un courrier départ qui a 3 pièces jointes
2. ✅ Vérifier que "Pièce jointe" = "OUI"
3. ✅ Vérifier que le nombre = 3
4. ✅ Vérifier que les 3 intitulés sont affichés
5. ✅ Vérifier que les 3 fichiers sont téléchargeables

### Scénario 2 : Courrier sans Pièce Jointe
1. ✅ Ouvrir un courrier départ sans pièce jointe
2. ✅ Vérifier que "Pièce jointe" = "NON"
3. ✅ Vérifier que le nombre = 0

### Scénario 3 : Modification des Pièces Jointes
1. ✅ Ouvrir un courrier avec pièces jointes
2. ✅ Modifier un intitulé
3. ✅ Ajouter une nouvelle pièce
4. ✅ Supprimer une pièce existante
5. ✅ Sauvegarder et vérifier que les modifications sont appliquées

## 📝 Dépendances

### Interfaces TypeScript

- `PieceJointeItem` (définie dans `DeclarationPiecesPhysiques.tsx`)
- `CourrierDepartApiData` (définie dans `courriersDepartApi.ts`)

### Composants

- `DeclarationPiecesPhysiques` : Composant de gestion des pièces jointes
- `CourrierDepartForm` : Formulaire principal

### APIs

- `GET /core/courrier-depart/{id}` : Récupération des détails du courrier
- Backend doit retourner `nombrePieceJointe` et `piecesJointes[]`

## 🚀 Prochaines Étapes

1. ✅ Tester en mode édition avec des courriers réels
2. ✅ Vérifier que la sauvegarde fonctionne correctement
3. ✅ S'assurer que les nouveaux fichiers uploadés remplacent les anciens
4. ✅ Vérifier la suppression de pièces jointes existantes

---

**Date de modification :** 22 décembre 2025  
**Statut :** ✅ Implémentation complète - Tests requis
