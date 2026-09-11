# API des Transmissions - Page Traitement

## 🎯 Question
**Quelle API est appelée pour récupérer les transmissions dans la page traitements ?**

---

## 📡 Réponse

### API Endpoint
```
GET /core/transmission
```

### Fonction Frontend
```typescript
// src/api/transmissionsApi.ts - ligne 342
export const getTransmissions = async (filters: TransmissionFilters = {}): Promise<TransmissionResponse>
```

---

## 🔄 Flux de Récupération des Données

### 1️⃣ Page Traitement
**Fichier :** `src/pages/CourriersTraitement.tsx`

```typescript
// Ligne 70 - Import du hook
import { useCourrierTraitement } from '@/hooks/useCourrierTraitement';

// Ligne 179 - Utilisation du hook
const {
  courriers,
  courriersServicesAdditionel,
  courriersEnCopie,
  loading,
  error,
  pagination,
  // ...
} = useCourrierTraitement({
  page: currentPagePrincipal,
  limit: itemsPerPagePrincipal,
  order_by: 'DESC'
});
```

### 2️⃣ Hook de Gestion
**Fichier :** `src/hooks/useCourrierTraitement.ts`

```typescript
// Ligne 5 - Import de l'API
import { 
  getTransmissions, 
  convertTransmissionResponseToCourrierResponse,
  TransmissionFilters,
  TransmissionResponse
} from '@/api/transmissionsApi';

// Le hook appelle getTransmissions() pour récupérer les données
```

### 3️⃣ Fonction API
**Fichier :** `src/api/transmissionsApi.ts`

```typescript
// Ligne 342
export const getTransmissions = async (filters: TransmissionFilters = {}): Promise<TransmissionResponse> => {
  try {
    const params = new URLSearchParams();
    
    // Paramètres par défaut
    params.append('order_by', filters.order_by || 'DESC');
    params.append('page', (filters.page || 1).toString());
    params.append('limit', (filters.limit !== undefined ? filters.limit : 10).toString());
    params.append('is_delete', (filters.is_delete !== undefined ? filters.is_delete : false).toString());
    params.append('is_archive', (filters.is_archive !== undefined ? filters.is_archive : false).toString());
    
    // Appel à l'API backend
    const response = await api.get(`/core/transmission`);
    
    // Mapping des données
    const mappedData = response.data.data.map(mapTransmissionFromApi);
    
    return {
      data: mappedData,
      total: response.data.total || 0,
      page: response.data.page || filters.page || 1,
      limit: response.data.limit || filters.limit || 10,
      totalPages: Math.ceil((response.data.total || 0) / (filters.limit || 10)),
    };
    
  } catch (error: any) {
    // Gestion des erreurs
    throw new Error('Erreur lors de la récupération des transmissions');
  }
};
```

---

## 📊 Structure de la Réponse API

### Réponse Backend (`TransmissionApiResponse`)

```typescript
{
  page: number;          // Numéro de la page
  limit: number;         // Nombre d'éléments par page
  total: number;         // Total d'éléments
  totalPages?: number;   // Nombre total de pages
  
  // Transmissions principales (service principal de l'utilisateur)
  data: TransmissionApiData[];
  
  // Transmissions des services additionnels (facultatif)
  data_services_additionel?: Array<{
    service_id: number;
    total: number;
    transmissions: TransmissionApiData[];
  }>;
  
  // Transmissions où l'utilisateur est en copie (facultatif)
  data_transmissions_copie?: Array<{
    service_id: number;
    total: number;
    transmissions: TransmissionApiData[];
  }>;
}
```

### Données de Transmission (`TransmissionApiData`)

