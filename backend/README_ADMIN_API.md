# README_ADMIN_API.md - Guide Technique pour les Administrateurs

## 1. Gestion modification réponses API

### 1.1 Structure générale des réponses

Toutes les réponses de l'API respectent une structure uniforme :

#### Réponse de succès
```json
{
  "success": true,
  "status": 200,
  "message": "Description de l'opération",
  "data": {
    "...": "contenu spécifique à l'endpoint"
  }
}
```

#### Réponse d'erreur
```json
{
  "success": false,
  "status": 400,
  "message": "Description de l'erreur",
  "data": null
}
```

### 1.2 Où modifier les réponses

#### ApiResponseFactory.php
**Localisation** : `src/Service/ApiResponseFactory.php`

**Responsabilités** :
- Crée la structure JSON uniforme avec les champs `success`, `status`, `message`, `data`
- Méthodes principales :
  - `success(mixed $data, int $status, string $message)` : crée une réponse réussie
  - `error(string $message, int $status, mixed $data)` : crée une réponse d'erreur

**Comment modifier** :
```php
// Pour ajouter des champs supplémentaires à toutes les réponses :
private function create(bool $success, int $status, string $message, mixed $data): JsonResponse
{
    $payload = [
        'success' => $success,
        'status' => $status,
        'message' => $message,
        'data' => $data,
        // Ajouter ici des champs supplémentaires si nécessaire
    ];
    
    return new JsonResponse($payload, $status);
}
```

#### PaginationFactory.php
**Localisation** : `src/Service/PaginationFactory.php`

**Responsabilités** :
- Crée une structure paginée uniforme pour tous les endpoints list
- Encapsule les données avec les informations de pagination

**Méthode** :
```php
public function createPaginatedResponse(
    array $items,      // Les éléments à retourner
    int $page,        // Numéro de page actuel
    int $limit,       // Nombre d'éléments par page
    int $total        // Nombre total d'éléments
): array
```

**Format retourné** :
```php
[
    'data' => [...items],
    'pagination' => [
        'page' => 1,
        'limit' => 10,
        'total' => 100,
        'pages' => 10
    ]
]
```

### 1.3 Où modifier les DTOs (Data Transfer Objects)

Il n'existe pas de DTOs explicites. Les données brutes des repositories sont sérialisées via le Serializer Symfony.

**Localisation des entités** : `src/Entity/`

**Entités principales** :
- `User.php` : Représente un utilisateur
- `Role.php` : Représente un rôle métier
- `Permission.php` : Représente une permission
- `Project.php` : Représente un projet
- `Service.php` : Représente un service/département

**Comment modifier les données retournées** :
1. Modifier les propriétés de l'entité si nécessaire
2. Ajouter les getters/setters correspondants
3. Mettre à jour les groupes de sérialisation (voir section Serializers ci-dessous)

### 1.4 Où modifier les Serializers

**Localisation** : `src/Serializer/`

#### Serializers principaux

**PaginatedCollectionNormalizer.php**
- Normalise les collections paginées
- Peut être utilisé pour transformer la structure des données avant pagination
- Méthode : `normalize(mixed $object, string $format, array $context)`

**ProfileNormalizer.php**
- Enrichit les données utilisateur pour l'endpoint `/profile`
- Ajoute les informations du service, rôles et permissions
- S'applique uniquement quand le chemin contient `/profile`

**ProjectNormalizer.php**
- Enrichit les données projet
- Ajoute le tableau `responsables` avec les informations des utilisateurs affectés
- Extrait `{id, firstName, lastName, email}` pour chaque responsable

#### Comment créer un nouveau Normalizer

```php
namespace App\Serializer;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class MonNormalizer implements NormalizerInterface
{
    public function normalize(mixed $object, string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        // Logique de normalisation
        return [
            'id' => $object->getId(),
            'nom' => $object->getNom(),
            // ... autres champs
        ];
    }

    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        // Condition pour appliquer ce normalizer
        return $data instanceof MonEntite;
    }
}
```

