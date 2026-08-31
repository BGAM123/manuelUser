# Rapport Final - API Unique de Gestion du Stock des Biens

---

## Routes Finales

### ✅ UNE SEULE ROUTE

**GET /gestion_stock_bien**

C'est la seule API publique pour la gestion du stock des biens. Toutes les fonctionnalités sont accessibles via cette route unique avec des paramètres de configuration.

---

## Paramètres

### Paramètres de Pagination

| Paramètre | Type | Défaut | Description |
|-----------|------|--------|-------------|
| `page` | integer | 1 | Numéro de page |
| `limit` | integer | 20 | Nombre d'éléments par page (max 100) |

### Paramètres de Configuration du Contenu

| Paramètre | Type | Défaut | Description |
|-----------|------|--------|-------------|
| `include_summary` | boolean | true | Inclure le résumé du stock |
| `include_statistics` | boolean | true | Inclure les statistiques |
| `include_assets` | boolean | true | Inclure les biens paginés |
| `include_movements` | boolean | false | Inclure les mouvements globaux |
| `include_history` | boolean | false | Inclure l'historique (nécessite asset_id) |

### Paramètres de Filtrage

| Paramètre | Type | Description |
|-----------|------|-------------|
| `asset_id` | integer | ID d'un bien spécifique pour obtenir son historique |
| `category_id` | integer | Filtrer par catégorie |
| `asset_type_id` | integer | Filtrer par type de bien |
| `etat_bien_id` | integer | Filtrer par état de bien |
| `service_id` | integer | Filtrer par service |
| `search` | string | Rechercher par nom, référence ou numéro de série |
| `situation` | string | Filtrer par situation (DISPONIBLE, AFFECTE, MAINTENANCE, SORTIE_TEMPORAIRE, RETOURNE, SORTI_DEFINITIVEMENT) |
| `date_debut` | string (YYYY-MM-DD) | Date de début |
| `date_fin` | string (YYYY-MM-DD) | Date de fin |

---

## Exemple de Requête

### Requête par défaut (dashboard)
```
GET /gestion_stock_bien
```

### Requête avec pagination
```
GET /gestion_stock_bien?page=1&limit=20
```

### Requête avec filtres
```
GET /gestion_stock_bien?situation=AFFECTE&service_id=10
```

### Requête avec filtre de catégorie
```
GET /gestion_stock_bien?category_id=5
```

### Requête avec recherche
```
GET /gestion_stock_bien?search=ordinateur
```

### Requête avec identifiant de bien
```
GET /gestion_stock_bien?asset_id=123
```

### Requête d'historique d'un bien
```
GET /gestion_stock_bien?asset_id=123&include_history=true
```

### Requête optimisée (sans statistiques ni mouvements)
```
GET /gestion_stock_bien?include_statistics=false&include_movements=false
```

---

## Exemple de Réponse Complète

