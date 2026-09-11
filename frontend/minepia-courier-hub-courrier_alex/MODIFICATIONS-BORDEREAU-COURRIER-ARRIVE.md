# Modifications du Bordereau de Transmission des Courriers Arrivés

## 📋 Résumé des Modifications

Ce document décrit les modifications apportées au bordereau de transmission généré après la création d'un courrier arrivé.

## 🎯 Objectifs

1. ✅ **Changer "Expéditeur" en "Émetteur"** dans le bordereau
2. ✅ **Émetteur = Service de l'utilisateur connecté** (au lieu de la structure destinataire)
3. ✅ **Ajouter la colonne "Provenance"** dans le tableau du bordereau
4. ✅ **Récupérer `nombrePieceJointe` depuis l'API** (au lieu de compter les fichiers uploadés)

---

## 🔧 Modifications Apportées

### 1. Interface `BordereauRow` - `src/services/bordereauPdf.ts` (ligne 22-40)

**Modification :**
```typescript
export interface BordereauRow {
  numero: string;
  numeroActe?: string;
  reference?: string;
  objet: string;
  expediteur?: string;
  provenance?: string;       // ✅ AJOUTÉ - Provenance du courrier
  destinataire?: string;
  responsable?: string;
  poste?: string;
  piecesJointes?: number;    // Nombre de pièces (vient de l'API)
  remarque?: string;
  // ...
}
```

### 2. Fonction `generateBordereauPDF` - `src/services/bordereauPdf.ts` (ligne 226-260)

#### Changement "Expéditeur" → "Émetteur"

**Avant :**
```typescript
{ text: `Expéditeur: ${toText(data.expediteurNom)}...` },
```

**Après :**
```typescript
{ text: `Émetteur: ${toText(data.expediteurNom)}...` },
```

#### Ajout de la colonne "Provenance"

**Avant :**
```typescript
const tableHeader = [
  { text: "N°\nd'ordre", ... },
  { text: 'N° de\nréférence', ... },
  { text: 'Objet', ... },
  { text: 'Responsable\n(Poste)', ... },
  { text: 'Pièces\njointes', ... },
  { text: 'Émargement', ... },
];

const bodyRows = data.courriers.map((r, i) => [
  { text: toText(i + 1), ... },
  { text: toText(r.numero), ... },
  { text: toText(r.objet), ... },
  { text: toText(r.responsable || r.poste || ''), ... },
  { text: toText(r.piecesJointes ?? 0), ... },
  { text: '', ... }, // Émargement
]);

const table = {
  table: { headerRows: 1, widths: [30,55,'*',80,40,60], body: [...] },
  // ...
};
```

**Après :**
```typescript
const tableHeader = [
  { text: "N°\nd'ordre", ... },
  { text: 'N° de\nréférence', ... },
  { text: 'Objet', ... },
  { text: 'Provenance', bold: true, alignment: 'center', fontSize: 8 }, // ✅ AJOUTÉ
  { text: 'Responsable\n(Poste)', ... },
  { text: 'Pièces\njointes', ... },
  { text: 'Émargement', ... },
];

const bodyRows = data.courriers.map((r, i) => [
  { text: toText(i + 1), ... },
  { text: toText(r.numero), ... },
  { text: toText(r.objet), ... },
  { text: toText(r.provenance || ''), fontSize: 7 }, // ✅ AJOUTÉ
  { text: toText(r.responsable || r.poste || ''), ... },
  { text: toText(r.piecesJointes ?? 0), ... },
  { text: '', ... }, // Émargement
]);

const table = {
  table: { headerRows: 1, widths: [30,50,'*',60,60,35,50], body: [...] },
  // Ajustement des largeurs: [N°, Réf, Objet, Provenance, Resp, PJ, Émarg]
  // ...
};
```

### 3. Fonction `handlePrintChoice` - `src/components/CourriersArrives/NonConfidentialMailForm.tsx` (ligne 1589-1663)

**Modifications complètes :**

