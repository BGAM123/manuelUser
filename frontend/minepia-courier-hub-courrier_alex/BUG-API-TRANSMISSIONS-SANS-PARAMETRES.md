# 🐛 BUG : Appel API Transmissions Sans Paramètres

## ⚠️ Problème Identifié

### Code Actuel (BUGUÉ)
**Fichier :** `src/api/transmissionsApi.ts` - Ligne 342-378

```typescript
export const getTransmissions = async (filters: TransmissionFilters = {}): Promise<TransmissionResponse> => {
  try {
    const params = new URLSearchParams();
    
    // ✅ Construction des paramètres
    params.append('order_by', filters.order_by || 'DESC');
    params.append('page', (filters.page || 1).toString());
    params.append('limit', (filters.limit !== undefined ? filters.limit : 10).toString());
    params.append('is_delete', (filters.is_delete !== undefined ? filters.is_delete : false).toString());
    params.append('is_archive', (filters.is_archive !== undefined ? filters.is_archive : false).toString());
    
    // Filtres de date
    if (filters.start_date) params.append('start_date', filters.start_date);
    if (filters.end_date) params.append('end_date', filters.end_date);
    if (filters.date) params.append('date', filters.date);
    
    // Filtres de contenu
    if (filters.priorite && filters.priorite !== 'all') params.append('priorite', filters.priorite);
    if (filters.categorie && typeof filters.categorie === 'number') params.append('categorie', filters.categorie.toString());
    if (filters.type_courrier && typeof filters.type_courrier === 'number') params.append('type_courrier', filters.type_courrier.toString());
    if (filters.provenance && typeof filters.provenance === 'number') params.append('provenance', filters.provenance.toString());
    if (filters.service_destinataire && typeof filters.service_destinataire === 'number') params.append('service_destinataire', filters.service_destinataire.toString());
    if (filters.emetteur && typeof filters.emetteur === 'number') params.append('emetteur', filters.emetteur.toString());
    if (filters.statut && filters.statut !== 'all') params.append('statut', filters.statut);
    if (filters.type_transfert && filters.type_transfert !== 'all') params.append('type_transfert', filters.type_transfert);
    if (filters.accuse_reception !== undefined) params.append('accuse_reception', filters.accuse_reception.toString());
    if (filters.search) params.append('search', filters.search);

    console.log('🔍 Appel API transmissions:', `/core/transmission`);
    
    // ❌ PROBLÈME : Les paramètres ne sont PAS envoyés !
    const response = await api.get(`/core/transmission`);
    //                                               ^^^^^ AUCUN PARAMÈTRE !
    
    console.log('📡 Réponse API transmissions:', response.data);
    // ...
  }
}
```

---

## 🔍 Analyse du Bug

### Ce qui se passe actuellement :

1. **Construction des paramètres** ✅
   ```typescript
   const params = new URLSearchParams();
   params.append('page', '1');
   params.append('limit', '10');
   params.append('order_by', 'DESC');
   // ... etc
   ```

2. **Appel API** ❌
   ```typescript
   const response = await api.get(`/core/transmission`);
   // Les params ne sont JAMAIS utilisés !
   ```

3. **Résultat**
   ```
   GET /core/transmission
   
   ❌ SANS aucun filtre !
   ❌ SANS pagination !
   ❌ SANS ordre de tri !
   ```

---

## ✅ Solution

### Option 1 : Avec URLSearchParams
```typescript
export const getTransmissions = async (filters: TransmissionFilters = {}): Promise<TransmissionResponse> => {
  try {
    const params = new URLSearchParams();
    
    // Construction des paramètres
    params.append('order_by', filters.order_by || 'DESC');
    params.append('page', (filters.page || 1).toString());
    params.append('limit', (filters.limit !== undefined ? filters.limit : 10).toString());
    params.append('is_delete', (filters.is_delete !== undefined ? filters.is_delete : false).toString());
    params.append('is_archive', (filters.is_archive !== undefined ? filters.is_archive : false).toString());
    
    if (filters.start_date) params.append('start_date', filters.start_date);
    if (filters.end_date) params.append('end_date', filters.end_date);
    if (filters.priorite && filters.priorite !== 'all') params.append('priorite', filters.priorite);
    if (filters.categorie && typeof filters.categorie === 'number') params.append('categorie', filters.categorie.toString());
    if (filters.search) params.append('search', filters.search);
    
    console.log('🔍 Appel API transmissions avec params:', params.toString());
    
    // ✅ CORRECTION : Ajouter les paramètres à l'URL
    const response = await api.get(`/core/transmission?${params.toString()}`);
    
    // Résultat : GET /core/transmission?order_by=DESC&page=1&limit=10&is_delete=false&is_archive=false
  }
}
```

### Option 2 : Avec objet de paramètres (axios)
```typescript
export const getTransmissions = async (filters: TransmissionFilters = {}): Promise<TransmissionResponse> => {
  try {
    const params: any = {
      order_by: filters.order_by || 'DESC',
      page: filters.page || 1,
      limit: filters.limit !== undefined ? filters.limit : 10,
      is_delete: filters.is_delete !== undefined ? filters.is_delete : false,
      is_archive: filters.is_archive !== undefined ? filters.is_archive : false,
    };
    
    // Ajouter les filtres optionnels
    if (filters.start_date) params.start_date = filters.start_date;
    if (filters.end_date) params.end_date = filters.end_date;
    if (filters.priorite && filters.priorite !== 'all') params.priorite = filters.priorite;
    if (filters.categorie && typeof filters.categorie === 'number') params.categorie = filters.categorie;
    if (filters.search) params.search = filters.search;
    
    console.log('🔍 Appel API transmissions avec params:', params);
    
    // ✅ CORRECTION : Passer les paramètres à axios
    const response = await api.get('/core/transmission', { params });
    
    // Résultat : GET /core/transmission?order_by=DESC&page=1&limit=10&is_delete=false&is_archive=false
  }
}
```

