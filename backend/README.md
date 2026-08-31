# API Gestion du Patrimoine

API REST backend pour la plateforme de **gestion du patrimoine** (administration, organigramme, projets patrimoniaux, taxonomie des biens, découpage territorial, maintenance, réévaluation, dépréciation, sortie de biens, sécurisation).

> Document de référence technique complet.  
> Documentation interactive : Swagger UI sur `/` · Spec OpenAPI sur `/doc.json`  
> Guide admin complémentaire : [`README_ADMIN_API.md`](README_ADMIN_API.md)

---

## Table des matières

1. [Présentation](#1-présentation)
2. [Stack technique](#2-stack-technique)
3. [Architecture](#3-architecture)
4. [Structure du projet](#4-structure-du-projet)
5. [Logique métier & conventions](#5-logique-métier--conventions)
6. [Authentification & sécurité](#6-authentification--sécurité)
7. [Format des réponses API](#7-format-des-réponses-api)
8. [Catalogue des endpoints](#8-catalogue-des-endpoints)
9. [Entités & modèle de données](#9-entités--modèle-de-données)
10. [Relations entre tables](#10-relations-entre-tables)
11. [Base de données & migrations](#11-base-de-données--migrations)
12. [Configuration & démarrage](#12-configuration--démarrage)
13. [Points d'attention pour les futurs développements](#13-points-dattention-pour-les-futurs-développements)
14. [TODO et problèmes connus](#14-todo-et-problèmes-connus)

---

## 1. Présentation

Cette API expose les ressources nécessaires à un back-office de gestion du patrimoine :

| Domaine | Contenu |
|---|---|
| **Administration** | Utilisateurs, profil, rôles, permissions (RBAC), groupes |
| **Organisation** | Services (organigramme hiérarchique + rattachement territorial), types d'organigramme |
| **Projets** | Projets patrimoniaux et responsables |
| **Référentiel biens** | Catégories → Types de biens (`AssetType`) → Sous-types (`AssetSubType`) → États (`EtatBien`) ; Champs personnalisés |
| **Biens (Asset)** | Inventaire patrimonial (ManyToMany catégories/types/états/services/projets/PJ/champs), photos & documents, géolocalisation |
| **Affectations** | Affectation de biens à des services/utilisateurs avec pièces jointes |
| **Maintenance** | Opérations de maintenance sur les biens (coût, date, observations) |
| **Réévaluation** | Réévaluation de la valeur des biens (méthode, motif, observations) |
| **Dépréciation** | Calcul et suivi de la dépréciation des biens |
| **Sortie de biens** | Gestion des sorties de biens avec BSP (Bon de Sortie Provisoire) |
| **Sécurisation** | Modes de sécurisation et documents de sécurité |
| **Pièces jointes** | Entité `PieceJointe` (`id`, `nom`, `chemin`) liée aux biens et autres entités |
| **Territoire** | Régions → Départements → Arrondissements (+ import batch + `GET /locations` / `GET /cartographie` avec `?search=`) |
| **Auth** | JWT + refresh token + 2FA OTP par e-mail (optionnelle) |

Le domaine métier vise un contexte administratif (découpage territorial type Cameroun, organigramme institutionnel, gestion de biens patrimoniaux).

---

## 2. Stack technique

| Composant | Technologie |
|---|---|
| Langage | PHP ≥ 8.2 |
| Framework | **Symfony 7.4** |
| ORM | **Doctrine ORM 3.6** + Doctrine Migrations |
| Base de données | **MySQL 8.x** (`gestion_patrimoine`, utf8mb4) |
| Authentification | **Lexik JWT Authentication Bundle 3.2** (RSA) |
| Documentation API | **Nelmio ApiDoc Bundle 5.10** (OpenAPI / Swagger) |
| E-mail | Symfony Mailer + Twig (OTP 2FA) |
| Validation | Symfony Validator (Assert sur les entités) |
| Sérialisation | Symfony Serializer (+ Normalizers métier) |
| Qualité (dev) | PHPStan, PHP-CS-Fixer, Maker Bundle, Fixtures + Faker |

---

## 3. Architecture

Architecture **monolithe Symfony** en couches classiques, sans modules Nest-like.

```
┌─────────────────────────────────────────────────────────────┐
│  Client (Frontend)                                          │
│  Authorization: Bearer <JWT>                                │
└───────────────────────────┬─────────────────────────────────┘
                            │
┌───────────────────────────▼─────────────────────────────────┐
│  public/index.php → App\Kernel                              │
│  EventSubscribers : CORS · Exceptions → JSON                │
└───────────────────────────┬─────────────────────────────────┘
                            │
┌───────────────────────────▼─────────────────────────────────┐
│  Security (firewalls)                                       │
│  login → /login_check  │  api → JWT sur ^/                  │
└───────────────────────────┬─────────────────────────────────┘
                            │
┌───────────────────────────▼─────────────────────────────────┐
│  Controllers (1 action ≈ 1 classe)                          │
│  src/Controller/{Resource}/*Controller.php                  │
└───────────────────────────┬─────────────────────────────────┘
                            │
        ┌───────────────────┼───────────────────┐
        ▼                   ▼                   ▼
┌───────────────┐  ┌────────────────┐  ┌────────────────────┐
│ Services      │  │ Repositories   │  │ Normalizers        │
│ métier / API  │  │ Doctrine       │  │ Serializer         │
└───────┬───────┘  └───────┬────────┘  └────────────────────┘
        │                  │
        └────────┬─────────┘
                 ▼
        ┌────────────────┐
        │ Entities       │
        │ Doctrine ORM   │
        └───────┬────────┘
                ▼
        ┌────────────────┐
        │ MySQL          │
        └────────────────┘
```

### Couches

| Couche | Emplacement | Rôle |
|---|---|---|
| **Controllers** | `src/Controller/` | 1 endpoint par classe, annotations OpenAPI, hydratation JSON manuelle |
| **Services** | `src/Service/` | Réponses uniformes, pagination, OTP, refresh tokens, organigramme, import localités |
| **Repositories** | `src/Repository/` | Requêtes Doctrine, filtres soft-delete |
| **Entities** | `src/Entity/` | Mapping Doctrine + Assert + Groups Serializer |
| **Normalizers** | `src/Serializer/` | Enrichissement des payloads (profil, responsables projet, listes…) |
| **Security** | `src/Security/` | Handlers succès/échec login JWT |
| **EventSubscribers** | `src/EventSubscriber/` | CORS, format d'erreur JSON global |

### Pattern dominant

- **Pas de DTO dédiés** : le JSON de la requête est lu dans le contrôleur, les contraintes vivent sur les entités.
- Réponse toujours via `ApiResponseFactory` : `{ success, status, message, data }`.
- Soft-delete fréquent via le flag `is_delete` (+ endpoints `restore` sur les référentiels).
- RBAC (rôles/permissions) **stocké en base** mais **pas encore appliqué** au runtime (pas de Voters / `IsGranted` métier).

---

## 4. Structure du projet

```
api_gestion_patrimoine/
├── bin/                      # Console Symfony (bin/console)
├── config/
│   ├── packages/             # security, doctrine, jwt, nelmio, mailer…
│   ├── jwt/                  # Clés RSA Lexik
│   ├── routes.yaml           # Entrée routing + /login_check
│   └── services.yaml         # Autowire App\
├── docs/                     # Diagrammes
├── migrations/               # Migrations Doctrine
├── public/
│   └── index.php             # Front controller HTTP
├── src/
│   ├── Controller/           # Endpoints groupés par ressource
│   │   ├── Auth/
│   │   ├── Users/
│   │   ├── Roles/
│   │   ├── Permissions/
│   │   ├── Groupes/
│   │   ├── Projects/
│   │   ├── Services/
│   │   ├── TypeOrganigrammes/
│   │   ├── Categories/
│   │   ├── AssetTypes/
│   │   ├── AssetSubTypes/
│   │   ├── EtatBiens/
│   │   ├── Champs/
│   │   ├── Inputs/
│   │   ├── Assets/
│   │   ├── AssetAssignments/
│   │   ├── AssetMaintenances/
│   │   ├── AssetReevaluations/
│   │   ├── AssetDepreciations/
│   │   ├── AssetExits/
│   │   ├── Bsps/
│   │   ├── Securities/
│   │   ├── SecurityModes/
│   │   ├── ExitTypes/
│   │   ├── Upload/
│   │   ├── Cartographie/
│   │   ├── Locations/
│   │   ├── Regions/
│   │   ├── Departements/
│   │   ├── Arrondissements/
│   │   └── Inventaire/
│   ├── DataFixtures/         # Jeux de données de démo
│   ├── Entity/               # Modèle Doctrine (34 entités)
│   ├── EventSubscriber/      # CORS, exceptions JSON
│   ├── Repository/           # Repositories Doctrine (34 repositories)
│   ├── Security/             # Handlers JWT login
│   ├── Serializer/           # Normalizers métier
│   ├── Service/              # Services transverses (38 services)
│   └── Kernel.php
├── templates/email/          # Templates Twig (OTP)
├── tests/
├── var/                      # Cache, logs
├── composer.json
├── .env                      # Configuration environnement
└── README.md                 # Ce fichier
```

### Points d'entrée

| Contexte | Fichier |
|---|---|
| HTTP | `public/index.php` → `App\Kernel` |
| CLI | `bin/console` |
| Routes | Attributs `#[Route]` sur les contrôleurs + `config/routes.yaml` |

---

## 5. Logique métier & conventions

### Services transverses

| Service | Rôle |
|---|---|
| `ApiResponseFactory` | Envelope JSON uniforme succès / erreur |
| `PaginationFactory` | Structure `{ data, pagination: { page, limit, total, pages } }` |
| `RefreshTokenService` | Création (TTL 7 jours), validation, rotation |
| `OtpService` | OTP 6 chiffres, validité 5 min, envoi e-mail Twig |
| `ServiceHierarchyBuilder` | Construction de l'arbre organigramme depuis une liste plate |
| `LocationRegistrationService` | Création/restauration régions (max ~10 actives), import batch transactionnel |
| `AssetManagementService` | Logique de création/mise à jour des biens avec gestion des fichiers |
| `AssetAssignmentService` | Gestion des affectations de biens |
| `AssetMaintenanceService` | Gestion des maintenances |
| `AssetReevaluationService` | Gestion des réévaluations |
| `AssetDepreciationService` | Gestion des dépréciations |
| `AssetExitService` | Gestion des sorties de biens |
| `BspService` | Gestion des Bon de Sortie Provisoire |
| `SecurityService` | Gestion des sécurisations |
| `FileUploadService` | Gestion des uploads de fichiers |
| `GeoFileParserService` | Parsing des fichiers géospatiaux |
| `InventaireService` | Génération d'inventaires paginés |
| `StatisticsService` | Calcul de statistiques |

### Authentification (flux)

1. `POST /login_check` avec `{ email, password }`
2. Si compte inactif ou soft-supprimé → refus
3. Si 2FA activée → réponse `requires_otp` (pas de JWT immédiat) + e-mail OTP
4. Sinon → JWT + refresh token
5. `POST /auth/verify-otp` pour finaliser la 2FA
6. `POST /refresh_token` pour renouveler le JWT

### Règles métier notables

- Un utilisateur peut avoir **au maximum 2 rôles métier** (`User::MAX_ASSIGNED_ROLES = 2`).
- Deux systèmes de « rôles » cohabitent :
  - `$roles` (JSON) → rôles Symfony Security (`ROLE_USER`, …)
  - `$assignedRoles` (ManyToMany `Role`) → rôles métier applicatifs
- Statuts projet : enum `ProjectStatus` → `PLANIFIE` | `EN COURS` | `TERMINE`
- Statuts demande de réforme : `EN_ATTENTE` | `VALIDEE` | `REJETEE`
- Soft-delete (`is_delete`) sur la plupart des entités ; restore disponible sur catégories, asset-types, régions, départements, arrondissements.
- Limite métier : environ **10 régions actives** maximum.
- Géolocalisation des biens via `Location` et `AssetLocation` (latitude, longitude, GeoJSON).
- Les biens peuvent avoir des champs personnalisés via `Champ` et `Input`.

### Groups Serializer (exemples)

`user:read`, `user:detail`, `role:list`, `role:detail`, `permission:*`, `project:list`, `project:detail`, `service:list`, `service:detail`, `category:*`, `asset_type:*`, `region:*`, `asset:detail`, `asset_maintenance:detail`, `asset_reevaluation:detail`, `asset_depreciation:detail`, `asset_exit:detail`, `bsp:*`, `security:*`, etc.

---

## 6. Authentification & sécurité

### Firewalls (`config/packages/security.yaml`)

| Firewall | Pattern | Comportement |
|---|---|---|
| `dev` | `/_profiler`, `/_wdt`, assets | Sécurité désactivée |
| `login` | `^/login` | `json_login` → `/login_check` (email/password) |
| `api` | `^/` | JWT Lexik |

### Routes publiques

- `POST /login_check`
- `POST /refresh_token`
- `POST /auth/verify-otp`
- `GET /` (Swagger UI)
- `GET /doc.json`

### Routes protégées

Tout le reste nécessite un JWT valide avec au minimum `ROLE_USER` :

```http
Authorization: Bearer <token>
```

### CORS

Géré par `CorsSubscriber` (origines frontend locales / IP déployée — ports typiques 8080, 5173, 8074).

---

## 7. Format des réponses API

### Succès / erreur

```json
{
  "success": true,
  "status": 200,
  "message": "…",
  "data": { }
}
```

### Liste paginée

```json
{
  "success": true,
  "status": 200,
  "message": "",
  "data": {
    "data": [ ],
    "pagination": {
      "page": 1,
      "limit": 10,
      "total": 42,
      "pages": 5
    }
  }
}
```

Les exceptions non gérées sont transformées en JSON par `ExceptionSubscriber` selon le même envelope.

---

## 8. Catalogue des endpoints

Sauf mention contraire : **JWT Bearer requis** (`ROLE_USER`).

### 8.1 Authentification & documentation

| Méthode | Path | Auth | Description |
|---|---|---|---|
| `POST` | `/login_check` | Public | Login e-mail/mot de passe → JWT+refresh ou flux OTP |
| `POST` | `/auth/verify-otp` | Public | Valide l'OTP 2FA → JWT+refresh |
| `POST` | `/refresh_token` | Public | Rotation refresh → nouveau JWT |
| `GET` | `/` | Public | Swagger UI |
| `GET` | `/doc.json` | Public | Spec OpenAPI JSON |

### 8.2 Utilisateurs & profil

| Méthode | Path | Description |
|---|---|---|
| `GET` | `/users` | Liste paginée |
| `POST` | `/users` | Création (`role_ids` max 2, `service_id`, `twoFactorEnabled`…) |
| `GET` | `/users/{id}` | Détail |
| `PUT` / `PATCH` | `/users/{id}` | Mise à jour |
| `DELETE` | `/users/{id}` | Suppression |
| `PATCH` | `/users/{id}/password` | Changer le mot de passe (admin) |
| `PATCH` | `/users/{id}/two-factor` | Activer / désactiver la 2FA |
| `GET` | `/profile` | Profil de l'utilisateur connecté |
| `PATCH` | `/profile/password` | Changer son propre mot de passe |

### 8.3 Rôles

| Méthode | Path | Description |
|---|---|---|
| `GET` | `/roles` | Liste |
| `POST` | `/roles` | Créer |
| `GET` | `/roles/{id}` | Détail |
| `PUT` / `PATCH` | `/roles/{id}` | Modifier |
| `DELETE` | `/roles/{id}` | Suppression physique |
| `DELETE` | `/roles/{id}/soft-delete` | Soft-delete |
| `GET` | `/roles/{roleId}/permissions` | Permissions du rôle |
| `POST` | `/roles/{roleId}/permissions` | Assigner plusieurs permissions |
| `POST` | `/roles/{roleId}/permissions/{permissionId}` | Assigner une permission |
| `DELETE` | `/roles/{roleId}/permissions/{permissionId}` | Retirer une permission |

### 8.4 Permissions

| Méthode | Path | Description |
|---|---|---|
| `GET` | `/permissions` | Liste |
| `POST` | `/permissions` | Créer |
| `GET` | `/permissions/{id}` | Détail |
| `PUT` / `PATCH` | `/permissions/{id}` | Modifier |
| `DELETE` | `/permissions/{id}` | Suppression physique |
| `DELETE` | `/permissions/{id}/soft-delete` | Soft-delete |

### 8.5 Groupes

| Méthode | Path | Description |
|---|---|---|
| `GET` | `/groupes` | Liste |
| `POST` | `/groupes` | Créer |
| `GET` | `/groupes/{id}` | Détail |
| `PUT` / `PATCH` | `/groupes/{id}` | Modifier |
| `DELETE` | `/groupes/{id}` | Suppression |
| `GET` | `/groupes/{id}/permissions` | Permissions du groupe |
| `POST` | `/groupes/{id}/permissions` | Assigner des permissions |
| `DELETE` | `/groupes/{id}/permissions/{permissionId}` | Retirer une permission |

### 8.6 Projets

| Méthode | Path | Description |
|---|---|---|
| `GET` | `/projects` | Liste |
| `POST` | `/projects` | Créer (+ `responsable_ids`) |
| `GET` | `/projects/{id}` | Détail |
| `PUT` / `PATCH` | `/projects/{id}` | Modifier (hors statut dédié) |
| `PATCH` | `/projects/{id}/status` | Changer le statut |
| `DELETE` | `/projects/{id}` | Suppression physique |
| `DELETE` | `/projects/{id}/soft-delete` | Soft-delete |

### 8.7 Services & organigramme

| Méthode | Path | Description |
|---|---|---|
| `GET` | `/services` | Liste (plate / hiérarchie selon params) |
| `POST` | `/services` | Créer |
| `GET` | `/services/{id}` | Détail |
| `PUT` / `PATCH` | `/services/{id}` | Modifier |
| `DELETE` | `/services/{id}` | Supprimer |
| `GET` | `/services/{id}/children` | Enfants directs |
| `GET` | `/organigramme` | Arbre organigramme (`?search=`, `?type_service=POSTE\|SERVICE`) |

### 8.8 Types d'organigramme

| Méthode | Path | Description |
|---|---|---|
| `GET` | `/type-organigrammes` | Liste |
| `POST` | `/type-organigrammes` | Créer |
| `GET` | `/type-organigrammes/{id}` | Détail |
| `PATCH` | `/type-organigrammes/{id}` | Modifier |
| `DELETE` | `/type-organigrammes/{id}` | Supprimer |

### 8.9 Catégories de biens

| Méthode | Path | Description |
|---|---|---|
| `GET` | `/categories` | Liste |
| `POST` | `/categories` | Créer |
| `GET` | `/categories/{id}` | Détail (+ assetTypes + champs) |
| `PUT` / `PATCH` | `/categories/{id}` | Modifier |
| `DELETE` | `/categories/{id}/soft-delete` | Soft-delete |
| `POST` | `/categories/{id}/restore` | Restaurer |
| `GET` | `/categories/{id}/champs` | Champs associés à la catégorie |
| `POST` | `/categories/{id}/champs` | Associer/synchroniser `champ_ids` |

### 8.10 Types de biens (AssetTypes)

| Méthode | Path | Description |
|---|---|---|
| `GET` | `/asset-types` | Liste (`dureeVie`, `taux`) |
| `POST` | `/asset-types` | Créer (lié à `category_id`) |
| `GET` | `/asset-types/{id}` | Détail (+ états associés) |
| `PUT` / `PATCH` | `/asset-types/{id}` | Modifier |
| `DELETE` | `/asset-types/{id}/soft-delete` | Soft-delete |
| `POST` | `/asset-types/{id}/restore` | Restaurer |
| `GET` | `/asset-types/{id}/etat-biens` | États autorisés pour ce type |

### 8.11 Sous-types de biens (AssetSubTypes)

| Méthode | Path | Description |
|---|---|---|
| `GET` | `/asset-sub-types` | Liste |
| `POST` | `/asset-sub-types` | Créer (lié à `asset_type_id`) |
| `GET` | `/asset-sub-types/{id}` | Détail |
| `PATCH` | `/asset-sub-types/{id}` | Modifier |
| `DELETE` | `/asset-sub-types/{id}/soft-delete` | Soft-delete |
| `POST` | `/asset-sub-types/{id}/restore` | Restaurer |

### 8.12 États de biens (EtatBiens)

| Méthode | Path | Description |
|---|---|---|
| `GET` | `/etat-biens` | Liste (+ `assetTypes`) — filtre `?asset_type_id=` |
| `POST` | `/etat-biens` | Créer (+ `asset_type_ids`) |
| `GET` | `/etat-biens/{id}` | Détail (+ `assetTypes`) |
| `PATCH` | `/etat-biens/{id}` | Modifier + synchroniser `asset_type_ids` |
| `DELETE` | `/etat-biens/{id}/soft-delete` | Soft-delete |
| `POST` | `/etat-biens/{id}/restore` | Restaurer |

### 8.13 Champs personnalisés

| Méthode | Path | Description |
|---|---|---|
| `GET` | `/champs` | Liste |
| `POST` | `/champs` | Créer |
| `GET` | `/champs/{id}` | Détail (+ catégories) |
| `PATCH` | `/champs/{id}` | Modifier |
| `DELETE` | `/champs/{id}/soft-delete` | Soft-delete |
| `POST` | `/champs/{id}/restore` | Restaurer |
| `GET` | `/champs/{id}/categories` | Catégories associées |

### 8.14 Inputs (valeurs de champs personnalisés)

| Méthode | Path | Description |
|---|---|---|
| `GET` | `/inputs` | Liste |
| `POST` | `/inputs` | Créer |
| `GET` | `/inputs/{id}` | Détail |
| `PATCH` | `/inputs/{id}` | Modifier |
| `DELETE` | `/inputs/{id}` | Supprimer |

### 8.15 Biens patrimoniaux (Assets)

| Méthode | Path | Description |
|---|---|---|
| `GET` | `/assets` | Liste paginée avec filtres |
| `POST` | `/assets` | Création multipart (champs + fichiers) |
| `GET` | `/assets/{id}` | Détail complet |
| `PATCH` | `/assets/{id}` | Modification |
| `DELETE` | `/assets/{id}/soft-delete` | Soft-delete |
| `GET` | `/assets/{id}/champs` | Champs personnalisés du bien |
| `POST` | `/assets/{id}/champs` | Associer des champs |
| `PATCH` | `/assets/{id}/etat` | Changer l'état du bien |
| `GET` | `/assets/{id}/location/current` | Localisation actuelle |
| `GET` | `/assets/{id}/location/history` | Historique des localisations |
| `POST` | `/assets/{id}/location` | Ajouter une localisation |

### 8.16 Affectations de biens

| Méthode | Path | Description |
|---|---|---|
| `POST` | `/asset-assignments` | Créer une affectation |
| `GET` | `/asset-assignments/{id}` | Détail |
| `PATCH` | `/asset-assignments/{id}` | Modifier |
| `DELETE` | `/asset-assignments/{id}` | Supprimer |
| `DELETE` | `/asset-assignments/{id}/piece-jointe/{pjId}` | Supprimer une PJ |

### 8.17 Maintenances

| Méthode | Path | Description |
|---|---|---|
| `POST` | `/asset-maintenances` | Créer une maintenance |
| `GET` | `/asset-maintenances/{id}` | Détail |
| `PATCH` | `/asset-maintenances/{id}` | Modifier |
| `DELETE` | `/asset-maintenances/{id}` | Supprimer |
| `DELETE` | `/asset-maintenances/{id}/piece-jointe/{pjId}` | Supprimer une PJ |

### 8.18 Réévaluations

| Méthode | Path | Description |
|---|---|---|
| `POST` | `/asset-reevaluations` | Créer une réévaluation |
| `GET` | `/asset-reevaluations/{id}` | Détail |
| `PATCH` | `/asset-reevaluations/{id}` | Modifier |
| `DELETE` | `/asset-reevaluations/{id}` | Supprimer |
| `DELETE` | `/asset-reevaluations/{id}/piece-jointe/{pjId}` | Supprimer une PJ |

### 8.19 Dépréciations

| Méthode | Path | Description |
|---|---|---|
| `POST` | `/asset-depreciations` | Créer une dépréciation |
| `GET` | `/asset-depreciations/{id}` | Détail |
| `PATCH` | `/asset-depreciations/{id}` | Modifier |
| `DELETE` | `/asset-depreciations/{id}` | Supprimer |
| `DELETE` | `/asset-depreciations/{id}/piece-jointe/{pjId}` | Supprimer une PJ |

### 8.20 Sorties de biens

| Méthode | Path | Description |
|---|---|---|
| `POST` | `/asset-exits` | Créer une sortie |
| `GET` | `/asset-exits/{id}` | Détail |
| `GET` | `/asset-exits/by-asset/{assetId}` | Sorties d'un bien |
| `PATCH` | `/asset-exits/{id}` | Modifier |
| `DELETE` | `/asset-exits/{id}` | Supprimer |
| `DELETE` | `/asset-exits/{id}/piece-jointe/{pjId}` | Supprimer une PJ |

### 8.21 Bon de Sortie Provisoire (BSP)

| Méthode | Path | Description |
|---|---|---|
| `GET` | `/bsps` | Liste |
| `POST` | `/bsps` | Créer un BSP |
| `GET` | `/bsps/{id}` | Détail |
| `PATCH` | `/bsps/{id}` | Modifier |
| `DELETE` | `/bsps/{id}` | Supprimer |
| `GET` | `/bsps/{id}/pdf` | Générer le PDF |

### 8.22 Sécurisation

| Méthode | Path | Description |
|---|---|---|
| `GET` | `/securities` | Liste |
| `POST` | `/securities` | Créer une sécurisation |
| `GET` | `/securities/{id}` | Détail |
| `PATCH` | `/securities/{id}` | Modifier |
| `DELETE` | `/securities/{id}` | Supprimer |

### 8.23 Modes de sécurisation

| Méthode | Path | Description |
|---|---|---|
| `GET` | `/security-modes` | Liste |
| `POST` | `/security-modes` | Créer |
| `GET` | `/security-modes/{id}` | Détail |
| `PATCH` | `/security-modes/{id}` | Modifier |
| `DELETE` | `/security-modes/{id}` | Supprimer |

### 8.24 Types de sortie

| Méthode | Path | Description |
|---|---|---|
| `GET` | `/exit-types` | Liste |
| `POST` | `/exit-types` | Créer |
| `GET` | `/exit-types/{id}` | Détail |
| `PATCH` | `/exit-types/{id}` | Modifier |
| `DELETE` | `/exit-types/{id}` | Supprimer |

### 8.25 Upload & cartographie

| Méthode | Path | Description |
|---|---|---|
| `POST` | `/upload` | Upload multipart (`file`, `nom` optionnel) |
| `GET` | `/cartographie` | Arbre Région → Département → Arrondissement |
| `GET` | `/locations` | Même arbre |
| `GET` | `/locations/tree` | Alias de `/locations` |
| `POST` | `/locations/batch-import` | Import batch |

### 8.26 Territoire

| Méthode | Path | Description |
|---|---|---|
| `GET` | `/regions` | Liste |
| `POST` | `/regions` | Créer |
| `GET` | `/regions/{id}` | Détail |
| `PATCH` | `/regions/{id}` | Modifier |
| `DELETE` | `/regions/{id}/soft-delete` | Soft-delete |
| `POST` | `/regions/{id}/restore` | Restaurer |
| `GET` | `/departements` | Liste |
| `POST` | `/departements` | Créer |
| `GET` | `/departements/{id}` | Détail |
| `PATCH` | `/departements/{id}` | Modifier |
| `DELETE` | `/departements/{id}/soft-delete` | Soft-delete |
| `POST` | `/departements/{id}/restore` | Restaurer |
| `GET` | `/arrondissements` | Liste |
| `POST` | `/arrondissements` | Créer |
| `GET` | `/arrondissements/{id}` | Détail |
| `PATCH` | `/arrondissements/{id}` | Modifier |
| `DELETE` | `/arrondissements/{id}/soft-delete` | Soft-delete |
| `POST` | `/arrondissements/{id}/restore` | Restaurer |

### 8.27 Inventaire

| Méthode | Path | Description |
|---|---|---|
| `GET` | `/inventaire` | Inventaire paginé par catégorie |

### 8.28 Statistiques

| Méthode | Path | Description |
|---|---|---|
| `GET` | `/statistics` | Statistiques globales |

---

## 9. Entités & modèle de données

Fichiers : `src/Entity/` (34 entités).

### 9.1 `User` — table `user`

| Champ | Type | Nullable | Notes |
|---|---|---|---|
| `id` | int | PK | Auto-increment |
| `firstName` | string(191) | non | |
| `lastName` | string(191) | non | |
| `email` | string(191) | non | Unique |
| `matricule` | string(255) | oui | |
| `cni` | string(255) | oui | |
| `roles` | json/array | non | Rôles Symfony Security |
| `password` | string | non | Hashé |
| `is_active` | bool | non | Défaut `true` |
| `createdAt` | datetime_immutable | non | |
| `service_id` | FK → `service` | **oui** | `onDelete: SET NULL` |
| `is_delete` | bool | non | Soft-delete |
| `two_factor_enabled` | bool | non | |
| `otp_code` | string(10) | **oui** | |
| `otp_expires_at` | datetime | **oui** | |
| `assignedRoles` | M2M → `Role` | | JoinTable `user_role` (max 2) |
| `permissions` | M2M → `Permission` | | JoinTable `user_permission` |
| `groupes` | M2M → `Groupe` | | JoinTable `user_groupe` |
| `projects` | M2M → `Project` | | côté inverse |

Implémente `UserInterface` + `PasswordAuthenticatedUserInterface`.

### 9.2 `Role` — table `role`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `nom` | string(255) unique | |
| `description` | string(500) nullable | |
| `is_active` | bool | |
| `createdAt` / `updatedAt` | datetime_immutable | |
| `is_delete` | bool | Soft-delete |
| `permissions` | M2M → `Permission` | JoinTable `role_permission` |
| `users` | M2M inverse → `User` | |

### 9.3 `Permission` — table `permission`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `nom` | string unique | Ex. `gerer_biens`, `voir_rapports` |
| `description` | nullable | |
| `is_active` | bool | |
| `createdAt` / `updatedAt` | datetime_immutable | |
| `is_delete` | bool | Soft-delete |
| `roles` | M2M inverse → `Role` | |
| `groupes` | M2M inverse → `Groupe` | |
| `users` | M2M inverse → `User` | |

### 9.4 `Groupe` — table `groupe`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `nom` | string(255) unique | |
| `description` | string(500) nullable | |
| `is_active` | bool | |
| `createdAt` / `updatedAt` | datetime_immutable | |
| `is_delete` | bool | Soft-delete |
| `permissions` | M2M → `Permission` | JoinTable `groupe_permission` |
| `users` | M2M inverse → `User` | |

### 9.5 `Project` — table `project`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `nom` | string(255) | |
| `description` | string(1000) nullable | |
| `dateDebut` | datetime_immutable | Obligatoire |
| `dateFinPrevue` | datetime_immutable nullable | |
| `statut` | string(255) | Défaut `PLANIFIE` — valeurs : enum `ProjectStatus` |
| `exercice` | int | Année d'exercice |
| `createdAt` / `updatedAt` | datetime_immutable | |
| `is_delete` | bool | Soft-delete |
| `users` | M2M → `User` | JoinTable `project_user` (responsables) |

### 9.6 `ProjectStatus` — enum PHP

```
PLANIFIE | EN COURS | TERMINE
```

### 9.7 `Service` — table `service`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `nom` | string(255) unique | |
| `sigle` | string nullable | |
| `code` | string nullable | |
| `type_service` | string nullable | |
| `ordre` | int | Ordre d'affichage |
| `is_active` | bool | |
| `created_at` / `updated_at` | datetime_immutable | |
| `parent_id` | self-FK nullable | `onDelete: SET NULL` |
| `children` | OneToMany self | |
| `typeOrganigrammes` | M2M inverse | |
| `region_id` | FK → `region` nullable | `onDelete: SET NULL` |
| `departement_id` | FK → `departement` nullable | `onDelete: SET NULL` |
| `arrondissement_id` | FK → `arrondissement` nullable | `onDelete: SET NULL` |
| `assetAssignments` | OneToMany → `AssetAssignment` | |

### 9.8 `TypeOrganigramme` — table `type_organigramme`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `nom` | string(191) unique | |
| `description` | text nullable | |
| `created_at` / `updated_at` | datetime_immutable | |
| `is_delete` | bool | Soft-delete |
| `services` | M2M → `Service` | JoinTable `type_organigramme_service` |

### 9.9 `Category` — table `category`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `nom` | string unique | |
| `description` | nullable | |
| `createdAt` / `updatedAt` | datetime_immutable | |
| `is_delete` | bool | Soft-delete |
| `isDefault` | bool | Catégorie par défaut |
| `assetTypes` | OneToMany → `AssetType` | |
| `champs` | M2M → `Champ` | JoinTable `category_champ` (propriétaire) |

### 9.10 `AssetType` — table `asset_type`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `nom` | string unique | |
| `description` | nullable | |
| `dureeVie` | integer nullable | Durée de vie en années |
| `taux` | decimal(10,2) nullable | Taux d'amortissement |
| `createdAt` / `updatedAt` | datetime_immutable | |
| `is_delete` | bool | Soft-delete |
| `category_id` | FK → `category` NOT NULL | `onDelete: RESTRICT` |
| `etatBiens` | M2M inverse → `EtatBien` | |
| `assetSubTypes` | OneToMany → `AssetSubType` | |

### 9.11 `AssetSubType` — table `asset_sub_type`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `nom` | string unique | |
| `description` | nullable | |
| `createdAt` / `updatedAt` | datetime_immutable | |
| `is_delete` | bool | Soft-delete |
| `asset_type_id` | FK → `asset_type` NOT NULL | `onDelete: CASCADE` |

### 9.12 `EtatBien` — table `etat_bien`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `nom` | string(191) unique | |
| `createdAt` / `updatedAt` | datetime_immutable | |
| `is_delete` | bool | Soft-delete |
| `assetTypes` | M2M → `AssetType` | JoinTable `asset_type_etat_bien` (propriétaire) |

### 9.13 `Champ` — table `champ`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `nom` | string(191) unique | |
| `typeChamp` | string(100) | Ex. `texte`, `nombre`, `date` |
| `valeur` | string(255) nullable | |
| `createdAt` / `updatedAt` | datetime_immutable | |
| `is_delete` | bool | Soft-delete |
| `category_id` | FK → `category` nullable | `onDelete: SET NULL` |
| `categories` | M2M inverse → `Category` | |
| `assets` | M2M inverse → `Asset` | |
| `inputs` | OneToMany → `Input` | |

### 9.14 `Input` — table `input`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `valeur` | text | Valeur du champ |
| `createdAt` / `updatedAt` | datetime_immutable | |
| `champ_id` | FK → `champ` NOT NULL | `onDelete: CASCADE` |
| `asset_id` | FK → `asset` NOT NULL | `onDelete: CASCADE` |
| `champ` | ManyToOne → `Champ` | |
| `asset` | ManyToOne → `Asset` | |

### 9.15 `PieceJointe` — table `piece_jointe`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `nom` | string(255) nullable | Nom affiché (optionnel) |
| `chemin` | string(500) | Chemin public (ex. `/uploads/assets/documents/...`) |
| `createdAt` / `updatedAt` | datetime_immutable | |

### 9.16 `Asset` — table `asset`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `reference` | string(100) unique nullable | Auto `PAT-YYYY-NNNNN` |
| `nom` | string(255) nullable | |
| `numeroSerie` | string(255) nullable | |
| `description` | text nullable | |
| `dateAcquisition` | date nullable | |
| `valeur` | decimal(18,2) nullable | |
| `sourceFinancement` | string nullable | |
| `modeAcquisition` | string nullable | |
| `statut` | string(50) nullable | Défaut `ACTIF` à la création |
| `exercice` | int | Année d'exercice |
| `typeFournisseur` ... `fournisseurPays` | strings nullable | Infos fournisseur embarquées |
| `createdAt` / `updatedAt` | datetime_immutable | |
| `is_delete` | bool | Soft-delete |
| `categories` | M2M → `Category` | JoinTable `asset_category` |
| `assetTypes` | M2M → `AssetType` | JoinTable `asset_asset_type` |
| `etatBiens` | M2M → `EtatBien` | JoinTable `asset_etat_bien` |
| `services` | M2M → `Service` | JoinTable `asset_service` |
| `projects` | M2M → `Project` | JoinTable `asset_project` |
| `piecesJointes` | M2M → `PieceJointe` | JoinTable `asset_piece_jointe` |
| `champs` | M2M → `Champ` | JoinTable `asset_champ` |
| `assetAssignments` | OneToMany → `AssetAssignment` | |
| `maintenances` | M2M inverse → `AssetMaintenance` | |
| `reevaluations` | M2M inverse → `AssetReevaluation` | |
| `depreciations` | M2M inverse → `AssetDepreciation` | |
| `sortie` | OneToOne → `AssetExit` | |
| `assetLocations` | OneToMany → `AssetLocation` | |
| `assetSecurities` | OneToMany → `AssetSecurity` | |
| `reformRequests` | OneToMany → `AssetReformRequest` | |

### 9.17 `AssetAssignment` — table `asset_assignment`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `dateAffectation` | date nullable | |
| `observations` | text nullable | |
| `createdAt` / `updatedAt` | datetime_immutable | |
| `asset_id` | FK → `asset` | |
| `service_id` | FK → `service` | |
| `user_id` | FK → `user` nullable | `onDelete: SET NULL` |
| `asset` | ManyToOne → `Asset` | |
| `service` | ManyToOne → `Service` | |
| `user` | ManyToOne → `User` | |
| `pieceJointes` | M2M → `PieceJointe` | JoinTable `asset_assignment_piece_jointe` |

### 9.18 `AssetMaintenance` — table `asset_maintenance`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `etatBien_id` | FK → `etat_bien` nullable | |
| `motif` | string(255) nullable | |
| `cout` | decimal(18,2) nullable | |
| `dateIntervention` | date nullable | |
| `dateRecuperation` | date nullable | |
| `observations` | text nullable | |
| `createdAt` / `updatedAt` | datetime | |
| `assets` | M2M → `Asset` | JoinTable `asset_maintenance_link` |
| `pieceJointes` | M2M → `PieceJointe` | JoinTable `maintenance_piece_jointe` |

### 9.19 `AssetReevaluation` — table `asset_reevaluation`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `valeurActuelle` | decimal(18,2) nullable | |
| `nouvelleValeur` | decimal(18,2) nullable | |
| `methodeEvaluation` | string(255) nullable | |
| `service_id` | FK → `service` nullable | |
| `dateReevaluation` | date nullable | |
| `motif` | string(255) nullable | |
| `observations` | text nullable | |
| `createdAt` / `updatedAt` | datetime | |
| `assets` | M2M → `Asset` | JoinTable `asset_reevaluation_link` |
| `pieceJointes` | M2M → `PieceJointe` | JoinTable `reevaluation_piece_jointe` |

### 9.20 `AssetDepreciation` — table `asset_depreciation`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `typeDepreciation` | string(255) nullable | |
| `methodeAmortissement` | string(255) nullable | |
| `dureeVie` | integer nullable | |
| `valeurActuelle` | decimal(18,2) nullable | |
| `tauxDepreciation` | decimal(18,2) nullable | |
| `montantDepreciation` | decimal(18,2) nullable | |
| `dateDepreciation` | date nullable | |
| `motif` | string(255) nullable | |
| `observations` | text nullable | |
| `createdAt` / `updatedAt` | datetime | |
| `assets` | M2M → `Asset` | JoinTable `asset_depreciation_link` |
| `pieceJointes` | M2M → `PieceJointe` | JoinTable `depreciation_piece_jointe` |

### 9.21 `AssetExit` — table `asset_exit`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `asset_id` | FK → `asset` unique NOT NULL | `onDelete: CASCADE` |
| `service_id` | FK → `service` nullable | |
| `motifSortie` | string(255) nullable | |
| `dateSortie` | date nullable | |
| `protocoleReference` | string(255) nullable | |
| `observations` | text nullable | |
| `createdAt` / `updatedAt` | datetime | |
| `is_delete` | bool | Soft-delete |
| `exit_type_id` | FK → `exit_type` nullable | |
| `pieceJointes` | M2M → `PieceJointe` | JoinTable `asset_exit_piece_jointe` |
| `bsps` | OneToMany → `Bsp` | |
| `asset` | OneToOne → `Asset` | |
| `service` | ManyToOne → `Service` | |
| `exitType` | ManyToOne → `ExitType` | |

### 9.22 `Bsp` — table `bsp`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `numero` | string(50) unique | |
| `asset_exit_id` | FK → `asset_exit` NOT NULL | `onDelete: CASCADE` |
| `service_id` | FK → `service` nullable | |
| `beneficiaire_id` | FK → `user` nullable | `onDelete: SET NULL` |
| `created_by_id` | FK → `user` nullable | `onDelete: SET NULL` |
| `quantiteDemandee` | integer nullable | |
| `quantiteAccordee` | integer nullable | |
| `quantiteServie` | integer NOT NULL | |
| `observations` | text nullable | |
| `dateEtablissement` | date nullable | |
| `createdAt` / `updatedAt` | datetime | |
| `is_delete` | bool | Soft-delete |
| `pieceJointes` | M2M → `PieceJointe` | JoinTable `bsp_piece_jointe` |
| `assetExit` | ManyToOne → `AssetExit` | |
| `service` | ManyToOne → `Service` | |
| `beneficiaire` | ManyToOne → `User` | |
| `createdBy` | ManyToOne → `User` | |

### 9.23 `ExitType` — table `exit_type`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `nom` | string(100) unique | |
| `description` | string(255) nullable | |
| `code` | string(50) | |
| `isActive` | bool | Défaut `true` |
| `createdAt` / `updatedAt` | datetime_immutable | |
| `is_delete` | bool | Soft-delete |
| `assetExits` | OneToMany → `AssetExit` | |

### 9.24 `Security` — table `security`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `security_mode_id` | FK → `security_mode` NOT NULL | `onDelete: RESTRICT` |
| `dateSecurisation` | date_immutable nullable | |
| `createdAt` / `updatedAt` | datetime_immutable | |
| `is_delete` | bool | Soft-delete |
| `assetSecurities` | OneToMany → `AssetSecurity` | |
| `securityDocuments` | OneToMany → `SecurityDocument` | |
| `securityMode` | ManyToOne → `SecurityMode` | |

### 9.25 `SecurityMode` — table `security_mode`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `nom` | string(255) | |
| `description` | text nullable | |
| `createdAt` / `updatedAt` | datetime_immutable | |
| `is_delete` | bool | Soft-delete |
| `securities` | OneToMany → `Security` | |

### 9.26 `SecurityDocument` — table `security_document`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `security_id` | FK → `security` NOT NULL | `onDelete: CASCADE` |
| `piece_jointe_id` | FK → `piece_jointe` NOT NULL | `onDelete: RESTRICT` |
| `createdAt` / `updatedAt` | datetime_immutable | |
| `is_delete` | bool | Soft-delete |
| `security` | ManyToOne → `Security` | |
| `pieceJointe` | ManyToOne → `PieceJointe` | |

### 9.27 `AssetSecurity` — table `asset_security`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `security_id` | FK → `security` NOT NULL | `onDelete: CASCADE` |
| `asset_id` | FK → `asset` NOT NULL | `onDelete: CASCADE` |
| `createdAt` / `updatedAt` | datetime_immutable | |
| `is_delete` | bool | Soft-delete |
| `security` | ManyToOne → `Security` | |
| `asset` | ManyToOne → `Asset` | |

### 9.28 `AssetReformRequest` — table `asset_reform_request`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `asset_id` | FK → `asset` NOT NULL | `onDelete: CASCADE` |
| `statut` | string(50) | `EN_ATTENTE`, `VALIDEE`, `REJETEE` |
| `previousEtat` | text nullable | |
| `createdAt` | datetime_immutable | |
| `created_by_id` | FK → `user` nullable | `onDelete: SET NULL` |
| `validatedAt` | datetime_immutable nullable | |
| `validated_by_id` | FK → `user` nullable | `onDelete: SET NULL` |
| `is_delete` | bool | Soft-delete |
| `pieceJointes` | M2M → `PieceJointe` | JoinTable `asset_reform_request_piece_jointe` |
| `asset` | ManyToOne → `Asset` | |
| `createdBy` | ManyToOne → `User` | |
| `validatedBy` | ManyToOne → `User` | |

### 9.29 `Location` — table `location`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `latitude` | decimal(10,8) nullable | |
| `longitude` | decimal(11,8) nullable | |
| `geometry` | text nullable | GeoJSON |
| `createdAt` / `updatedAt` | datetime_immutable | |
| `assetLocations` | OneToMany → `AssetLocation` | |

### 9.30 `AssetLocation` — table `asset_location`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `asset_id` | FK → `asset` NOT NULL | `onDelete: CASCADE` |
| `location_id` | FK → `location` NOT NULL | `onDelete: CASCADE` |
| `createdAt` / `updatedAt` | datetime_immutable | |
| `is_delete` | bool | Soft-delete |
| `asset` | ManyToOne → `Asset` | |
| `location` | ManyToOne → `Location` | |

### 9.31 `Region` — table `region`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `nom` | string(100) unique | |
| `code` | string(50) nullable | |
| `createdAt` / `updatedAt` | datetime_immutable | |
| `is_delete` | bool | Soft-delete |
| `departements` | OneToMany → `Departement` | |

### 9.32 `Departement` — table `departement`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `nom` | string(100) | Unique avec la région `(nom, region)` |
| `code` | string(50) nullable | |
| `createdAt` / `updatedAt` | datetime_immutable | |
| `is_delete` | bool | Soft-delete |
| `region_id` | FK → `region` NOT NULL | `onDelete: RESTRICT` |
| `arrondissements` | OneToMany → `Arrondissement` |
| `region` | ManyToOne → `Region` | |

### 9.33 `Arrondissement` — table `arrondissement`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `nom` | string(100) | Unique avec le département `(nom, departement)` |
| `code` | string(50) nullable | |
| `createdAt` / `updatedAt` | datetime_immutable | |
| `is_delete` | bool | Soft-delete |
| `departement_id` | FK → `departement` NOT NULL | `onDelete: RESTRICT` |
| `departement` | ManyToOne → `Departement` | |

### 9.34 `RefreshToken` — table `refresh_token`

| Champ | Type | Notes |
|---|---|---|
| `id` | int PK | |
| `user_id` | FK → `user` NOT NULL | `onDelete: CASCADE` |
| `refresh_token` | string(191) unique | |
| `createdAt` | datetime_immutable | |
| `expiresAt` | datetime | TTL ~7 jours |
| `isRevoked` | bool | |
| `user` | ManyToOne → `User` | |

---

## 10. Relations entre tables

### Diagramme entité-relation (simplifié)

```
┌──────────────┐       user_role        ┌──────────────┐
│     User     │◄──────────────────────►│     Role     │
└──────┬───────┘                        └──────┬───────┘
       │                                       │
       │ ManyToOne                             │ role_permission
       │ service_id                            │
       ▼                                       ▼
┌──────────────┐                        ┌──────────────┐
│   Service    │◄── parent_id (self)    │  Permission  │
└──────┬───────┘                        └──────────────┘
       │ region / departement / arrondissement (ManyToOne, SET NULL)
       │ type_organigramme_service
       ▼
┌──────────────────┐
│ TypeOrganigramme │
└──────────────────┘

┌──────────────┐     project_user      ┌──────────────┐
│   Project    │◄─────────────────────►│     User     │
└──────────────┘                       └──────┬───────┘
                                              │ refresh_token.user_id
                                              ▼
                                       ┌──────────────┐
                                       │ RefreshToken │
                                       └──────────────┘

┌──────────────┐  M2M  ┌──────────────────┐  1──*  ┌──────────────┐
│   Category   │◄─────►│    AssetType     │───────►│ AssetSubType │
└──────┬───────┘       │                  │       └──────────────┘
       │               │                  │
       │               │                  │ M2M (référentiel)
       │               │                  ├◄──M2M──► EtatBien
       │               │                  ├◄──M2M──► Service
       │               │                  ├◄──M2M──► Project
       │               │                  ├◄──M2M──► PieceJointe
       │               │                  ├◄──M2M──► Champ
       │ category_champ│                  │
       ▼               │                  │
┌──────────────┐       │                  │
│    Champ     │       │                  │
└──────┬───────┘       │                  │
       │               │                  │
       │ OneToMany     │                  │
       ▼               │                  │
┌──────────────┐       │                  │
│    Input     │       │                  │
└──────────────┘       │                  │
                       │                  │
                       │                  │ OneToMany
                       │                  ├──────────────┐
                       │                  │AssetAssignment│
                       │                  ├──────────────┐
                       │                  │AssetLocation  │
                       │                  ├──────────────┐
                       │                  │AssetSecurity  │
                       │                  ├──────────────┐
                       │                  │AssetReformReq│
                       │                  └──────────────┘
                       │
                       │ M2M inverse
                       ├──────────────┐
                       │AssetMainten. │
                       ├──────────────┐
                       │AssetReeval.  │
                       ├──────────────┐
                       │AssetDeprec.  │
                       └──────────────┘
                       │
                       │ OneToOne
                       ▼
                  ┌──────────────┐
                  │  AssetExit   │
                  └──────┬───────┘
                         │ OneToMany
                         ▼
                  ┌──────────────┐
                  │     Bsp      │
                  └──────────────┘

┌──────────────┐  1──*  ┌──────────────┐  1──*  ┌────────────────┐
│    Region    │───────►│ Departement  │───────►│ Arrondissement │
└──────────────┘        └──────────────┘        └────────────────┘

┌──────────────┐  1──*  ┌──────────────┐
│ SecurityMode │───────►│  Security    │
└──────────────┘        └──────┬───────┘
                               │ OneToMany
                               ├──────────────┐
                               │AssetSecurity │
                               ├──────────────┐
                               │SecurityDoc.  │
                               └──────────────┘

┌──────────────┐  1──*  ┌──────────────┐
│  ExitType   │───────►│  AssetExit   │
└──────────────┘        └──────────────┘
```

### Synthèse des cardinalités principales

| Relation | Type | Table de jointure / FK | Règle suppression |
|---|---|---|---|
| User → Service | ManyToOne | `user.service_id` | `SET NULL` |
| User ↔ Role | ManyToMany | `user_role` | — (max 2 rôles côté app) |
| User ↔ Permission | ManyToMany | `user_permission` | — |
| User ↔ Groupe | ManyToMany | `user_groupe` | — |
| Role ↔ Permission | ManyToMany | `role_permission` | — |
| Groupe ↔ Permission | ManyToMany | `groupe_permission` | — |
| Project ↔ User | ManyToMany | `project_user` | — |
| Service → Service | ManyToOne (parent) | `service.parent_id` | `SET NULL` |
| Service → Region/Departement/Arrondissement | ManyToOne | `service.region_id` / `departement_id` / `arrondissement_id` | `SET NULL` |
| TypeOrganigramme ↔ Service | ManyToMany | `type_organigramme_service` | — |
| Category → AssetType | OneToMany | `asset_type.category_id` | `RESTRICT` |
| Category ↔ Champ | ManyToMany | `category_champ` | `CASCADE` |
| AssetType → AssetSubType | OneToMany | `asset_sub_type.asset_type_id` | `CASCADE` |
| AssetType ↔ EtatBien | ManyToMany | `asset_type_etat_bien` | `CASCADE` |
| Asset ↔ Category | ManyToMany | `asset_category` | `CASCADE` |
| Asset ↔ AssetType | ManyToMany | `asset_asset_type` | `CASCADE` |
| Asset ↔ EtatBien | ManyToMany | `asset_etat_bien` | `CASCADE` |
| Asset ↔ Service | ManyToMany | `asset_service` | `CASCADE` |
| Asset ↔ Project | ManyToMany | `asset_project` | `CASCADE` |
| Asset ↔ PieceJointe | ManyToMany | `asset_piece_jointe` | `CASCADE` |
| Asset ↔ Champ | ManyToMany | `asset_champ` | `CASCADE` |
| Champ → Input | OneToMany | `input.champ_id` | `CASCADE` |
| Asset → AssetAssignment | OneToMany | `asset_assignment.asset_id` | — |
| Asset → AssetLocation | OneToMany | `asset_location.asset_id` | `CASCADE` |
| Asset → AssetSecurity | OneToMany | `asset_security.asset_id` | `CASCADE` |
| Asset → AssetReformRequest | OneToMany | `asset_reform_request.asset_id` | `CASCADE` |
| Asset ↔ AssetMaintenance | ManyToMany | `asset_maintenance_link` | — |
| Asset ↔ AssetReevaluation | ManyToMany | `asset_reevaluation_link` | — |
| Asset ↔ AssetDepreciation | ManyToMany | `asset_depreciation_link` | — |
| Asset → AssetExit | OneToOne | `asset_exit.asset_id` | `CASCADE` |
| AssetExit → Bsp | OneToMany | `bsp.asset_exit_id` | `CASCADE` |
| AssetExit → ExitType | ManyToOne | `asset_exit.exit_type_id` | `SET NULL` |
| SecurityMode → Security | OneToMany | `security.security_mode_id` | `RESTRICT` |
| Security → AssetSecurity | OneToMany | `asset_security.security_id` | `CASCADE` |
| Security → SecurityDocument | OneToMany | `security_document.security_id` | `CASCADE` |
| Location → AssetLocation | OneToMany | `asset_location.location_id` | `CASCADE` |
| Region → Departement | OneToMany | `departement.region_id` | `RESTRICT` |
| Departement → Arrondissement | OneToMany | `arrondissement.departement_id` | `RESTRICT` |
| RefreshToken → User | ManyToOne | `refresh_token.user_id` | `CASCADE` |

---

## 11. Base de données & migrations

### Migrations

Dossier `migrations/` — schéma construit progressivement.

Migration principale : `Version20260815093202.php` (ajout de contraintes foreign key et champ `exercice`).

Commandes :

```bash
php bin/console doctrine:migrations:migrate
php bin/console doctrine:schema:validate
```

### Fixtures (`src/DataFixtures/`)

| Fixture | Contenu typique |
|---|---|
| `AppFixtures` | Utilisateurs Faker (mot de passe de démo `password123`) |
| `PermissionFixtures` | Permissions métier de base |
| `RoleFixtures` | Rôles (Administrateur, Gestionnaire de Biens, …) |
| `ProjectFixtures` | Projets de démonstration |

```bash
php bin/console doctrine:fixtures:load
```

---

## 12. Configuration & démarrage

### Variables d'environnement

| Variable | Usage |
|---|---|
| `APP_ENV` / `APP_SECRET` | Framework |
| `DATABASE_URL` | Connexion MySQL |
| `JWT_SECRET_KEY` / `JWT_PUBLIC_KEY` / `JWT_PASSPHRASE` | Clés RSA Lexik |
| `MAILER_DSN` / `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` | Envoi des OTP |
| `DEFAULT_URI` | Génération d'URL en CLI |

Fichiers : `.env` (défauts) · `.env.local` (secrets locaux, non versionnés de préférence).

### Installation locale

```bash
composer install
# Configurer .env.local (DATABASE_URL, mailer, JWT…)
php bin/console lexik:jwt:generate-keypair
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load   # optionnel
symfony server:start
# ou : php -S 127.0.0.1:8000 -t public
```

Swagger UI : `http://localhost:8000/`

### Commandes utiles

```bash
php bin/console debug:router
php bin/console cache:clear
php bin/console make:controller   # Maker Bundle
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

---

## 13. Points d'attention pour les futurs développements

1. **Pattern contrôleur** — Continuer le modèle « 1 action = 1 classe » dans `src/Controller/{Resource}/`, avec annotations Nelmio pour Swagger.
2. **Réponses** — Toujours passer par `ApiResponseFactory` (et `PaginationFactory` pour les listes).
3. **Soft-delete** — Préférer `is_delete` + endpoints `soft-delete` / `restore` plutôt qu'une suppression physique, sauf cas déjà établis.
4. **RBAC** — Les permissions existent en base mais **ne sont pas encore appliquées** aux endpoints. Tout travail de sécurisation fine devra introduire Voters / `IsGranted` sans casser le contrat actuel.
5. **Pas de DTO** — Aujourd'hui le JSON est mappé manuellement sur les entités. Si le projet grossit, envisager des DTO Input/Output sans casser les groupes Serializer existants.
6. **Biens patrimoniaux** — Module Asset complet avec ManyToMany, uploads photos/PJ, géolocalisation, listing allégé, détail sans historiques.
7. **CORS** — Origines hardcodées dans le subscriber ; à adapter si nouveaux frontends.
8. **Secrets** — Ne jamais committer de secrets réels ; privilégier `.env.local` / Symfony Secrets en production.
9. **Uploads** — Photos et documents sont des `PieceJointe` distinguées par le chemin (`/photos/` vs `/documents/`). Un même document peut être lié à plusieurs biens.
10. **Géolocalisation** — Utiliser les entités `Location` et `AssetLocation` pour stocker les coordonnées géographiques.
11. **Maintenance/Réévaluation/Dépréciation** — Ces modules suivent le même pattern avec ManyToMany vers Asset et PieceJointe.
12. **Sortie de biens** — Gérer via `AssetExit` avec `Bsp` pour les bons de sortie provisoire.
13. **Sécurisation** — Utiliser `SecurityMode` et `Security` pour documenter les mesures de sécurité appliquées aux biens.

---

## 14. État d'avancement du projet

### Fonctionnalités récemment implémentées (Août 2026)

#### Consommables
- **Upload de pièces jointes** : Correction du traitement des fichiers dans `CreateConsumableController` et `UpdateConsumableController` pour utiliser `UploadedFilesNormalizer::parseLabelList()` comme les autres services (AssetExit, AssetMaintenance)
- **Documentation OpenAPI** : Ajout de descriptions détaillées pour les champs `piecesJointes[]` et `piecesJointesNoms[]` avec support CSV Swagger
- **Champs de prix** : Ajout de `prixInitial` et `prixTotal` dans l'entité Consumable et les API de création/mise à jour
- **Modification de quantité** : La quantité peut maintenant être modifiée lors de la mise à jour (contrairement à la création où elle est figée)
- **Méthode HTTP** : L'API de mise à jour utilise maintenant POST au lieu de PATCH

#### Projets
- **Champ exercice** : Le champ `exercice` peut maintenant être modifié lors de la mise à jour d'un projet (précédemment figé à la création)

#### Sorties de biens (AssetExit)
- **État Réformé** : Amélioration de la recherche de l'état "Réformé" pour éviter de sélectionner "A Reformer"
  - Recherche exacte prioritaire pour "Réformé" ou "Reformé"
  - Exclusion de "A Reformer" dans le fallback LIKE
  - Création automatique de l'état "Réformé" s'il n'existe pas dans la base
- **Réponse enrichie** : Ajout du statut et de l'état du bien dans la réponse de création de sortie

#### Biens patrimoniaux (Assets)
- **Géolocalisation** : Mise à jour individuelle de latitude et longitude autorisée si une localisation existe déjà
  - Pour créer une nouvelle localisation, les deux coordonnées doivent être fournies ensemble
  - Validation des plages (-90 à 90 pour latitude, -180 à 180 pour longitude)
- **API POST** : Ajout des champs latitude et longitude dans le schéma OpenAPI de `UpdateAssetPostController`

#### Améliorations générales
- **Normalisation des uploads** : Uniformisation du traitement des fichiers entre tous les services (Consumable, AssetExit, AssetMaintenance)
- **Validation des fichiers** : Ajout de vérifications systématiques pour les fichiers uploadés (UPLOAD_ERR_NO_FILE, isValid())
- **Messages d'erreur** : Messages d'erreur plus clairs pour les problèmes d'upload de fichiers

---

## 15. TODO et problèmes connus

### TODO identifiés

- **Application du RBAC** : Les permissions existent en base mais ne sont pas encore appliquées au runtime via Voters ou `IsGranted`.
- **Tests unitaires** : Renforcer les tests dans `tests/` pour couvrir les services et contrôleurs.
- **Validation des données** : Certaines validations côté contrôleur pourraient être déplacées vers des DTOs avec Symfony Validator.
- **Documentation Swagger** : Enrichir les exemples de requête/réponse pour tous les endpoints.

### Problèmes connus

- **Aucun problème critique identifié** dans le code actuel.
- **TODO dans UserRepository** : Un commentaire TODO a été identifié dans le repository (à vérifier).

### Points d'amélioration suggérés

- Implémenter un système de cache pour les requêtes fréquentes (listes de référentiels).
- Optimiser les requêtes N+1 lors du chargement des relations ManyToMany.
- Ajouter des logs structurés pour faciliter le debugging en production.
- Implémenter un système d'audit trail pour les modifications sensibles.

---

## Résumé pour un développeur qui rejoint le projet

> API REST **Symfony 7.4 / Doctrine / MySQL / JWT**, organisée en **contrôleurs fins par action**, avec réponses JSON uniformes, soft-delete généralisé, organigramme hiérarchique, RBAC stocké (pas encore enforced), référentiels biens et territoire, gestion complète du cycle de vie des biens (maintenance, réévaluation, dépréciation, sortie), géolocalisation, sécurisation, et auth JWT + refresh + 2FA e-mail.

Pour toute nouvelle API : créer l'entité + repository + migration, un contrôleur par verbe/route, documenter via Nelmio, respecter l'envelope `ApiResponseFactory`, et filtrer les soft-deleted côté repository.