```typescript
const handlePrintChoice = async (choice: "yes" | "no") => {
  setShowPrintChoice(false);

  if (choice === "yes" && submittedData && createdCourrierId) {
    try {
      // ✅ 1. Récupérer les détails complets du courrier depuis l'API
      const token = getToken();
      if (!token) {
        throw new Error("Token d'authentification manquant");
      }

      const courrierResponse = await fetch(`${API_URL}/core/courrier/${createdCourrierId}`, {
        headers: {
          Authorization: `Bearer ${token}`,
        },
      });

      if (!courrierResponse.ok) {
        throw new Error("Impossible de récupérer les détails du courrier");
      }

      const courrierData = await courrierResponse.json();
      console.log("📦 Données du courrier pour bordereau:", courrierData);

      // ✅ 2. Récupérer le service de l'utilisateur connecté
      const currentUser = getCurrentUser();
      const userServiceName = currentUser?.service || 
                              currentUser?.poste?.nom || 
                              currentUser?.poste?.sigle || 
                              "Service non défini";
      
      const structureName = structures.find(s => s.id.toString() === submittedData.structure)?.nom || submittedData.structure;
      const provenanceName = correspondants.find(c => c.id.toString() === submittedData.provenance)?.nom || submittedData.customProvenance || "";

      // Générer un numéro de bordereau unique
      const numeroBordereau = `BT-${new Date().getFullYear()}-${String(createdCourrierId).padStart(5, '0')}`;

      // Préparer les données pour le bordereau PDF
      const bordereauData: BordereauData = {
        centerLogo: "/cropped-logo-minepia.png",
        
        numeroTransmission: numeroBordereau,
        dateEmission: format(new Date(), "dd/MM/yyyy"),
        expediteurNom: userServiceName,              // ✅ Service de l'utilisateur
        expediteurFonction: currentUser?.fullName || "", // Nom de l'utilisateur
        
        courriers: [
          {
            numero: autoReference,
            objet: submittedData.objet,
            provenance: provenanceName,              // ✅ Provenance ajoutée
            responsable: structureName,
            poste: "",
            piecesJointes: courrierData.nombrePieceJointe || 
                          courrierData.data?.nombrePieceJointe || 
                          0,                         // ✅ Nombre de pièces de l'API
            remarque: submittedData.comment || "",
          },
        ],
        
        fileName: `bordereau-transmission-${numeroBordereau}`,
      };

      console.log("📄 Génération du bordereau PDF avec les données:", bordereauData);
      
      await generateBordereauPDF(bordereauData);
      
      toast({
        title: "Bordereau généré",
        description: "Le bordereau de transmission a été téléchargé avec succès",
      });
    } catch (error) {
      console.error("❌ Erreur génération bordereau:", error);
      toast({ 
        title: "Erreur", 
        description: "Impossible de générer le bordereau", 
        variant: "destructive" 
      });
    }
  }

  setSubmittedData(null);
  setCreatedCourrierId(null);
  
  if (onSuccess) {
    onSuccess();
  }
  
  onClose();
};
```

---

## 📊 Structure du Bordereau (Avant vs Après)

### Avant

```
┌─────────────────────────────────────────────────────────────────┐
│ Bordereau de transmission N°: BT-2025-00123                    │
│ Date d'émission: 22/12/2025                                    │
│ Expéditeur: DRHGA (Structure destinataire) ❌                   │
│ Destinataire:                                                   │
├─────────────────────────────────────────────────────────────────┤
│ N°   │ Réf    │ Objet      │ Responsable │ PJ │ Émargement   │
├──────┼────────┼────────────┼─────────────┼────┼──────────────┤
│  1   │ CA-123 │ Demande... │ DRHGA       │ 2  │              │
└─────────────────────────────────────────────────────────────────┘
```

### Après

```
┌──────────────────────────────────────────────────────────────────────────┐
│ Bordereau de transmission N°: BT-2025-00123                             │
│ Date d'émission: 22/12/2025                                             │
│ Émetteur: Service Courrier (Utilisateur connecté) ✅                    │
│ Destinataire:                                                            │
├──────────────────────────────────────────────────────────────────────────┤
│ N°│ Réf   │ Objet      │ Provenance  │ Responsable │ PJ │ Émargement  │
├───┼───────┼────────────┼─────────────┼─────────────┼────┼─────────────┤
│ 1 │CA-123 │ Demande... │ Ministère   │ DRHGA       │ 3  │             │
│   │       │            │ ✅ AJOUTÉ   │             │✅API│             │
└──────────────────────────────────────────────────────────────────────────┘
```

---

## 🔍 Détails des Changements

### 1. Émetteur

**Avant :**
- Émetteur = Structure destinataire du courrier (DRHGA, etc.)
- Problème : Ne reflète pas qui a créé/enregistré le courrier

**Après :**
- Émetteur = Service de l'utilisateur connecté
- Source : `currentUser?.service` ou `currentUser?.poste?.nom` ou `currentUser?.poste?.sigle`
- Fonction : Nom complet de l'utilisateur (`currentUser?.fullName`)

### 2. Provenance