```json
{
    "success": true,
    "message": "Gestion du stock des biens récupérée avec succès",
    "data": {
        "summary": {
            "total": 150,
            "disponibles": 45,
            "affectes": 60,
            "maintenance": 15,
            "sorties_temporaires": 10,
            "retournes": 5,
            "sorties_definitives": 15
        },
        "statistics": {
            "by_category": [],
            "by_type": [],
            "by_etat": [],
            "by_service": []
        },
        "assets": {
            "data": [
                {
                    "id": 1,
                    "reference": "PAT-2026-00001",
                    "code": "ORD-001",
                    "nom": "Ordinateur Portable Dell",
                    "numero_serie": "SN123456",
                    "date_acquisition": "2024-01-15",
                    "valeur": "1200.00",
                    "statut": "ACTIF",
                    "situation": "AFFECTE",
                    "situation_details": {
                        "assignment": {
                            "id": 10,
                            "type_affectation": "AFFECTATION",
                            "date_debut": "2024-01-20",
                            "date_fin": null,
                            "commentaire": "Affectation au service RH",
                            "user": {
                                "id": 5,
                                "nom": "Dupont",
                                "prenom": "Jean",
                                "matricule": "MAT001"
                            },
                            "service": null
                        }
                    },
                    "categories": [
                        {
                            "id": 1,
                            "nom": "Informatique"
                        }
                    ],
                    "asset_types": [
                        {
                            "id": 2,
                            "nom": "Ordinateur Portable"
                        }
                    ],
                    "asset_sub_types": [
                        {
                            "id": 3,
                            "nom": "PC Portable"
                        }
                    ],
                    "etat_biens": [
                        {
                            "id": 1,
                            "nom": "Bon état"
                        }
                    ],
                    "services": [
                        {
                            "id": 10,
                            "nom": "Direction des Ressources Humaines",
                            "sigle": "DRH"
                        }
                    ],
                    "quantite_stock": null,
                    "created_at": "2024-01-15 10:30:00",
                    "updated_at": "2024-01-20 14:15:00"
                }
            ],
            "pagination": {
                "page": 1,
                "limit": 20,
                "total": 150,
                "pages": 8
            }
        },
        "movements": null,
        "history": null
    }
}
```

---

## Structure JSON

### Racine
```json
{
    "success": boolean,
    "message": "string",
    "data": {
        // Sections conditionnelles
    }
}
```

### Section `summary` (résumé du stock)
```json
{
    "total": integer,
    "disponibles": integer,
    "affectes": integer,
    "maintenance": integer,
    "sorties_temporaires": integer,
    "retournes": integer,
    "sorties_definitives": integer
}
```

### Section `statistics` (statistiques détaillées)
```json
{
    "by_category": array,
    "by_type": array,
    "by_etat": array,
    "by_service": array
}
```

### Section `assets` (biens paginés)
```json
{
    "data": [
        {
            "id": integer,
            "reference": string|null,
            "code": string|null,
            "nom": string|null,
            "numero_serie": string|null,
            "date_acquisition": string|null,
            "valeur": string|null,
            "statut": string|null,
            "situation": "DISPONIBLE|AFFECTE|MAINTENANCE|SORTIE_TEMPORAIRE|RETOURNE|SORTI_DEFINITIVEMENT",
            "situation_details": object|null,
            "categories": array,
            "asset_types": array,
            "asset_sub_types": array,
            "etat_biens": array,
            "services": array,
            "quantite_stock": integer|null,
            "created_at": string,
            "updated_at": string
        }
    ],
    "pagination": {
        "page": integer,
        "limit": integer,
        "total": integer,
        "pages": integer
    }
}
```

### Section `movements` (mouvements globaux)
```json
{
    "data": [
        {
            "type": "ASSIGNMENT|MAINTENANCE|EXIT|BSP",
            "id": integer,
            "asset": {
                "id": integer,
                "nom": string
            },
            "date_debut": string|null,
            "date_fin": string|null,
            "created_at": string
        }
    ],
    "pagination": {
        "page": integer,
        "limit": integer,
        "total": integer,
        "pages": integer
    }
}
```

### Section `history` (historique d'un bien)
```json
[
    {
        "type": "ASSIGNMENT|MAINTENANCE|EXIT|BSP",
        "id": integer,
        "date_debut": string|null,
        "date_fin": string|null,
        "created_at": string,
        // Détails spécifiques selon le type
    }
]
```

### Section `asset` (détail d'un bien spécifique)
```json
{
    // Tous les champs de la liste + détails supplémentaires
    "description": string|null,
    "valeur_initiale": string|null,
    "prix_mercurial": string|null,
    "mode_acquisition": string|null,
    "active_amortissement": boolean,
    "active_reevaluation": boolean,
    "fournisseur": object|null,
    "projects": array
}
```

---

## Règles de Calcul du Stock

La situation d'un bien est calculée dynamiquement selon l'ordre de priorité suivant :

### 1. SORTI_DEFINITIVEMENT
- Le bien a une `AssetExit` associée avec `isDelete = false`

