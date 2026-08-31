# MINEPIA — Application de gestion du patrimoine

Application web de gestion du patrimoine du **Ministère de l'Élevage, des Pêches et des Industries Animales** (Cameroun). Elle centralise le suivi des biens, inventaires, programmations d'acquisitions, maintenances, projets, statistiques et configuration.

## Stack technique

- **React 18** + **TypeScript 5**
- **Vite** (dev/build)
- **React Router DOM** — routing centralisé, pas de file-based routing
- **Tailwind CSS** + **shadcn/ui** — design system par tokens sémantiques (`src/styles.css`)
- **Recharts** — graphiques
- **lucide-react** — icônes
- **sonner** — toasts
- i18n maison (FR/EN) via `src/utils/i18n.tsx`

Application front-only : toutes les données proviennent de mocks déterministes.

## Démarrage

```bash
bun install
bun run dev       # http://localhost:8080
bun run build     # build de production dans dist/
bun run preview   # serveur de preview du build
```

## Architecture générale

```
minepia/
├── index.html                  Point d'entrée HTML (favicon = logo MINEPIA)
├── public/
│   ├── minepia-logo.png        Logo servi statiquement + favicon
│   └── robots.txt
├── src/
│   ├── main.tsx                Bootstrap React (createRoot)
│   ├── App.tsx                 Providers globaux (Theme, i18n, Toaster, Router)
│   ├── router.tsx              Configuration React Router
│   ├── styles.css              Tokens Tailwind + variables CSS (thème clair/sombre)
│   │
│   ├── api/                    ⭐ Un fichier par module métier
│   │   ├── common.ts           unites, detenteurs, formatFCFA
│   │   ├── biens.ts            Type Bien + mockBiens
│   │   ├── inventaires.ts      Type Inventaire + mockInventaires
│   │   ├── programmation.ts    Type Programmation + mockProgrammations
│   │   ├── maintenance.ts      Type Maintenance + mockMaintenances
│   │   ├── projets.ts          Projet, ProjetBien, MouvementProjet, HistoriqueProjet
│   │   └── configuration.ts    User, mockUnites, mockCategories, mockExercices
│   │
│   ├── pages/                  Une page par route
│   │   ├── Index.tsx           Redirection / → /dashboard
│   │   ├── Authentification.tsx
│   │   ├── Dashboard.tsx
│   │   ├── Biens.tsx
│   │   ├── Inventaires.tsx
│   │   ├── Programmation.tsx
│   │   ├── Maintenance.tsx
│   │   ├── Projets.tsx
│   │   ├── Statistiques.tsx
│   │   ├── Configuration.tsx
│   │   └── NotFound.tsx
│   │
│   ├── layouts/
│   │   └── RootLayout.tsx      Outlet racine
│   │
│   ├── components/
│   │   ├── ui/                 Primitives shadcn (button, dialog, table, ...)
│   │   ├── shared/             AppShell, DataTable, Pagination, StatCard,
│   │   │                       StatusBadge, ExportButton, ViewShell
│   │   └── theme/              ThemeProvider (dark/light)
│   │
│   ├── hooks/                  use-mobile, useViewStack
│   ├── services/               Réservé aux appels API réels futurs
│   └── utils/
│       ├── utils.ts            cn(), helpers
│       ├── i18n.tsx            Provider et hooks FR/EN
│       └── mock-data.ts        Barrel de compat → réexporte src/api/*
└── vite.config.ts
```

## Modules métier

Chaque module suit la même convention : **une page** dans `src/pages/`, un **fichier de données** dans `src/api/`, une **entrée de nav** dans `AppShell`, et une **route** dans `src/router.tsx`.

| Route               | Page                    | Données (`src/api/`) | Objectif                                       |
| ------------------- | ----------------------- | -------------------- | ---------------------------------------------- |
| `/dashboard`        | `Dashboard.tsx`         | agrège tous          | Vue d'ensemble et indicateurs clés             |
| `/biens`            | `Biens.tsx`             | `biens.ts`           | Inventaire des biens du patrimoine             |
| `/inventaires`      | `Inventaires.tsx`       | `inventaires.ts`     | Campagnes d'inventaire (général, mutation…)   |
| `/programmation`    | `Programmation.tsx`     | `programmation.ts`   | Programmation prévisionnelle des acquisitions  |
| `/maintenance`      | `Maintenance.tsx`       | `maintenance.ts`     | Demandes et suivi des maintenances             |
| `/projets`          | `Projets.tsx`           | `projets.ts`         | Gestion patrimoniale par projet MINEPIA        |
| `/statistiques`     | `Statistiques.tsx`      | agrège tous          | Analyses et graphiques                         |
| `/configuration`    | `Configuration.tsx`     | `configuration.ts`   | Utilisateurs, unités, catégories, exercices    |
| `/authentification` | `Authentification.tsx`  | —                    | Connexion et réinitialisation                  |

### Conventions

- **Pas de modales** pour les vues métier — les pages basculent entre plusieurs vues internes (`liste`, `detail`, `creation`, `edition`, `affectation`, `mouvements`, `historique`, `statistiques`) via `useViewStack`.
- Couleurs, ombres et rayons sont des **tokens sémantiques** définis dans `src/styles.css`. Ne jamais hardcoder `bg-white`, `text-black`, `#hex` dans les composants.
- Le logo est servi depuis `public/minepia-logo.png` : il est disponible aussi bien en dev qu'en déploiement, et sert de favicon.
- Les données mock sont **déterministes** (aucun `Math.random`) pour un rendu stable.

### Ajouter un nouveau module

1. Créer `src/api/<module>.ts` avec les types et les données mock.
2. Créer `src/pages/<Module>.tsx`.
3. Déclarer la route dans `src/router.tsx`.
4. Ajouter l'entrée dans `navItems` de `src/components/shared/AppShell.tsx` et les libellés FR/EN dans `src/utils/i18n.tsx`.

## Internationalisation

`I18nProvider` (`src/utils/i18n.tsx`) expose `useT()` et un sélecteur FR/EN. Toutes les clés vivent dans un unique fichier.

## Thème

`ThemeProvider` (`src/components/theme/ThemeProvider.tsx`) gère le mode clair/sombre. Le bouton de bascule est présent dans l'entête et dans l'écran d'authentification.

## Assets

- `public/minepia-logo.png` — logo MINEPIA servi statiquement à `/minepia-logo.png` (référence stable en production, aussi utilisée comme favicon).
- `src/assets/minepia-logo.png.asset.json` — pointeur CDN Lovable (utilisé côté code par import ES).

## Licence

Projet interne — Ministère de l'Élevage, des Pêches et des Industries Animales, République du Cameroun.