**Avant :**
- Pas de colonne provenance dans le tableau
- Information perdue dans le bordereau

**Après :**
- Nouvelle colonne "Provenance" affichant l'origine du courrier
- Source : Correspondant sélectionné ou saisie manuelle
- Permet de tracer l'origine du courrier dans le bordereau

### 3. Nombre de Pièces Jointes

**Avant :**
```typescript
piecesJointes: mainDocument.length + attachments.length
```
- Compte uniquement les fichiers uploadés dans le formulaire
- Ne tient pas compte des pièces déclarées sans upload
- Peut être inexact si l'utilisateur déclare des pièces physiques

**Après :**
```typescript
piecesJointes: courrierData.nombrePieceJointe || courrierData.data?.nombrePieceJointe || 0
```
- Récupère le nombre de pièces depuis l'API
- Prend en compte les pièces déclarées ET uploadées
- Source de vérité unique : la base de données

---

## 🎯 Flux de Données

```
1. Création du courrier
   └─> API retourne { id: 123, nombrePieceJointe: 3, ... }

2. Modal "Imprimer le bordereau ?"
   └─> Utilisateur clique "Oui"

3. Récupération des données
   ├─> GET /core/courrier/123 (détails complets)
   ├─> getCurrentUser() (service de l'utilisateur)
   └─> Recherche de la provenance dans les correspondants

4. Génération du bordereau
   ├─> Émetteur: "Service Courrier" (service de l'user)
   ├─> Provenance: "Ministère de la Santé"
   ├─> Pièces jointes: 3 (depuis l'API)
   └─> generateBordereauPDF(bordereauData)

5. Téléchargement du PDF
   └─> bordereau-transmission-BT-2025-00123.pdf
```

---

## 🧪 Tests à Effectuer

### Test 1 : Émetteur = Service de l'utilisateur
```
1. Se connecter avec un utilisateur du "Service Courrier"
2. Créer un courrier arrivé
3. Cliquer "Oui" pour imprimer le bordereau
4. ✅ Vérifier : "Émetteur: Service Courrier"
```

### Test 2 : Colonne Provenance
```
1. Créer un courrier avec provenance "Ministère de la Santé"
2. Imprimer le bordereau
3. ✅ Vérifier : Colonne "Provenance" affiche "Ministère de la Santé"
```

### Test 3 : Nombre de pièces jointes de l'API
```
1. Créer un courrier avec 2 fichiers uploadés
2. Déclarer 5 pièces au total (3 physiques)
3. Imprimer le bordereau
4. ✅ Vérifier : "Pièces jointes: 5" (et non 2)
```

### Test 4 : Utilisateur sans service défini
```
1. Se connecter avec un utilisateur sans service
2. Créer un courrier
3. Imprimer le bordereau
4. ✅ Vérifier : "Émetteur: Service non défini" (fallback)
```

---

## 📝 Fichiers Modifiés

1. **src/services/bordereauPdf.ts**
   - Interface `BordereauRow` : Ajout de `provenance`
   - Fonction `generateBordereauPDF` :
     - Changement "Expéditeur" → "Émetteur"
     - Ajout colonne "Provenance" dans le tableau
     - Ajustement des largeurs de colonnes

2. **src/components/CourriersArrives/NonConfidentialMailForm.tsx**
   - Fonction `handlePrintChoice` :
     - Récupération des détails du courrier depuis l'API
     - Récupération du service de l'utilisateur connecté
     - Ajout de la provenance dans les données du bordereau
     - Utilisation de `nombrePieceJointe` de l'API

---

## 📊 API Requirements

L'endpoint `GET /core/courrier/{id}` doit retourner :

```json
{
  "id": 123,
  "reference": "CA-2025-00123",
  "objet": "Demande de budget",
  "nombrePieceJointe": 3,  // ✅ REQUIS
  "provenance": {
    "id": 45,
    "nom": "Ministère de la Santé"
  },
  // ... autres champs
}
```

---

## ✅ Checklist

- [x] Changer "Expéditeur" en "Émetteur"
- [x] Émetteur = Service de l'utilisateur connecté
- [x] Ajouter colonne "Provenance" au tableau
- [x] Récupérer `nombrePieceJointe` de l'API
- [x] Ajuster les largeurs des colonnes du tableau
- [x] Gérer les cas où le service n'est pas défini (fallback)
- [x] Gérer les erreurs de récupération de l'API
- [x] Logs de debug pour le suivi

---

**Date :** 22 décembre 2025  
**Statut :** ✅ **IMPLÉMENTATION COMPLÈTE**  
**Prêt pour les tests**
