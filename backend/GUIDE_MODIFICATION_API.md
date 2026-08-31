# 🛠️ Guide Pratique : Modifier et Créer des API

Ce guide s'adresse à **toute personne**, même sans connaissance préalable du projet. Il explique étape par étape comment :

1. Retirer un champ d'une réponse API
2. Ajouter un champ à une réponse API
3. Créer une toute nouvelle API (endpoint)
4. Mettre à jour la documentation Swagger

---

## 📖 Comprendre le principe de base

Chaque réponse de l'API a toujours cette forme :

```json
{
  "success": true,
  "status": 200,
  "message": "Description de l'action",
  "data": { ... }
}
```

Le contenu de `data` provient d'une **Entité** (ex: `Project`, `User`) qui est transformée en JSON par le **Serializer** de Symfony, en utilisant des **groupes** (`Groups`).

**Chaîne de transformation** :

```
Base de données  →  Entité PHP (src/Entity/)  →  Serializer (avec Groups)  →  JSON renvoyé au client
```

---

## 1️⃣ Retirer un champ d'une réponse API

### Exemple concret : retirer le champ `"users"` de la réponse `GET /projects/{id}`

Actuellement la réponse contient :
```json
"users": [[]],
```

### 📍 Étape 1 : Trouver où le champ est déclaré

Chaque champ visible dans le JSON correspond à une **propriété PHP** dans le fichier de l'entité, située dans `src/Entity/`.

Pour le champ `users` du projet, ouvrez :
📂 `src/Entity/Project.php`

Vous trouverez :

```php
#[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'projects')]
#[ORM\JoinTable(name: 'project_user')]
#[Groups(['project:detail'])]   // <-- C'est cette ligne qui expose le champ en JSON
private Collection $users;
```

### 📍 Étape 2 : Retirer le groupe de sérialisation

Le tag `#[Groups(['project:detail'])]` indique à Symfony : *"quand tu sérialises avec le groupe `project:detail`, inclus ce champ"*.

**Pour retirer le champ de la réponse**, supprimez ou videz la ligne `Groups` :

```php
// Avant (le champ "users" apparaît dans la réponse)
#[Groups(['project:detail'])]
private Collection $users;

// Après (le champ "users" n'apparaît plus)
private Collection $users;
```

> ⚠️ Ne supprimez jamais la propriété elle-même (`private Collection $users;`), seulement l'annotation `#[Groups(...)]`. La propriété reste utile pour la logique métier (ex: `ProjectNormalizer` qui lit `$object->getUsers()` pour construire `"responsables"`).

### 📍 Étape 3 : Vider le cache et tester

```powershell
php bin/console cache:clear
```

Relancez une requête `GET /projects/{id}` : le champ `"users"` ne doit plus apparaître.

---

## 2️⃣ Ajouter un champ à une réponse API

### Cas A — Le champ existe déjà dans l'entité mais n'est pas exposé

📂 Ouvrez le fichier de l'entité concernée dans `src/Entity/` (ex: `Project.php`, `User.php`).

Repérez la propriété et **ajoutez** l'annotation `#[Groups(...)]` avec le bon nom de groupe (le même que celui utilisé dans le contrôleur, ex: `project:detail`, `project:list`, `user:read`) :

```php
#[ORM\Column(length: 255, nullable: true)]
#[Groups(['project:detail'])]   // <-- Ajouté pour exposer le champ
private ?string $codeReference = null;
```

Puis videz le cache :
```powershell
php bin/console cache:clear
```

### Cas B — Le champ n'existe pas du tout (nouvelle colonne en base de données)

1. **Ajouter la propriété dans l'entité** (`src/Entity/Project.php`) avec getter/setter :

```php
#[ORM\Column(length: 255, nullable: true)]
#[Groups(['project:detail'])]
private ?string $codeReference = null;

public function getCodeReference(): ?string
{
    return $this->codeReference;
}

public function setCodeReference(?string $codeReference): static
{
    $this->codeReference = $codeReference;
    return $this;
}
```

2. **Générer et appliquer une migration** pour créer la colonne en base :

```powershell
php bin/console make:migration
php bin/console doctrine:migrations:migrate --no-interaction
```

3. **Vider le cache** :
```powershell
php bin/console cache:clear
```

### Cas C — Le champ est "calculé" (pas une vraie colonne, ex: `responsables`)

Ce type de champ n'est PAS dans l'entité, il est ajouté manuellement dans un **Normalizer**.

📂 Regardez l'exemple existant : `src/Serializer/ProjectNormalizer.php`

```php
$responsables = [];
foreach ($object->getUsers() as $user) {
    $responsables[] = [
        'id' => $user->getId(),
        'firstName' => $user->getFirstName(),
        'lastName' => $user->getLastName(),
        'email' => $user->getEmail(),
    ];
}
$normalizedData['responsables'] = $responsables;  // <-- champ ajouté manuellement
```

Pour ajouter un nouveau champ calculé, ajoutez simplement une ligne similaire dans le Normalizer correspondant à votre entité :
- Projets → `src/Serializer/ProjectNormalizer.php`
- Profil utilisateur → `src/Serializer/ProfileNormalizer.php`

```php
$normalizedData['mon_nouveau_champ'] = 'valeur calculée ici';
```

