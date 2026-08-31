# Guide Complet : Implémentation du Module Administration

## 📋 Résumé des Changements

Ce document explique tout ce qui a été créé et généré pour le module Administration (Permissions, Rôles, Projets, Catégories, Types d'Actifs).

### 🆕 Entités Créées

1. **Permission** (`src/Entity/Permission.php`)
   - Propriétés: `id`, `nom` (unique), `description`, `isActive`, `createdAt`, `updatedAt`, `isDelete`
   - Relation: ManyToMany avec Role (inverse)

2. **Role** (`src/Entity/Role.php`)
   - Propriétés: `id`, `nom` (unique), `description`, `isActive`, `createdAt`, `updatedAt`, `isDelete`
   - Relations: ManyToMany owning avec Permission (table `role_permission`) et ManyToMany inverse avec User

3. **Project** (`src/Entity/Project.php`)
   - Propriétés: `id`, `nom`, `description`, `responsable`, `dateDebut`, `dateFinPrevue`, `statut` (enum), `createdAt`, `updatedAt`, `isDelete`
   - Relation: ManyToMany avec User (table `project_user`)

4. **ProjectStatus** (`src/Entity/ProjectStatus.php`)
   - Énumération PHP: `PLANIFIE`, `EN COURS`, `TERMINE`

5. **Category** (`src/Entity/Category.php`)
   - Propriétés: `id`, `nom` (unique), `description`, `createdAt`, `updatedAt`, `isDelete`
   - Note: Entité de support (pas d'API CRUD)

6. **AssetType** (`src/Entity/AssetType.php`)
   - Propriétés: `id`, `nom` (unique), `description`, `createdAt`, `updatedAt`, `isDelete`
   - Note: Entité de support (pas d'API CRUD)

### 📦 Repositories Créés

- `src/Repository/PermissionRepository.php` - avec méthodes `findPaginated()`, `search()`, `existsByNom()`, `save()`, `softDelete()`
- `src/Repository/RoleRepository.php` - idem + méthodes `assignPermission()`, `unassignPermission()`
- `src/Repository/ProjectRepository.php` - avec filtrage par statut
- `src/Repository/CategoryRepository.php` - repository basique
- `src/Repository/AssetTypeRepository.php` - repository basique

### 🛣️ Contrôleurs & Routes API Créés

#### Permissions
- `GET /permissions` - Lister (avec pagination et recherche)
- `POST /permissions` - Créer
- `GET /permissions/{id}` - Détail
- `PUT/PATCH /permissions/{id}` - Modifier
- `DELETE /permissions/{id}` - Supprimer (hard delete)
- `DELETE /permissions/{id}/soft-delete` - Soft delete (marquer comme supprimé)

#### Rôles
- `GET /roles` - Lister (avec pagination et recherche)
- `POST /roles` - Créer
- `GET /roles/{id}` - Détail
- `PUT/PATCH /roles/{id}` - Modifier
- `DELETE /roles/{id}` - Supprimer (hard delete)
- `DELETE /roles/{id}/soft-delete` - Soft delete
- `POST /roles/{roleId}/permissions/{permissionId}` - Assigner une permission
- `DELETE /roles/{roleId}/permissions/{permissionId}` - Retirer une permission
- `GET /roles/{roleId}/permissions` - Lister les permissions d'un rôle

#### Projets
- `GET /projects` - Lister (avec pagination et recherche par statut)
- `POST /projects` - Créer
- `GET /projects/{id}` - Détail
- `PUT/PATCH /projects/{id}` - Modifier
- `DELETE /projects/{id}` - Supprimer (hard delete)
- `DELETE /projects/{id}/soft-delete` - Soft delete

### 📝 Fixtures Créées

- `src/DataFixtures/PermissionFixtures.php` - 8 permissions d'exemple
- `src/DataFixtures/RoleFixtures.php` - 6 rôles avec permissions associées
- `src/DataFixtures/ProjectFixtures.php` - 5 projets d'exemple

### 🔧 Modifications

- **User** (`src/Entity/User.php`)
  - Ajout: Collection `assignedRoles` (ManyToMany, table `user_role`, max 2 rôles)
  - Ajout: Collection `projects` (ManyToMany inverse)
  - Conservation: propriété `$roles` (Symfony roles en tableau)

### 📊 Migration Générée

- `migrations/Version20260727111241.php` - Crée toutes les nouvelles tables et relations

---

## 🚀 COMMANDES À EXÉCUTER (DANS L'ORDRE)

### 1️⃣ Accéder au répertoire du projet

```powershell
cd "e:\GESTION DU PATRIMOINE\api_gestion_patrimoine"
```

**Explication:** Change le répertoire courant vers le dossier racine du projet Symfony.

---

### 2️⃣ Générer la migration (si pas déjà faite)

```powershell
php bin/console doctrine:migrations:diff --no-interaction
```

**Explication:**
- Analyse les différences entre le schéma Doctrine (`src/Entity/*.php`) et la base de données
- Génère automatiquement un fichier de migration PHP dans `migrations/`
- `--no-interaction`: Ne pose pas de questions (mode automatique)
- **Résultat attendu:** Un nouveau fichier `Version*.php` est créé dans le dossier `migrations/`

**Note:** Cette étape est déjà complétée. Si vous la relancez, elle créera une nouvelle migration si vous avez changé les entités.

---

### 3️⃣ Vider la base de données (optionnel mais recommandé)

Si vous voulez réinitialiser complètement la base de données:

**Option A: Via Doctrine (nettoie le schéma)**
```powershell
php bin/console doctrine:database:drop --force
php bin/console doctrine:database:create
```

**Explication:**
- `doctrine:database:drop --force`: Supprime complètement la base de données (destructif!)
- `doctrine:database:create`: Recrée la base de données vide
- `--force`: Force sans demander de confirmation

**Option B: Via Doctrine Migrations (applique un rollback complet)**
```powershell
php bin/console doctrine:migrations:migrate 0 --no-interaction
```

**Explication:**
- Revient à la version 0 (aucune migration appliquée)
- Puis relancez l'étape 4 pour réappliquer

---

### 4️⃣ Appliquer les migrations

```powershell
php bin/console doctrine:migrations:migrate --no-interaction
```

**Explication:**
- Exécute TOUTES les migrations en attente (fichiers `.php` dans `migrations/`)
- Crée les tables: `permission`, `role`, `project`, `category`, `asset_type`, `project_user`, `role_permission`, `user_role`
- Ajoute les colonnes manquantes à `user` et `service`
- Met à jour la table `_doctrine_migration_versions` pour tracer les migrations exécutées
- `--no-interaction`: N'affiche que les résultats importants
- **Résultat attendu:** Message de succès, tables créées

**Explication détaillée de la commande que vous m'avez montré:**

```powershell
cd "c:\Users\Administrateur\Desktop\Minepia Code\api_gestion_patrimoine" ; php bin/console doctrine:migrations:migrate --no-interaction 2>&1 | Select-String -NotMatch "Warning"
```

- `cd "..."` : Change le répertoire (remplace votre chemin)
- `;` : Sépare deux commandes PowerShell (exécute les deux)
- `php bin/console doctrine:migrations:migrate --no-interaction` : Applique les migrations
- `2>&1` : Redirige les erreurs vers la sortie standard (affiche tout)
- `| Select-String -NotMatch "Warning"` : Filtre et masque les lignes contenant "Warning"

**Résumé:** Cette commande applique les migrations et cache les avertissements.

---

### 5️⃣ Charger les fixtures (données d'exemple)

```powershell
php bin/console doctrine:fixtures:load --no-interaction
```

**Explication:**
- Charge les données d'exemple depuis `src/DataFixtures/*.php`
- Crée: 8 permissions, 6 rôles (avec permissions), 5 projets
- `--no-interaction`: Ne demande pas de confirmation (purge directement la DB)
- **Attention:** Purge les données existantes avant de charger les fixtures
- **Résultat attendu:** Message "Loaded XX fixtures"

**Alternative (avec confirmation):**
```powershell
php bin/console doctrine:fixtures:load
```

---

### 6️⃣ Vider le cache Symfony

```powershell
php bin/console cache:clear
```

**Explication:**
- Vide le cache de développement (`var/cache/dev/`)
- Symfony recalcule les routes, services, etc.
- Recommandé après ajouter du code ou des configurations
- **Résultat attendu:** Message "Cache cleared for the "dev" environment"

---

### 7️⃣ Vérifier la migration (optionnel)

```powershell
php bin/console doctrine:migrations:status
```

**Explication:**
- Affiche l'état actuel des migrations
- Montre lesquelles sont exécutées et lesquelles sont en attente
- Utile pour déboguer les problèmes de migration
- **Résultat attendu:** Liste des migrations avec statut "executed" ou "not executed"

---

### 8️⃣ Valider le schéma Doctrine (optionnel)

```powershell
php bin/console doctrine:schema:validate
```

**Explication:**
- Vérifie que les entités PHP correspondent aux tables en base de données
- Détecte les incohérences (colonnes manquantes, types mal configurés, etc.)
- **Résultat attendu:** "Mapping [OK]" et "Database [OK]"

---

### 9️⃣ Afficher les routes API (optionnel)

```powershell
php bin/console debug:router | Select-String "role|permission|project"
```

**Explication:**
- Affiche toutes les routes disponibles
- `| Select-String "role|permission|project"` : Filtre pour voir seulement les nouvelles routes
- **Résultat attendu:** Liste des routes `/permissions`, `/roles`, `/projects`, etc.

---

### 🔟 Lancer le serveur Symfony

```powershell
php bin/console server:run
```

ou 

```powershell
symfony server:start
```

**Explication:**
- Lance le serveur de développement Symfony
- Accessible sur `http://127.0.0.1:8000` ou `http://localhost:8000`
- **Pour arrêter:** `Ctrl+C`

**Alternative (si symfony CLI est installée):**
```powershell
symfony serve
```

---

## 📋 CHECKLISTE COMPLÈTE (À COPIER-COLLER)

✅ **DÉJÀ EXÉCUTÉE** - Voici ce qui a été fait:

```powershell
# 1. Accéder au projet
cd "e:\GESTION DU PATRIMOINE\api_gestion_patrimoine"

# 2. Vider complètement la base de données
php bin/console doctrine:database:drop --force

# 3. Créer une nouvelle base vide
php bin/console doctrine:database:create

# 4. Appliquer toutes les migrations
php bin/console doctrine:migrations:migrate --no-interaction

# 5. Charger les fixtures (données d'exemple)
php bin/console doctrine:fixtures:load --no-interaction

# 6. Vider le cache
php bin/console cache:clear
```

**Résultat:** 
- ✅ Base de données recréée: `gestion_patrimoine`
- ✅ 3 migrations appliquées: Version20260721174117, Version20260724120000, Version20260727123849
- ✅ Toutes les tables créées: service, user, refresh_token, asset_type, category, permission, project, role, role_permission, project_user, user_role
- ✅ Fixtures chargées:
  - 30 utilisateurs (AppFixtures)
  - 8 permissions (PermissionFixtures)
  - 6 rôles avec permissions associées (RoleFixtures)  
  - 5 projets (ProjectFixtures)
- ✅ Cache vidé

---

## 🔗 COMMANDES RAPIDES (COPIER-COLLER)

**Pour un projet existant sans réinitialisation:**
```powershell
cd "e:\GESTION DU PATRIMOINE\api_gestion_patrimoine"
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load --no-interaction
php bin/console cache:clear
php bin/console server:run
```

**Pour un projet neuf avec réinitialisation complète:**
```powershell
cd "e:\GESTION DU PATRIMOINE\api_gestion_patrimoine"
php bin/console doctrine:database:drop --force
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load --no-interaction
php bin/console cache:clear
php bin/console server:run
```

---

## 🎯 EXPLICATION RAPIDE DE VOTRE COMMANDE

```powershell
cd "c:\Users\Administrateur\Desktop\Minepia Code\api_gestion_patrimoine" ; php bin/console doctrine:migrations:migrate --no-interaction 2>&1 | Select-String -NotMatch "Warning"
```

✅ **Cette commande:**
1. Change le répertoire vers le projet
2. Applique toutes les migrations en attente
3. Masque les avertissements ("Warning")

✅ **Résultat:** Les tables sont créées dans la base de données

✅ **Exit Code 0 = Succès** ✅

---

## 🚀 LANCER LE SERVEUR

Une fois tout configuré, lancez le serveur:

```powershell
php bin/console server:run
```

Ou si vous avez la CLI Symfony:

```powershell
symfony serve
```

**Accessible sur:**
- API: `http://localhost:8000`
- Documentation Swagger: `http://localhost:8000/api/doc`

**Pour arrêter:** Appuyez sur `Ctrl+C`

---

## ✅ VERIFICATION FINALE

Vérifiez que tout fonctionne:

### 1. Vérifier les routes créées
```powershell
php bin/console debug:router | findstr "role|permission|project"
```

### 2. Vérifier le schéma
```powershell
php bin/console doctrine:schema:validate
```

### 3. Vérifier les migrations
```powershell
php bin/console doctrine:migrations:status
```

### 4. Tester l'API (une fois le serveur lancé)
- Ouvrir: `http://localhost:8000/api/doc`
- Tester une requête GET sur `/permissions`

---

## 📚 RÉSUMÉ DES FICHIERS CRÉÉS

### Entités (`src/Entity/`)
- `Permission.php` ✅
- `Role.php` ✅
- `Project.php` ✅
- `ProjectStatus.php` ✅
- `Category.php` ✅
- `AssetType.php` ✅
- `User.php` (modifié) ✅

### Repositories (`src/Repository/`)
- `PermissionRepository.php` ✅
- `RoleRepository.php` ✅
- `ProjectRepository.php` ✅
- `CategoryRepository.php` ✅
- `AssetTypeRepository.php` ✅

### Contrôleurs (`src/Controller/`)
- `Permissions/` (6 fichiers) ✅
- `Roles/` (9 fichiers) ✅
- `Projects/` (6 fichiers) ✅

### Fixtures (`src/DataFixtures/`)
- `PermissionFixtures.php` ✅
- `RoleFixtures.php` ✅
- `ProjectFixtures.php` ✅

### Migrations (`migrations/`)
- `Version20260727111241.php` ✅

### Configuration (`config/packages/`)
- `nelmio_api_doc.yaml` (modifié) ✅

---

## ❓ FAQ

**Q: Que fait exactement `doctrine:migrations:migrate`?**
R: Exécute tous les fichiers `.php` dans `migrations/` qui n'ont pas encore été exécutés. Chaque fichier contient du SQL pour créer/modifier les tables.

**Q: Pourquoi dois-je charger les fixtures?**
R: Les fixtures créent des données d'exemple (permissions, rôles, projets) pour tester l'API. Si vous ne les chargez pas, les tables seront vides.

**Q: Que fait `cache:clear`?**
R: Supprime les fichiers mis en cache par Symfony. Recommandé après ajouter du code.

**Q: Comment vérifier que tout est OK?**
R: Lancez `php bin/console doctrine:schema:validate` et `php bin/console debug:router`.

**Q: Puis-je réappliquer les migrations?**
R: Les migrations ne s'exécutent qu'une fois. Si vous voulez les réappliquer, utilisez `doctrine:migrations:migrate 0` puis `doctrine:migrations:migrate`.

---

**Généré le:** 2026-07-27
**Projet:** API Gestion du Patrimoine
**Version:** 1.0
