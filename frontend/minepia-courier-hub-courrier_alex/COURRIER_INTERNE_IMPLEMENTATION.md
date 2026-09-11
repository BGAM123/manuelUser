# Implémentation du Module Courrier Interne

## Date d'implémentation
Janvier 2025

## Vue d'ensemble
Nouveau module de gestion des courriers internes du ministère avec interface utilisateur complète, formulaire de création et système de filtrage.

---

## Fichiers créés

### 1. Page principale
**`/src/pages/CourrierInterne.tsx`** (92 lignes)
- Page principale avec système de tabs (2 onglets)
- Gestion de l'état du modal de création
- Rafraîchissement automatique après création

**Fonctionnalités:**
- Tab "Mes courriers internes" (courriers reçus)
- Tab "Courrier interne envoyés" (courriers envoyés)
- Bouton "Nouveau Courrier" ouvrant le modal
- Callback de rafraîchissement après création

---

### 2. Composants

#### **`/src/components/CourrierInterne/CourrierInterneModal.tsx`** (470 lignes)
Modal de création de courrier interne (simplifié depuis TransmissionForm)

**Champs du formulaire:**
- `poste_destinataire` (requis) - Sélection hiérarchique du service
- `objet` (requis) - Objet du courrier
- `type_transfert` (requis) - Pour Instruction / Pour Visa / Pour Transfert
- `delai_traitement` (optionnel) - Délai en jours
- `commentaire_public` (optionnel) - Commentaire
- Pièces jointes (optionnel)

**Validation Zod:**
```typescript
const courrierInterneSchema = z.object({
  poste_destinataire: z.number().min(1, 'Sélectionnez un poste destinataire'),
  objet: z.string().min(1, 'L\'objet est requis'),
  type_transfert: z.enum(['instruction', 'visa', 'transfert']),
  delai_traitement: z.number().optional(),
  commentaire_public: z.string().optional(),
  files: z.array(...).optional()
});
```

**API utilisée:**
- `getParentServices()` - Chargement de l'arborescence des services
- `createTransmission()` - Création du courrier interne

---

#### **`/src/components/CourrierInterne/CourrierInterneRecusTab.tsx`** (425 lignes)
Onglet des courriers internes reçus

**Fonctionnalités:**
- Affichage en tableau avec colonnes sélectionnables
- Filtres: date, priorité, catégorie, recherche
- Pagination moderne (ModernPagination)
- Vue détaillée (MailDetailsSheet)
- Tri des colonnes

**Colonnes affichées:**
| Colonne | ID | Visible par défaut |
|---------|----|--------------------|
| Sélection | `select` | ✓ |
| Référence | `reference` | ✓ |
| Émetteur | `emetteur` | ✓ |
| Objet | `objet` | ✓ |
| Type | `type` | ✓ |
| Priorité | `priorite` | ✓ |
| Date | `dateArrivee` | ✓ |
| Délai | `delai` | ✓ |
| Actions | `actions` | ✓ |

**Stockage local:** `courrier-interne-recus-columns`

---

#### **`/src/components/CourrierInterne/CourrierInterneEnvoyesTab.tsx`** (442 lignes)
Onglet des courriers internes envoyés (structure identique à RecusTab)