---

## 🎯 Recommandation

**Utiliser l'Option 2** (objet de paramètres) car :
- ✅ Plus lisible
- ✅ Axios gère automatiquement l'encodage URL
- ✅ Plus facile à déboguer
- ✅ Convention standard avec axios

---

## 📋 Exemple d'URL Générée

### Appel Simple
```typescript
getTransmissions({ page: 1, limit: 10 });
```
**URL :**
```
GET /core/transmission?order_by=DESC&page=1&limit=10&is_delete=false&is_archive=false
```

### Appel avec Filtres
```typescript
getTransmissions({
  page: 2,
  limit: 20,
  priorite: 'haute',
  search: 'rapport',
  start_date: '2025-01-01',
  end_date: '2025-12-31'
});
```
**URL :**
```
GET /core/transmission?order_by=DESC&page=2&limit=20&is_delete=false&is_archive=false&priorite=haute&search=rapport&start_date=2025-01-01&end_date=2025-12-31
```

---

## 🔧 Code Corrigé Complet

```typescript
export const getTransmissions = async (filters: TransmissionFilters = {}): Promise<TransmissionResponse> => {
  try {
    // Construction de l'objet de paramètres
    const params: any = {
      order_by: filters.order_by || 'DESC',
      page: filters.page || 1,
      limit: filters.limit !== undefined ? filters.limit : 10,
      is_delete: filters.is_delete !== undefined ? filters.is_delete : false,
      is_archive: filters.is_archive !== undefined ? filters.is_archive : false,
    };
    
    // Filtres de date
    if (filters.start_date) params.start_date = filters.start_date;
    if (filters.end_date) params.end_date = filters.end_date;
    if (filters.date) params.date = filters.date;
    
    // Compatibilité avec anciens filtres
    if (filters.date_debut) params.start_date = filters.date_debut;
    if (filters.date_fin) params.end_date = filters.date_fin;
    
    // Filtres de contenu
    if (filters.priorite && filters.priorite !== 'all') params.priorite = filters.priorite;
    if (filters.categorie && typeof filters.categorie === 'number') params.categorie = filters.categorie;
    if (filters.type_courrier && typeof filters.type_courrier === 'number') params.type_courrier = filters.type_courrier;
    if (filters.courrier && typeof filters.courrier === 'number') params.courrier = filters.courrier;
    if (filters.provenance && typeof filters.provenance === 'number') params.provenance = filters.provenance;
    if (filters.service_destinataire && typeof filters.service_destinataire === 'number') params.service_destinataire = filters.service_destinataire;
    if (filters.emetteur && typeof filters.emetteur === 'number') params.emetteur = filters.emetteur;
    if (filters.statut && filters.statut !== 'all') params.statut = filters.statut;
    if (filters.type_transfert && filters.type_transfert !== 'all') params.type_transfert = filters.type_transfert;
    if (filters.accuse_reception !== undefined) params.accuse_reception = filters.accuse_reception;
    if (filters.search) params.search = filters.search;

    console.log('🔍 Appel API transmissions avec paramètres:', params);
    console.log('📡 URL complète:', `/core/transmission?${new URLSearchParams(params).toString()}`);
    
    // ✅ Appel API avec les paramètres
    const response = await api.get('/core/transmission', { params });
    
    console.log('📡 Réponse API transmissions:', response.data);
    
    // Mapper les données de l'API vers le format frontend
    const mappedData = response.data.data.map(mapTransmissionFromApi);
    
    // Mapper les données des services additionnels si présentes
    const mappedServicesAdditionel = response.data.data_services_additionel?.map((serviceData: any) => ({
      service_id: serviceData.service_id,
      total: serviceData.total,
      transmissions: serviceData.transmissions.map(mapTransmissionFromApi)
    }));

    // Mapper les transmissions en copie si présentes
    const mappedTransmissionsCopie = response.data.data_transmissions_copie?.map((serviceData: any) => ({
      service_id: serviceData.service_id,
      total: serviceData.total,
      transmissions: serviceData.transmissions.map(mapTransmissionFromApi)
    }));
    
    const result: TransmissionResponse = {
      data: mappedData,
      total: response.data.total || 0,
      page: response.data.page || filters.page || 1,
      limit: response.data.limit || filters.limit || 10,
      totalPages: Math.ceil((response.data.total || 0) / (filters.limit || 10)),
      data_services_additionel: mappedServicesAdditionel,
      data_transmissions_copie: mappedTransmissionsCopie,
    };
    
    console.log('✅ Données transmissions mappées:', result);
    
    return result;
    
  } catch (error: any) {
    console.error('❌ Erreur lors de la récupération des transmissions:', error);
    throw new Error(
      error.response?.data?.detail || 
      error.response?.data?.message || 
      'Erreur lors de la récupération des transmissions'
    );
  }
};
```

---

## 🎯 Impact du Bug

### Sans la Correction (actuellement)
```
❌ Tous les filtres sont ignorés
❌ La pagination ne fonctionne pas
❌ L'ordre de tri est ignoré
❌ Les recherches ne marchent pas
❌ L'API retourne TOUTES les transmissions
```

### Avec la Correction
```
✅ Filtres appliqués côté serveur
✅ Pagination fonctionnelle
✅ Ordre de tri respecté
✅ Recherches opérationnelles
✅ API retourne uniquement les résultats filtrés
```

---

**Date :** 23 décembre 2025  
**Statut :** 🐛 **BUG IDENTIFIÉ - CORRECTION NÉCESSAIRE**  
**Priorité :** 🔴 **HAUTE** (affecte toutes les fonctionnalités de filtrage)