#### Groupes de sérialisation

Les groupes contrôlent quels champs sont inclus dans la sérialisation :

```php
// Dans les contrôleurs ou services :
$serializer->serialize($data, 'json', ['groups' => ['user:read']]);
```

**Groupes existants** :
- `user:read` : Pour la lecture des utilisateurs
- `role:list` : Pour la liste des rôles
- `role:read` : Pour la lecture détaillée d'un rôle
- `permission:list` : Pour la liste des permissions
- `project:list` : Pour la liste des projets
- `project:read` : Pour la lecture détaillée d'un projet

**Ajouter un groupe** :
1. Marquer les propriétés de l'entité avec le groupe :
```php
#[Groups(['user:read'])]
private string $email;
```

2. Utiliser le groupe lors de la sérialisation :
```php
$serializer->serialize($user, 'json', ['groups' => ['user:read']]);
```

---

## 2. Gestion Swagger

### 2.1 Documentation Swagger actuelle

L'API utilise **Nelmio API Doc Bundle** pour générer automatiquement la documentation Swagger depuis les annotations OpenAPI.

**Fichier de configuration** : `config/packages/nelmio_api_doc.yaml`

### 2.2 Comment masquer/retirer une API du Swagger

#### Option 1 : Retirer l'annotation OA\Tag

Supprimer la ligne `#[OA\Tag(name: 'TagName')]` du contrôleur masque l'endpoint du Swagger.

#### Option 2 : Retirer l'annotation OA\Get, OA\Post, etc.

Supprimer l'annotation OpenAPI spécifique (ex: `#[OA\Get(...)]`) masque l'endpoint sans retirer la route.

#### Option 3 : Configuration Nelmio API Doc

Dans `config/packages/nelmio_api_doc.yaml`, ajouter des règles d'exclusion :

```yaml
nelmio_api_doc:
  documentation:
    paths:
      /admin/internal:
        exclude: true  # Exclure cette route du Swagger
```

#### Option 4 : Retirer la route complètement

Supprimer ou commenter l'annotation `#[Route(...)]` supprime entièrement la route de l'application.

### 2.3 Configurer Nelmio API Doc

**Localisation du fichier** : `config/packages/nelmio_api_doc.yaml`

**Sections principales** :

```yaml
nelmio_api_doc:
  documentation:
    info:
      title: API Gestion du Patrimoine
      description: "Description de votre API"
      version: "1.0.0"
    servers:
      - url: https://api.example.com
        description: "Production"
      - url: http://localhost:8000
        description: "Development"
    paths: {}
  areas:
    path_patterns:
      - ^/admin(?!/internal)  # Inclure les routes commençant par /admin
      - ^/api
```

### 2.4 Comment modifier l'info API

Éditer le fichier `config/packages/nelmio_api_doc.yaml` :

```yaml
nelmio_api_doc:
  documentation:
    info:
      title: "Nouveau titre"
      description: "Nouvelle description"
      version: "2.0.0"
```

### 2.5 Comment retirer une route du Swagger sans la supprimer

**Meilleure approche** : Ajouter une règle d'exclusion par route dans la config Nelmio :

```yaml
nelmio_api_doc:
  areas:
    path_patterns:
      - ^/(?!admin/internal)  # Exclure les routes /admin/internal
```

Ou directement dans le contrôleur, en supprimant les annotations OpenAPI :

```php
#[Route('/admin/internal')]  // Pas de #[OA\Get(...)]
public function internalEndpoint() { ... }
```

---

## 3. Modifications récentes et structure API

### 3.1 Endpoints de gestion des utilisateurs

