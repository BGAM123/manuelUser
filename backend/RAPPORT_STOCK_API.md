# Rapport Final - API de Gestion du Stock Patrimonial

## Objectif de la Mission

Développer une API complète en lecture seule (read-only) pour la gestion et l'analyse du stock patrimonial, sans modifier aucune entité, table ou logique métier existante. L'API doit calculer dynamiquement la situation des biens et fournir des endpoints pour le listing, la synthèse, les statistiques, l'historique et les mouvements globaux.

---

## Analyse du Projet Existant

### Entités Principales Analysées

1. **Asset** : Entité centrale représentant un bien patrimonial
   - Relations : categories, assetTypes, etatBiens, services, projects, assignments, maintenances, sortie
   - Champs clés : reference, nom, numeroSerie, dateAcquisition, valeur, statut, quantiteStock

2. **AssetAssignment** : Affectation d'un bien à un utilisateur ou un service
   - Relations : asset, user, service
   - Champs clés : dateDebut, dateFin, typeAffectation
   - Une affectation active a dateFin = NULL

3. **AssetMaintenance** : Maintenance d'un bien
   - Relations : assets (ManyToMany), etatBien
   - Champs clés : dateIntervention, dateRecuperation, statut (EN COURS, TERMINEE)
   - Une maintenance en cours a dateRecuperation = NULL

4. **AssetExit** : Sortie définitive d'un bien
   - Relations : asset (OneToOne), service, user, exitType
   - Champs clés : dateSortie, motifSortie, protocoleReference

5. **Bsp** : Bon de Sortie Provisoire (sortie temporaire)
   - Relations : assetExit, service, beneficiaire (user)
   - Champs clés : numero, dateEtablissement, dateRetourEffective, retour (booléen)
   - retour = false : bien sorti temporairement
   - retour = true : bien retourné

6. **User** : Utilisateur du système
   - Relations : service (ManyToOne)
   - Méthodes : getFirstName(), getLastName() (pas getNom()/getPrenom())

7. **Service** : Service organisationnel
   - Relations : users, assets

### Services Existant Analysés

1. **AssetAssignmentService** : Gestion des affectations
   - Méthodes : create(), update(), delete(), createAutomaticAssignmentOnAssetCreate()
   - Note : service_id est commenté dans applyPayload, seul user_id est utilisé

2. **AssetMaintenanceService** : Gestion des maintenances
   - Méthodes : create(), update(), delete(), terminate()
   - Important : syncAssetStatuts() met à jour Asset::$statut automatiquement

3. **BspService** : Gestion des BSP
   - Méthodes : create(), update(), delete()
   - Gère automatiquement le stock pour les biens consommables (quantiteStock)

4. **StatisticsService** : Statistiques existantes
   - Fournit déjà des KPIs détaillés sur les biens, véhicules, terrains, bâtiments

### Repositories Existant Analysés

1. **AssetRepository** : Requêtes sur les biens
   - Méthodes clés : findPaginated(), countAll(), findActiveInMaintenance()
   - Gère déjà la pagination et les filtres

2. **AssetAssignmentRepository** : Requêtes sur les affectations
   - Méthodes clés : findByAsset(), findActiveByAsset(), findActiveByUser()

3. **AssetMaintenanceRepository** : Requêtes sur les maintenances
   - Méthodes clés : findOpenForAsset() (maintenance en cours)

4. **AssetExitRepository** : Requêtes sur les sorties
   - Méthodes clés : findByAssetId()

5. **BspRepository** : Requêtes sur les BSP
   - Méthodes clés : findActiveByAssetExitId()

---

## Cycle de Vie d'un Bien

1. **Création** : Le bien est créé avec un statut initial
2. **Affectation** : Le bien peut être affecté à un utilisateur ou un service
3. **Maintenance** : Le bien peut être envoyé en maintenance
4. **BSP (Sortie Temporaire)** : Le bien peut sortir temporairement via un BSP
5. **Retour** : Le bien peut être retourné après un BSP
6. **Sortie Définitive** : Le bien peut être sorti définitivement

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

## Fichiers Créés

### Repository

**`src/Repository/StockRepository.php`**
- `calculateAssetSituation(int $assetId)` : Calcule la situation d'un bien
- `findAssetsWithSituation()` : Récupère les biens avec leur situation calculée
- `countAssetsWithSituation()` : Compte les biens avec filtres
- `countBySituation()` : Compte les biens par situation
- `findAssetHistory()` : Récupère l'historique des mouvements d'un bien
- `findGlobalMovements()` : Récupère les mouvements globaux
- `countGlobalMovements()` : Compte les mouvements globaux

### Service

**`src/Service/StockService.php`**
- Encapsule la logique métier de calcul de la situation
- Méthodes :
  - `calculateAssetSituation()`
  - `getAssetsWithSituation()`
  - `countAssetsWithSituation()`
  - `getStockSummary()`
  - `getAssetHistory()`
  - `getGlobalMovements()`
  - `getActiveAssignment()`
  - `getOpenMaintenance()`
  - `getAssetExit()`
  - `getAssetBsp()`
  - `normalizeFilters()`