---

## 3️⃣ Créer une toute nouvelle API (endpoint)

Prenons un exemple concret : créer `GET /projects/{id}/summary`.

### 📍 Étape 1 : Créer le fichier du contrôleur

Chaque route = 1 fichier de contrôleur, rangé dans `src/Controller/<Ressource>/`.

Créez : `src/Controller/Projects/ProjectSummaryController.php`

```php
<?php

namespace App\Controller\Projects;

use App\Repository\ProjectRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projects')]
#[OA\Tag(name: 'Projects')]
final class ProjectSummaryController extends AbstractController
{
    #[Route('/{id}/summary', name: 'app_project_summary', methods: ['GET'])]
    #[OA\Get(
        path: '/projects/{id}/summary',
        summary: 'Résumé rapide d’un projet'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Success - résumé du projet',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Résumé du projet retourné avec succès.',
                'data' => [
                    'id' => 1,
                    'nom' => 'Recensement 2026',
                    'nombre_responsables' => 3,
                ]
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Not Found - Projet non trouvé')]
    public function __invoke(
        int $id,
        ProjectRepository $projectRepository,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $project = $projectRepository->getProjectById($id);

        if (!$project || $project->isDelete()) {
            return $apiResponse->error('Projet non trouvé.', Response::HTTP_NOT_FOUND);
        }

        $data = [
            'id' => $project->getId(),
            'nom' => $project->getNom(),
            'nombre_responsables' => count($project->getUsers()),
        ];

        return $apiResponse->success($data, Response::HTTP_OK, 'Résumé du projet retourné avec succès.');
    }
}
```

### 📍 Étape 2 : Vérifier — aucune configuration manuelle de route n'est nécessaire

Ce projet charge automatiquement toutes les routes déclarées avec `#[Route(...)]` (voir `config/routes.yaml`, section `controllers`). Il **suffit de créer le fichier** ci-dessus, la route est détectée automatiquement.

### 📍 Étape 3 : Vider le cache et vérifier que la route existe

```powershell
php bin/console cache:clear
php bin/console debug:router | Select-String "summary"
```

Vous devez voir apparaître :
```
app_project_summary   GET   /projects/{id}/summary
```

### 📍 Étape 4 : Tester la route

```powershell
symfony serve
```
Puis appelez `GET http://localhost:8000/projects/1/summary` (avec un token JWT valide dans le header `Authorization: Bearer <token>` si la route est protégée).

### 🧩 Checklist générique pour toute nouvelle API

| # | Action | Où |
|---|--------|-----|
| 1 | Créer le contrôleur | `src/Controller/<Ressource>/MonController.php` |
| 2 | Ajouter la logique métier si besoin | `src/Repository/<Ressource>Repository.php` |
| 3 | Utiliser `ApiResponseFactory` pour la réponse | injecté dans le contrôleur |
| 4 | Utiliser `PaginationFactory` si c'est une liste | injecté dans le contrôleur |
| 5 | Documenter avec les attributs `#[OA\...]` | dans le même contrôleur |
| 6 | Vider le cache | `php bin/console cache:clear` |
| 7 | Vérifier la route | `php bin/console debug:router` |

---

## 4️⃣ Mettre à jour la documentation Swagger

La documentation Swagger (`/api/doc` ou `/doc.json`) est générée **automatiquement** à partir des attributs `#[OA\...]` présents dans les contrôleurs. Il n'y a rien à écrire à la main dans un fichier séparé.

### ✅ Commande à exécuter après CHAQUE modification de contrôleur :

```powershell
php bin/console cache:clear
```

C'est la seule commande nécessaire : Nelmio API Doc régénère la documentation à la volée à partir du code à chaque appel de `/doc.json` ou `/api/doc`, mais le cache Symfony doit être vidé pour que les nouvelles annotations soient prises en compte.

### 👀 Visualiser le résultat :

```powershell
symfony serve
```
Puis ouvrez : `http://localhost:8000/api/doc`

### 📤 (Optionnel) Exporter la spec Swagger en fichier :

```powershell
php bin/console api:openapi:export --format=json > swagger_output.json
php bin/console api:openapi:export --format=yaml > swagger_output.yaml
```

---

## ✅ Résumé express

| Je veux... | Je modifie... |
|---|---|
| **Retirer un champ** existant | Supprimer `#[Groups([...])]` sur la propriété dans `src/Entity/*.php` |
| **Ajouter un champ** déjà présent en base | Ajouter `#[Groups([...])]` sur la propriété dans `src/Entity/*.php` |
| **Ajouter un champ** qui n'existe pas en base | Ajouter la colonne dans l'entité + migration (`make:migration` puis `doctrine:migrations:migrate`) |
| **Ajouter un champ calculé** (pas une colonne) | L'ajouter dans le Normalizer correspondant (`src/Serializer/*.php`) |
| **Créer une nouvelle API** | Créer un contrôleur dans `src/Controller/<Ressource>/` avec `#[Route]` + `#[OA\...]` |
| **Mettre à jour Swagger** | `php bin/console cache:clear` puis ouvrir `/api/doc` |

> 💡 Après **toute** modification de code PHP dans ce projet, exécutez systématiquement `php bin/console cache:clear` avant de tester.