```typescript
{
  id: number;
  courrier: {
    id: number;
    numero: string;
    reference: string;
    objet: string;
    dateArrivee: string;
    dateEnregistrement: string;
    typeCourrier: string | null;
    provenance: string | null;
    categorie: string | null;
    priorite: string;
    isGeled?: boolean;
    classeCourrier?: string | null;
  };
  serviceDestinataire: {
    id: number;
    nom: string;
    sigle: string;
  };
  emetteur: {
    id: number;
    fullName: string;
    email: string;
    service?: {
      id: number;
      nom: string;
      sigle: string;
    };
  };
  structuresCopie: string[] | null;
  dateInstruction: string;
  dateReception: string | null;
  instruction: string | null;
  delaiTraitement: number | null;
  typeTransfert: string;
  accuseReception: boolean;
  statut: string | null;
  canTransmit: boolean;      // Peut transmettre ce courrier ?
  canModify?: boolean;        // Peut modifier la transmission ?
  modifiableTransmissionId?: number;
  isinstance?: boolean;       // Transmission en instance ?
  
  // Historique des traitements
  traitePar?: Array<{
    action: string;
    date_traitement: string;
    transmis_par_id?: number;
    transmis_par_id_fullname?: string;
    transmis_par_id_service?: {
      id: number;
      nom: string;
      sigle: string;
    };
    accuse_par_id?: number;
    accuse_par_id_fullname?: string;
    accuse_par_id_service?: {
      id: number;
      nom: string;
      sigle: string;
    };
  }>;
  
  // Pièces jointes de la transmission
  piecesJointes?: Array<{
    id: number;
    nom: string;
    intitule: string;
    chemin: string;
    type: string;
  }>;
  nombrePieceJointe?: number;
}
```

---

## 🎛️ Paramètres de Filtrage

La fonction `getTransmissions` accepte des filtres optionnels :

```typescript
interface TransmissionFilters {
  // Pagination
  page?: number;              // Numéro de page (défaut: 1)
  limit?: number;             // Éléments par page (défaut: 10)
  order_by?: 'ASC' | 'DESC';  // Ordre de tri (défaut: DESC)
  
  // Filtres de base
  is_delete?: boolean;        // Exclure les supprimés (défaut: false)
  is_archive?: boolean;       // Exclure les archivés (défaut: false)
  
  // Filtres de date
  start_date?: string;        // Date de début (format: YYYY-MM-DD)
  end_date?: string;          // Date de fin (format: YYYY-MM-DD)
  date?: string;              // Date exacte
  date_debut?: string;        // Alias pour start_date
  date_fin?: string;          // Alias pour end_date
  
  // Filtres de contenu
  priorite?: string;          // Priorité (ou 'all')
  categorie?: number;         // ID de la catégorie
  type_courrier?: number;     // ID du type de courrier
  courrier?: number;          // ID du courrier
  provenance?: number;        // ID de la provenance
  service_destinataire?: number;  // ID du service destinataire
  emetteur?: number;          // ID de l'émetteur
  statut?: string;            // Statut (ou 'all')
  type_transfert?: string;    // Type de transfert (ou 'all')
  accuse_reception?: boolean; // Accusé de réception
  search?: string;            // Recherche textuelle
}
```

---

## 🔍 Exemple d'Appel

### Dans le Hook
```typescript
const response = await getTransmissions({
  page: 1,
  limit: 10,
  order_by: 'DESC',
  is_delete: false,
  is_archive: false,
  priorite: 'haute',
  search: 'rapport'
});
```

### Réponse Attendue
```json
{
  "page": 1,
  "limit": 10,
  "total": 25,
  "totalPages": 3,
  "data": [
    {
      "id": 1,
      "reference": "TRS-2025-001",
      "objet": "Demande de rapport trimestriel",
      "serviceDestinataire": "Direction Technique",
      "emetteur": "Admin System",
      "priorite": "haute",
      "statut": "transmis",
      "canTransmit": true,
      // ...
    }
  ]
}
```

---

## 📋 Types de Transmissions Retournées

### 1. **Service Principal** (`data`)
Transmissions destinées au **service principal** de l'utilisateur connecté.

### 2. **Services Additionnels** (`data_services_additionel`)
Transmissions destinées aux **services additionnels** de l'utilisateur (si configurés).

### 3. **En Copie** (`data_transmissions_copie`)
Transmissions où l'utilisateur/son service est **en copie** (pour information).

---

## 🎯 Résumé

| Élément | Valeur |
|---------|--------|
| **Endpoint** | `GET /core/transmission` |
| **Fonction** | `getTransmissions()` |
| **Fichier** | `src/api/transmissionsApi.ts` |
| **Hook** | `useCourrierTraitement` |
| **Page** | `CourriersTraitement.tsx` |
| **Pagination** | ✅ Oui (page, limit) |
| **Filtres** | ✅ Oui (date, priorité, statut, etc.) |
| **Services additionnels** | ✅ Oui |
| **Transmissions en copie** | ✅ Oui |

---

**Date :** 23 décembre 2025  
**Statut :** ✅ **DOCUMENTÉ**
