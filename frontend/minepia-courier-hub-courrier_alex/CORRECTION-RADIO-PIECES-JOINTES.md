# Correction : Radio Button "Pièce jointe" Reste sur "Non" en Mode Édition

## 🐛 Problème Identifié

Lorsqu'on ouvre le formulaire d'édition d'un courrier départ qui possède des pièces jointes, le radio button "Pièce jointe" reste sur **"Non"** au lieu de se cocher automatiquement sur **"Oui"**.

### Symptômes
- ✅ Les pièces jointes sont bien chargées depuis l'API
- ✅ Les intitulés et fichiers sont bien formatés
- ✅ Le state `piecesJointes` est bien rempli dans le formulaire
- ❌ **Mais le radio button reste sur "Non"**

## 🔍 Cause du Problème

Le composant `DeclarationPiecesPhysiques` initialise son état uniquement **lors du premier rendu** (montage du composant) :

```typescript
// ❌ ANCIEN CODE - Initialisation uniquement au montage
const initialHasPieces = initialPieces.length > 0 ? "oui" : "non";
const [hasPiecesJointes, setHasPiecesJointes] = useState<string>(initialHasPieces);
```

**Problème :**
1. Le composant `DeclarationPiecesPhysiques` est monté **avant** que les données du courrier soient chargées
2. Au moment du montage, `initialPieces = []` (vide)
3. Donc `initialHasPieces = "non"`
4. Ensuite, même si `initialPieces` est mis à jour via les props, l'état interne `hasPiecesJointes` ne change pas

### Chronologie du Problème

```
1. Montage du composant DeclarationPiecesPhysiques
   initialPieces = [] (vide)
   → hasPiecesJointes = "non" ❌

2. Chargement des données API (fetchCourrierDetails)
   courrierDepart.piecesJointes = [{...}, {...}, {...}]

3. Formatage et mise à jour
   setPiecesJointes([{titre: "...", file: null, ...}, ...])

4. Re-render de DeclarationPiecesPhysiques
   initialPieces = [{...}, {...}, {...}] ✅
   Mais hasPiecesJointes = "non" toujours ❌ (pas de réactivité)
```

## ✅ Solution Implémentée

Ajouter un `useEffect` qui réagit aux changements de `initialPieces` pour mettre à jour l'état interne du composant.

### Fichier : `src/components/shared/DeclarationPiecesPhysiques.tsx`

**Code ajouté après la ligne 62 :**

```typescript
// ✅ Réagir aux changements de initialPieces et initialNombreTotal (mode édition)
useEffect(() => {
  if (initialPieces && initialPieces.length > 0) {
    console.log("📎 DeclarationPiecesPhysiques: Initialisation avec pièces existantes", initialPieces);
    setHasPiecesJointes("oui");
    setPieces(initialPieces);
    setNombrePieces(initialPieces.length);
    setNombreTotal(initialNombreTotal || initialPieces.length);
  } else {
    console.log("📎 DeclarationPiecesPhysiques: Aucune pièce initiale");
  }
}, [initialPieces, initialNombreTotal]);
```

### Explication de la Solution

1. **`useEffect` avec dépendances** : Le hook s'exécute à chaque fois que `initialPieces` ou `initialNombreTotal` changent
2. **Vérification de présence** : Si `initialPieces` contient des éléments
3. **Mise à jour de tous les états** :
   - `setHasPiecesJointes("oui")` → Coche le radio button sur "Oui" ✅
   - `setPieces(initialPieces)` → Remplit le tableau des pièces
   - `setNombrePieces(initialPieces.length)` → Met à jour le compteur
   - `setNombreTotal(...)` → Met à jour le nombre total

### Nouvelle Chronologie (Après Correction)

```
1. Montage du composant DeclarationPiecesPhysiques
   initialPieces = [] (vide)
   → hasPiecesJointes = "non"

2. Chargement des données API (fetchCourrierDetails)
   courrierDepart.piecesJointes = [{...}, {...}, {...}]

3. Formatage et mise à jour
   setPiecesJointes([{titre: "...", file: null, ...}, ...])

4. Re-render de DeclarationPiecesPhysiques
   initialPieces = [{...}, {...}, {...}] ✅
   
5. ✅ useEffect détecte le changement de initialPieces
   → setHasPiecesJointes("oui") ✅
   → setPieces([...]) ✅
   → setNombrePieces(3) ✅
   → setNombreTotal(3) ✅

6. Affichage correct
   ⦿ Oui   ○ Non  [✓ COCHÉ AUTOMATIQUEMENT]
```