**Différences avec RecusTab:**
- Filtre: `emetteur: user?.id` (courriers dont l'utilisateur est émetteur)
- Colonne "Destinataire" au lieu de "Émetteur"
- Stockage local: `courrier-interne-envoyes-columns`

---

## Modifications des fichiers existants

### **`/src/App.tsx`**
**Ajouts:**
```tsx
import CourrierInterne from "./pages/CourrierInterne";

// Dans le routing:
<Route path="courrier-interne" element={<CourrierInterne />} />
```

---

### **`/src/contexts/LanguageContext.tsx`**
**Nouvelles clés de traduction ajoutées:**

```typescript
{
  internalMail: { fr: 'Courrier Interne', en: 'Internal Mail' },
  internalMailSubtitle: { 
    fr: 'Gestion des courriers internes du ministère',
    en: 'Ministry internal mail management'
  },
  newInternalMail: { fr: 'Nouveau Courrier', en: 'New Mail' },
  internalMailManagement: { 
    fr: 'Gestion des Courriers Internes',
    en: 'Internal Mail Management'
  },
  myInternalMails: { 
    fr: 'Mes courriers internes',
    en: 'My internal mails'
  },
  sentInternalMails: { 
    fr: 'Courrier interne envoyés',
    en: 'Sent internal mails'
  },
  internalMailProcessing: { 
    fr: 'Traitement du courrier interne',
    en: 'Internal mail processing'
  },
  internalMailDescription: { 
    fr: 'Remplissez le formulaire pour créer un nouveau courrier interne',
    en: 'Fill in the form to create a new internal mail'
  },
  noInternalMailsFound: { 
    fr: 'Aucun courrier interne trouvé',
    en: 'No internal mails found'
  },
  noSentInternalMailsFound: { 
    fr: 'Aucun courrier envoyé trouvé',
    en: 'No sent mails found'
  },
  internalMailCreated: { 
    fr: 'Courrier interne créé',
    en: 'Internal mail created'
  },
  internalMailCreatedSuccess: { 
    fr: 'Le courrier interne a été créé avec succès',
    en: 'The internal mail has been created successfully'
  },
  internalMailError: { 
    fr: 'Erreur lors de la création du courrier interne',
    en: 'Error creating internal mail'
  },
  destinationPosition: { 
    fr: 'Poste destinataire',
    en: 'Destination position'
  },
  selectDestinationPosition: { 
    fr: 'Sélectionner le poste destinataire',
    en: 'Select destination position'
  },
  transferType: { 
    fr: 'Type de transfert',
    en: 'Transfer type'
  },
  selectTransferType: { 
    fr: 'Sélectionner le type de transfert',
    en: 'Select transfer type'
  },
  publicComment: { 
    fr: 'Commentaire public',
    en: 'Public comment'
  },
  enterPublicComment: { 
    fr: 'Entrez votre commentaire...',
    en: 'Enter your comment...'
  }
}
```

---

### **`/src/pages/CourriersTraitement.tsx`**
**Ajouts:**

1. **Import:**
```tsx
import { useNavigate } from "react-router-dom";
```

2. **Hook:**
```tsx
const navigate = useNavigate();
```

3. **Bouton de navigation (dans l'en-tête):**
```tsx
<Button 
  onClick={() => navigate('/courrier-interne')}
  className="w-full sm:w-auto"
  variant="outline"
>
  <ArrowRightLeft className="h-4 w-4 mr-2" />
  {t('internalMail')}
</Button>
```

---

## Architecture technique

### Structure des dossiers
```
src/
├── pages/
│   └── CourrierInterne.tsx
├── components/
│   └── CourrierInterne/
│       ├── CourrierInterneModal.tsx
│       ├── CourrierInterneRecusTab.tsx
│       └── CourrierInterneEnvoyesTab.tsx
└── contexts/
    └── LanguageContext.tsx (modifié)
```

### Flux de données

```
┌─────────────────────────────────────────┐
│      CourrierInterne.tsx (Page)         │
│  - État: activeTab, showModal           │
│  - Callback: handleCourrierCreated()    │
└─────────────────┬───────────────────────┘
                  │
        ┌─────────┴─────────┐
        │                   │
┌───────▼───────┐  ┌────────▼────────┐
│  RecusTab     │  │  EnvoyesTab     │
│  (Reçus)      │  │  (Envoyés)      │
│               │  │                 │
│ Filter:       │  │ Filter:         │
│ destinataire  │  │ emetteur        │
│ = user?.id    │  │ = user?.id      │
└───────────────┘  └─────────────────┘
        │                   │
        └─────────┬─────────┘
                  │
        ┌─────────▼──────────┐
        │ getTransmissions() │
        │  (API courriers)   │
        └────────────────────┘
```

### API utilisées

| Endpoint | Usage | Fichier |
|----------|-------|---------|
| `getParentServices()` | Chargement services | CourrierInterneModal.tsx |
| `createTransmission()` | Création courrier | CourrierInterneModal.tsx |
| `getTransmissions()` | Liste courriers | RecusTab + EnvoyesTab |
| `getCourrier()` | Détails courrier | MailDetailsSheet |

---

## Patterns utilisés

### 1. **React Hook Form + Zod**
Validation de formulaire avec schéma TypeScript-safe:
```tsx
const form = useForm<CourrierInterneFormData>({
  resolver: zodResolver(courrierInterneSchema),
  defaultValues: { ... }
});
```

### 2. **Composants shadcn/ui**
- Dialog (modal)
- Form (react-hook-form)
- Button, Badge, Card
- Tabs, TabsList, TabsTrigger
- Select, Input, Textarea

### 3. **Contextes React**
- `useLanguage()` - Traductions FR/EN
- `useUser()` - Données utilisateur connecté
- `useToast()` - Notifications toast

### 4. **Filtrage client-side**
```tsx
const filteredCourriers = useMemo(() => {
  return courriers.filter(c => {
    // Filtres date, priorité, recherche...
  });
}, [courriers, filters, debouncedSearch]);
```

### 5. **Stockage localStorage**
```tsx
const [visibleColumns, setVisibleColumns] = useLocalStorage(
  'courrier-interne-recus-columns',
  defaultColumns
);
```

---

## Fonctionnalités implémentées

✅ **Page de gestion avec 2 onglets**
- Mes courriers internes (reçus)
- Courriers envoyés

✅ **Modal de création**
- Formulaire validé (Zod)
- Sélection hiérarchique de services
- Upload de fichiers
- Types de transfert (Instruction/Visa/Transfert)

✅ **Filtres avancés**
- Recherche texte (référence, objet)
- Filtre par date (plage)
- Filtre par priorité
- Filtre par catégorie

✅ **Tableau personnalisable**
- Sélection des colonnes visibles
- Tri des colonnes
- Pagination moderne

✅ **Actions sur courriers**
- Vue détaillée (MailDetailsSheet)
- Sélection multiple
- Actions groupées (à venir)

✅ **Traductions**
- Français
- Anglais

✅ **Navigation**
- Bouton dans CourriersTraitement
- Route `/courrier-interne`

---

## Prochaines étapes (recommandations)

### Backend
- [ ] Créer endpoint dédié `/api/courriers-internes`
- [ ] Ajouter filtre `type: 'interne'` dans getTransmissions
- [ ] Implémenter gestion des pièces jointes
- [ ] Ajouter statistiques courriers internes

### Frontend
- [ ] Implémenter actions groupées (transmission multiple, impression)
- [ ] Ajouter graphiques statistiques (Dashboard)
- [ ] Implémenter système de notifications temps réel
- [ ] Ajouter historique des modifications
- [ ] Améliorer responsive mobile

### Tests
- [ ] Tests unitaires (Vitest)
- [ ] Tests d'intégration
- [ ] Tests E2E (Playwright)

---

## Points d'attention

⚠️ **Backend API**
Actuellement, le module utilise l'API `getTransmissions()` existante. Pour une meilleure séparation, créer un endpoint dédié.

⚠️ **Permissions**
Vérifier les permissions utilisateur avant d'afficher le bouton de navigation et la page.

⚠️ **Performance**
Si le nombre de courriers internes est élevé, implémenter la pagination côté serveur.

---

## Conclusion

Le module Courrier Interne est **fonctionnel et prêt pour les tests**. Il respecte l'architecture existante du projet et réutilise les composants existants pour une cohérence maximale.

**Isolation:** Le module est complètement isolé dans `/src/components/CourrierInterne/` et n'affecte pas les autres fonctionnalités.

**Évolutivité:** La structure permet d'ajouter facilement de nouvelles fonctionnalités (actions groupées, statistiques, etc.).

---

## Commandes de test

```bash
# Démarrer le serveur de développement
npm run dev

# Naviguer vers
http://localhost:5173/courrier-interne

# Ou cliquer sur le bouton "Courrier Interne" 
# depuis la page Courriers Traitement
```

---

**Document créé le:** Janvier 2025  
**Auteur:** GitHub Copilot  
**Version:** 1.0