### Response Builder

**`src/Service/StockResponseBuilder.php`**
- Formate les réponses de l'API
- Méthodes :
  - `buildAssetListItem()`
  - `buildAssetDetail()`
  - `buildStockSummary()`
  - `buildHistoryItem()`
  - `buildGlobalMovement()`
  - `buildPaginatedResponse()`

### Contrôleurs

**`src/Controller/Stock/GetStockAssetsController.php`**
- Endpoint : `GET /api/v1/stock/assets`
- Liste les biens avec leur situation calculée
- Pagination et filtres (category_id, asset_type_id, etat_bien_id, service_id, search, date_debut, date_fin)
- Documentation OpenAPI complète

**`src/Controller/Stock/GetStockSummaryController.php`**
- Endpoint : `GET /api/v1/stock/summary`
- Résumé du stock avec nombre de biens par situation
- Taux de disponibilité
- Documentation OpenAPI complète

**`src/Controller/Stock/GetStockStatisticsController.php`**
- Endpoint : `GET /api/v1/stock/statistics`
- Réutilise le StatisticsService existant
- Statistiques détaillées du patrimoine
- Documentation OpenAPI complète

**`src/Controller/Stock/GetAssetHistoryController.php`**
- Endpoint : `GET /api/v1/stock/assets/{id}/history`
- Historique complet des mouvements d'un bien
- Types : ASSIGNMENT, MAINTENANCE, EXIT, BSP
- Documentation OpenAPI complète

**`src/Controller/Stock/GetStockMovementsController.php`**
- Endpoint : `GET /api/v1/stock/movements`
- Mouvements globaux du patrimoine
- Pagination et filtres (type, date_debut, date_fin)
- Documentation OpenAPI complète

### Tests

**`tests/Controller/Stock/StockApiTest.php`**
- Tests pour tous les endpoints
- Tests de pagination
- Tests de validation des paramètres
- Tests des filtres

---

## Endpoints de l'API

| Endpoint | Méthode | Description |
|----------|---------|-------------|
| `/api/v1/stock/assets` | GET | Liste des biens avec situation calculée |
| `/api/v1/stock/summary` | GET | Résumé du stock par situation |
| `/api/v1/stock/statistics` | GET | Statistiques détaillées du patrimoine |
| `/api/v1/stock/assets/{id}/history` | GET | Historique d'un bien |
| `/api/v1/stock/movements` | GET | Mouvements globaux du patrimoine |

---

## Contraintes Respectées

✅ **Aucune modification des entités existantes**
- Aucune nouvelle table créée
- Aucune migration de base de données
- Aucune modification des entités Asset, AssetAssignment, AssetMaintenance, AssetExit, Bsp, User, Service

✅ **Aucune modification de la logique métier existante**
- Les services existants (AssetAssignmentService, AssetMaintenanceService, BspService) n'ont pas été modifiés
- La logique de calcul de la situation est encapsulée dans le nouveau StockRepository et StockService

✅ **Calcul dynamique de la situation**
- La situation des biens est calculée à la volée à partir des données existantes
- Aucun champ "situation" n'est stocké dans la base de données

✅ **Documentation OpenAPI complète**
- Tous les endpoints sont documentés avec des annotations OpenAPI
- Exemples de requêtes et réponses inclus

✅ **Tests**
- Tests unitaires créés pour tous les endpoints
- Tests de pagination, filtres et validation

---

## Architecture de la Solution

```
Controller (GetStockAssetsController)
    ↓
Service (StockService)
    ↓
Repository (StockRepository)
    ↓
Base de données (tables existantes)
```

La séparation des responsabilités est claire :
- **Controller** : Gère les requêtes HTTP et la validation
- **Service** : Encapsule la logique métier
- **Repository** : Effectue les requêtes SQL
- **ResponseBuilder** : Formate les réponses JSON

---

## Performance

- Utilisation de requêtes SQL optimisées avec des jointures
- Pagination pour éviter de charger trop de données
- Calcul de la situation par bien (peut être optimisé avec du batch processing si nécessaire)
- Utilisation des indexes existants sur les clés étrangères

---

## Prochaines Étapes Possibles

1. **Optimisation des performances** : Implémenter un cache pour les situations calculées
2. **Filtres supplémentaires** : Ajouter des filtres par région, département, arrondissement
3. **Export** : Ajouter des endpoints pour exporter les données en CSV/Excel
4. **Graphiques** : Ajouter des endpoints pour générer des graphiques de visualisation
5. **Alertes** : Ajouter des alertes pour les biens en maintenance depuis trop longtemps

---

## Conclusion

L'API de gestion du stock patrimonial a été développée avec succès en respectant strictement les contraintes imposées. Aucune modification des entités, tables ou logique métier existante n'a été effectuée. L'API fournit une vue complète et dynamique du patrimoine avec des endpoints bien documentés et testés.