## 🧪 Tests de Validation

### Test 1 : Courrier avec Pièces Jointes
```
1. Ouvrir un courrier départ avec 3 pièces jointes
2. ✅ Le radio button doit être sur "Oui"
3. ✅ Le nombre doit être "3"
4. ✅ Les 3 pièces doivent être affichées avec leurs intitulés
5. ✅ Les fichiers doivent être téléchargeables
```

### Test 2 : Courrier sans Pièce Jointe
```
1. Ouvrir un courrier départ sans pièce jointe
2. ✅ Le radio button doit être sur "Non"
3. ✅ Aucune pièce ne doit être affichée
```

### Test 3 : Création d'un Nouveau Courrier
```
1. Créer un nouveau courrier départ
2. ✅ Le radio button doit être sur "Non" par défaut
3. ✅ Passer à "Oui" manuellement
4. ✅ Ajouter des pièces jointes
```

### Test 4 : Modification des Pièces Jointes
```
1. Ouvrir un courrier avec pièces jointes
2. ✅ Radio = "Oui" (automatique)
3. ✅ Modifier un intitulé
4. ✅ Ajouter une nouvelle pièce
5. ✅ Supprimer une pièce existante
6. ✅ Sauvegarder et vérifier
```

## 📊 Logs de Debug

Pour vérifier que la correction fonctionne, les logs suivants seront affichés dans la console :

### Cas avec Pièces Jointes
```
📎 Pièces jointes trouvées: [{id: 1, nom: "annexe.pdf", ...}, ...]
📎 ✅ Pièces jointes initialisées pour DeclarationPiecesPhysiques: [...]
📎 ✅ Nombre de pièces jointes initialisé: 3
📎 DeclarationPiecesPhysiques: Initialisation avec pièces existantes [...]
```

### Cas sans Pièce Jointe
```
ℹ️ Aucune pièce jointe pour ce courrier
📎 DeclarationPiecesPhysiques: Aucune pièce initiale
```

## 🎯 Résultat Attendu

### Avant la Correction
```
Pièce jointe (optionnel)
Pièce jointe
Ajoutez une pièce jointe...

○ Oui   ⦿ Non  ❌ (Reste sur "Non" même avec pièces)
```

### Après la Correction
```
Pièce jointe (optionnel)
Pièce jointe
Ajoutez une pièce jointe...

⦿ Oui   ○ Non  ✅ (Passe automatiquement sur "Oui")

Nombre total de pièces jointes : [3]

Pièce 1/3
├─ Intitulé : [Rapport financier]
└─ Fichier  : annexe.pdf ✓

Pièce 2/3
├─ Intitulé : [Données statistiques]
└─ Fichier  : tableau.xlsx ✓

Pièce 3/3
├─ Intitulé : [Photo événement]
└─ Fichier  : photo.jpg ✓
```

## 📝 Fichiers Modifiés

1. ✅ `src/components/shared/DeclarationPiecesPhysiques.tsx`
   - Ajout d'un `useEffect` pour réagir aux changements de `initialPieces`

## ⚠️ Points d'Attention

### Pourquoi utiliser `useEffect` au lieu de `useState` ?

**Option 1 (❌ Mauvaise) :** Utiliser directement `initialPieces.length` dans le JSX
```typescript
// ❌ Ne fonctionne pas - l'état reste "non"
<RadioGroupItem value="oui" checked={initialPieces.length > 0} />
```

**Option 2 (✅ Bonne) :** Utiliser `useEffect` pour synchroniser l'état interne
```typescript
// ✅ Fonctionne - l'état se met à jour quand initialPieces change
useEffect(() => {
  if (initialPieces && initialPieces.length > 0) {
    setHasPiecesJointes("oui");
    // ... autres mises à jour
  }
}, [initialPieces, initialNombreTotal]);
```

### Pourquoi ne pas mettre à jour initialPieces dans le parent ?

Le composant enfant (`DeclarationPiecesPhysiques`) gère son propre état interne. Il doit réagir aux changements des props pour se synchroniser.

## 🔗 Ressources

- Interface `PieceJointeItem` : `src/components/shared/DeclarationPiecesPhysiques.tsx:11`
- Fonction `fetchCourrierDetails` : `src/components/CourriersDepart/CourrierDepartForm.tsx:215`
- Documentation complète : `INITIALISATION-PIECES-JOINTES-EDITION.md`

---

**Date :** 22 décembre 2025  
**Statut :** ✅ **CORRECTION APPLIQUÉE**  
**Impact :** Améliore l'UX en mode édition - Radio button cohérent avec les données