### 2. MAINTENANCE
- Le bien a au moins une `AssetMaintenance` avec `dateRecuperation IS NULL`

### 3. AFFECTE
- Le bien a au moins une `AssetAssignment` avec `isDelete = false` ET `dateFin IS NULL`

### 4. SORTIE_TEMPORAIRE
- Le bien a une `AssetExit` avec des `Bsp` associés
- Au moins un BSP a `retour = false`

### 5. RETOURNE
- Le bien a une `AssetExit` avec des `Bsp` associés
- Tous les BSP ont `retour = true`

### 6. DISPONIBLE
- Aucune des conditions ci-dessus n'est remplie

---

## Règles de Situation

| Situation | Condition | Détails Inclus |
|-----------|-----------|----------------|
| DISPONIBLE | Aucune condition remplie | Aucun |
| AFFECTE | AssetAssignment active | assignment (user, service, dates) |
| MAINTENANCE | AssetMaintenance ouverte | maintenance (motif, coût, dates) |
| SORTIE_TEMPORAIRE | BSP avec retour=false | bsp (numéro, quantités, bénéficiaire) |
| RETOURNE | BSP avec retour=true | bsp (numéro, quantités, bénéficiaire) |
| SORTI_DEFINITIVEMENT | AssetExit existante | exit (motif, date, détenteur) |

---

## Pagination

- **Biens** : Paginés par défaut (page=1, limit=20)
- **Mouvements** : Paginés si inclus (page=1, limit=20)
- **Maximum** : 100 éléments par page
- **Structure** : `{ data: [], pagination: { page, limit, total, pages } }`

---

## Filtres

### Filtres Disponibles
- `category_id` : Filtrer par catégorie
- `asset_type_id` : Filtrer par type de bien
- `etat_bien_id` : Filtrer par état de bien
- `service_id` : Filtrer par service
- `search` : Recherche textuelle (nom, référence, numéro de série)
- `situation` : Filtrer par situation calculée
- `date_debut` / `date_fin` : Plage de dates d'acquisition

### Comportement
- Les filtres s'appliquent uniquement aux biens
- Les filtres sont cumulatifs (AND logique)
- Le filtre `situation` est calculé dynamiquement

---

## Statistiques

Les statistiques sont fournies par le `StatisticsService` existant du projet.

### Contenu
- Répartition par catégorie
- Répartition par type de bien
- Répartition par état
- Répartition par service

### Activation
- Inclu par défaut (`include_statistics=true`)
- Peut être désactivé pour optimiser les performances

---

## Historique

### Activation
- Nécessite le paramètre `asset_id`
- Nécessite le paramètre `include_history=true`

### Contenu
- Affectations (ASSIGNMENT)
- Maintenances (MAINTENANCE)
- Sorties définitives (EXIT)
- BSP (BSP)

### Structure par Type
- **ASSIGNMENT** : dates, détenteur, type d'affectation
- **MAINTENANCE** : dates, motif, coût, statut
- **EXIT** : date de sortie, motif, détenteur
- **BSP** : numéro, quantités, bénéficiaire, retour

---

## Mouvements

### Activation
- Nécessite le paramètre `include_movements=true`
- Désactivé par défaut pour optimiser les performances

### Contenu
- Tous les mouvements du patrimoine
- Types : ASSIGNMENT, MAINTENANCE, EXIT, BSP
- Paginés (page, limit)

### Filtres Possibles
- `type` : Filtrer par type de mouvement
- `date_debut` / `date_fin` : Plage de dates

---

## Fichiers Créés

### Repository
- `src/Repository/StockRepository.php` - Requêtes SQL pour le calcul de situation

### Service
- `src/Service/StockService.php` - Logique métier unifiée avec méthode `getStockData()`
- `src/Service/StockResponseBuilder.php` - Formatage des réponses avec méthode `buildUnifiedResponse()`

### Contrôleur
- `src/Controller/Stock/GetStockBienController.php` - Contrôleur unique pour l'API `/gestion_stock_bien`