- `GET /users` : Liste paginée des utilisateurs
- `POST /users` : Créer un utilisateur avec `role_ids` (max 2)
- `GET /users/{id}` : Détails utilisateur
- `PUT /users/{id}` : Modifier utilisateur
- `PATCH /users/{id}` : Modification partielle utilisateur
- `GET /profile` : Profil complet de l'utilisateur connecté (avec rôles et permissions)

### 3.2 Endpoints de gestion des rôles

- `GET /roles` : Liste paginée des rôles
- `POST /roles` : Créer un rôle
- `GET /roles/{id}` : Détails rôle
- `POST /roles/{roleId}/permissions` : Assigner plusieurs permissions à un rôle (nouveau)
- `DELETE /roles/{id}/permissions/{permissionId}` : Retirer une permission

### 3.3 Endpoints de gestion des permissions

- `GET /permissions` : Liste paginée des permissions
- `POST /permissions` : Créer une permission
- `GET /permissions/{id}` : Détails permission

### 3.4 Endpoints de gestion des projets

- `GET /projects` : Liste paginée des projets
- `POST /projects` : Créer un projet avec `responsable_ids` (array)
- `GET /projects/{id}` : Détails projet
- `PUT /projects/{id}` : Modifier projet (sans statut)
- `PATCH /projects/{id}` : Modification partielle (sans statut)
- `PATCH /projects/{id}/status` : Changer le statut du projet (nouveau)

### 3.5 Authentification

- `POST /login` : Authentification JWT
- Vérification automatique : les utilisateurs inactifs (`is_active=false`) ou supprimés (`is_delete=true`) ne peuvent pas s'authentifier

---

## 4. Commandes de maintenance

### Valider le schéma de base de données
```bash
php bin/console doctrine:schema:validate
```

### Vider le cache
```bash
php bin/console cache:clear
```

### Afficher toutes les routes
```bash
php bin/console debug:router
```

### Créer une migration
```bash
php bin/console make:migration
```

### Exécuter les migrations
```bash
php bin/console doctrine:migrations:migrate
```

### Charger les fixtures
```bash
php bin/console doctrine:fixtures:load
```

---

## 5. Contraintes et limites actuelles

- **Maximum 2 rôles métier** par utilisateur (MAX_ASSIGNED_ROLES=2)
- **Limite de recherche** : Les recherches par défaut retournent max 200 items
- **Authentification** : JWT avec refresh tokens
- **Validations** : Toutes les entités utilisent Symfony Validator

---

## 6. Pour les développeurs

### Structure des répertoires pertinents

```
src/
├── Controller/          # Contrôleurs API (organisés par ressource)
│   ├── Users/
│   ├── Roles/
│   ├── Permissions/
│   └── Projects/
├── Entity/             # Modèles de données
├── Repository/         # Accès aux données
├── Service/           # Services métier
│   ├── ApiResponseFactory.php
│   ├── PaginationFactory.php
│   └── RefreshTokenService.php
├── Serializer/        # Normaliseurs et transformateurs
└── Security/          # Gestion de l'authentification
```

### Ajouter un nouvel endpoint

1. Créer le contrôleur dans `src/Controller/Resource/ActionController.php`
2. Ajouter les annotations OpenAPI pour la documentation Swagger
3. Utiliser `ApiResponseFactory` pour les réponses
4. Utiliser `PaginationFactory` si l'endpoint retourne une liste
5. Ajouter des tests en `tests/Controller/`

---

## 7. Sécurité et authentification

### JWT (JSON Web Tokens)

- **Token expiration** : Configurable dans `config/packages/lexik_jwt_authentication.yaml`
- **Refresh tokens** : Stockés en base de données dans la table `refresh_token`
- **Vérification** : Utilisateurs désactivés ou supprimés sont bloqués à l'authentification

### Contrôle d'accès

Actuellement, les permissions sont attachées aux rôles, mais pas encore appliquées par des middleware de contrôle d'accès. Le système est prêt pour ajouter des vérifications de permissions via des voters Symfony ou des attributes.

---

**Dernière mise à jour** : Décembre 2026