### Tests
- `tests/Controller/Stock/StockApiTest.php` - Tests adaptés pour l'API unique

### Documentation
- `RAPPORT_FINAL_API_STOCK.md` - Ce rapport

---

## Fichiers Modifiés

### Aucun fichier existant modifié

Les fichiers créés sont tous nouveaux. Aucune modification n'a été apportée aux fichiers existants du projet.

---

## Confirmation

### ✅ Aucune Entity modifiée
- Asset.php : Non modifié (date : 21/08/2026 07:43:48 - date antérieure à la mission)
- AssetAssignment.php : Non modifié (date : 14/08/2026 11:00:00 - date antérieure à la mission)
- AssetMaintenance.php : Non modifié (date : 21/08/2026 07:43:48 - date antérieure à la mission)
- AssetExit.php : Non modifié (date : 20/08/2026 18:01:16 - date antérieure à la mission)
- Bsp.php : Non modifié (date : 16/08/2026 20:51:14 - date antérieure à la mission)
- Toutes les autres entités : Non modifiées

### ✅ Aucune table créée
- Aucune migration de base de données créée
- Aucune nouvelle table dans la base de données

### ✅ Aucune migration créée
- Aucun fichier de migration Doctrine créé

### ✅ Aucune logique métier existante modifiée
- AssetAssignmentService : Non modifié
- AssetMaintenanceService : Non modifié
- BspService : Non modifié
- AssetExitService : Non modifié
- StatisticsService : Non modifié (utilisé tel quel)

### ✅ Une seule API publique de stock créée
- **GET /gestion_stock_bien** : Seule route publique pour la gestion du stock des biens
- Les anciennes routes ont été supprimées :
  - ❌ /api/v1/stock/assets (supprimé)
  - ❌ /api/v1/stock/summary (supprimé)
  - ❌ /api/v1/stock/statistics (supprimé)
  - ❌ /api/v1/stock/assets/{id}/history (supprimé)
  - ❌ /api/v1/stock/movements (supprimé)

### ✅ Documentation OpenAPI complète
- L'API unique est documentée avec des annotations OpenAPI
- Tous les paramètres sont documentés
- Exemples de requêtes et réponses inclus

### ✅ Tests créés
- Tests adaptés pour l'API unique
- Tests des paramètres include_*
- Tests de pagination et filtres
- Tests de validation

---

## Architecture Interne

```
GET /gestion_stock_bien
    ↓
GetStockBienController
    ↓
StockService::getStockData()
    ↓
StockRepository (requêtes SQL)
    ↓
Entities / Repositories existants
    ↓
StockResponseBuilder::buildUnifiedResponse()
    ↓
JSON Response
```

L'architecture interne reste propre et séparée, mais seule une route publique est exposée.

---

## Performance

- **Optimisation** : Les paramètres `include_*` permettent de ne charger que les données nécessaires
- **Pagination** : Les biens et mouvements sont paginés pour éviter de charger tout en mémoire
- **Requêtes SQL** : Utilisation de jointures et agrégations optimisées
- **Calcul dynamique** : La situation est calculée à la volée sans stockage

---

## Conclusion

L'API unique de gestion du stock des biens a été développée avec succès en respectant strictement toutes les contraintes imposées :

- ✅ Une seule API publique (`GET /gestion_stock_bien`)
- ✅ Aucune modification des entités existantes
- ✅ Aucune table ou migration créée
- ✅ Aucune modification de la logique métier existante
- ✅ Calcul dynamique de la situation des biens
- ✅ Pagination et filtres complets
- ✅ Paramètres `include_*` pour optimiser les performances
- ✅ Documentation OpenAPI complète
- ✅ Tests unitaires adaptés

L'API fournit une vue complète et unifiée du patrimoine, permettant au frontend de construire directement le dashboard, le tableau du stock, les statistiques, les graphiques, les filtres, l'historique et les mouvements avec une seule requête HTTP.
