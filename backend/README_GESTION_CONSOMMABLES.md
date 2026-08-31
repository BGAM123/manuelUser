# Gestion des Consommables - Documentation Technique

## Table des matières

1. [Vue d'ensemble](#vue-densemble)
2. [Entités principales](#entités-principales)
3. [Flux de création de consommables](#flux-de-création-de-consommables)
4. [Flux de transfert de consommables](#flux-de-transfert-de-consommables)
5. [Système BSP (Bon de Sortie Provisionnel)](#système-bsp-bon-de-sortie-provisionnel)
6. [Relations entre entités](#relations-entre-entités)
7. [Règles métier](#règles-métier)
8. [API Endpoints](#api-endpoints)
9. [Recommandations pour la gestion](#recommandations-pour-la-gestion)

---

## Vue d'ensemble

Le système de gestion des consommables permet de gérer le stock, les entrées et les sorties de consommables (matériel consommable, fournitures, etc.). Le système supporte deux types de transfert :

- **TRANSFERT_DIRECT** : Transfert direct d'un service à un autre
- **BSP** : Bon de Sortie Provisionnel avec suivi de bénéficiaire et de quantités

### Objectifs du système

- Gérer le stock de consommables par service
- Tracer les mouvements de stock (entrées/sorties)
- Suivre les transferts inter-services
- Gérer les BSP avec validation de quantités
- Historiser les opérations avec pièces jointes

---

## Entités principales

### 1. Consumable

Représente un type de consommable dans le stock.

**Champs principaux :**

| Champ | Type | Description |
|-------|------|-------------|
| `id` | int | Identifiant unique |
| `nom` | string (255) | Nom du consommable (obligatoire) |
| `description` | text | Description détaillée |
| `category` | Category (ManyToOne) | Catégorie du consommable |
| `assetType` | AssetType (ManyToOne) | Type d'actif |
| `assetSubType` | AssetSubType (ManyToOne) | Sous-type d'actif |
| `quantite` | decimal (18,2) | Quantité en stock (défaut: 0) |
| `prixInitial` | decimal (18,2) | Prix unitaire initial |
| `prixTotal` | decimal (18,2) | Prix total du stock |
| `pieceJointes` | Collection | Documents attachés |
| `createdAt` | datetime | Date de création |
| `updatedAt` | datetime | Date de dernière modification |
| `isDelete` | boolean | Marque de suppression logique |

**Cycle de vie :**
- `createdAt` et `updatedAt` sont automatiquement gérés par les lifecycle callbacks
- La suppression est logique (`isDelete = true`)

---

### 2. ConsumableTransfer

Représente un transfert de consommables entre services.

**Champs principaux :**

| Champ | Type | Description |
|-------|------|-------------|
| `id` | int | Identifiant unique |
| `consumable` | Consumable (ManyToOne) | Consommable transféré (obligatoire) |
| `serviceDestination` | Service (ManyToOne) | Service destinataire (obligatoire) |
| `serviceSource` | Service (ManyToOne) | Service émetteur (auto-rempli à la création) |
| `type` | string | Type : `TRANSFERT_DIRECT` ou `BSP` |
| `statut` | string | Statut : `SORTI` (BSP) ou `TRANSFERE` (direct) |
| `quantite` | decimal (18,2) | Quantité transférée (obligatoire) |
| `dateTransfert` | date | Date du transfert (défaut: aujourd'hui) |
| `observations` | text | Observations |
| `pieceJointes` | Collection | Documents attachés |
| `consumableBsp` | ConsumableBsp (OneToOne) | Lien BSP (si type = BSP) |
| `createdAt` | datetime | Date de création |
| `updatedAt` | datetime | Date de dernière modification |
| `isDelete` | boolean | Marque de suppression logique |

**Comportements automatiques :**
- `serviceSource` est automatiquement renseigné avec le service de l'utilisateur créateur
- `statut` est fixé automatiquement selon le type :
  - `BSP` → `SORTI`
  - `TRANSFERT_DIRECT` → `TRANSFERE`
- `dateTransfert` prend la date du jour si non fourni

---

### 3. ConsumableBsp

Table de liaison entre un transfert et un BSP.

**Champs principaux :**

| Champ | Type | Description |
|-------|------|-------------|
| `id` | int | Identifiant unique |
| `consumableTransfer` | ConsumableTransfer (OneToOne) | Transfert associé |
| `bsp` | Bsp (OneToOne) | BSP associé |
| `createdAt` | datetime | Date de création |
| `isDelete` | boolean | Marque de suppression logique |

**Rôle :** Permet de lier un transfert de consommable à un BSP existant, créant une relation bidirectionnelle.

---

### 4. Bsp (Bon de Sortie Provisionnel)

Représente un BSP pour la sortie de consommables.

**Champs principaux :**

| Champ | Type | Description |
|-------|------|-------------|
| `numero` | string | Numéro unique du BSP (auto-généré) |
| `service` | Service (ManyToOne) | Service BSP |
| `beneficiaire` | User (ManyToOne) | Bénéficiaire du BSP |
| `quantiteDemandee` | int | Quantité demandée |
| `quantiteAccordee` | int | Quantité accordée |
| `quantiteServie` | int | Quantité servie (obligatoire) |
| `dateEtablissement` | date | Date d'établissement |
| `observations` | text | Observations |
| `pieceJointes` | Collection | Documents BSP |
| `assetExit` | AssetExit (ManyToOne) | Lien optionnel avec sortie d'actif |
| `createdBy` | User (ManyToOne) | Créateur du BSP |
| `createdAt` | datetime | Date de création |
| `updatedAt` | datetime | Date de dernière modification |
| `isDelete` | boolean | Marque de suppression logique |

**Comportements :**
- Le numéro est généré automatiquement
- `quantiteServie` est obligatoire avec valeur par défaut = quantité du transfert

---

## Flux de création de consommables

### 1. Création d'un consommable

**Endpoint :** `POST /consumables`

**Données requises :**
- `nom` (string) : Nom du consommable
- `quantite` (decimal) : Quantité initiale en stock

**Données optionnelles :**
- `description` (text)
- `category_id` (int)
- `asset_type_id` (int)
- `asset_sub_type_id` (int)
- `prixInitial` (decimal)
- `prixTotal` (decimal)
- `piecesJointes[]` (fichiers)

**Processus :**
1. Validation des données
2. Création de l'entité Consumable
3. Upload des pièces jointes
4. Persistance en base de données
5. Retour de l'ID du consommable créé

**Impact sur le stock :**
- Initialise le stock avec la quantité fournie
- Le stock est géré au niveau de l'entité Consommable (pas par service)

---

## Flux de transfert de consommables

### 1. Transfert Direct

**Endpoint :** `POST /consumable-transfers`

**Type :** `TRANSFERT_DIRECT`

**Données requises :**
- `type` = "TRANSFERT_DIRECT"
- `consumable_id` (int) : Consommable à transférer
- `service_destination_id` (int) : Service destinataire
- `quantite` (decimal) : Quantité transférée

**Données optionnelles :**
- `dateTransfert` (date)
- `observations` (text)
- `piecesJointes[]` (fichiers)

**Processus :**
1. Récupération de l'utilisateur connecté
2. Validation du consommable (existe et actif)
3. Validation du service destinataire
4. Création du ConsumableTransfer avec :
   - `type` = "TRANSFERT_DIRECT"
   - `statut` = "TRANSFERE" (auto)
   - `serviceSource` = service de l'utilisateur (auto)
5. Upload des pièces jointes
6. Validation et persistance
7. Mise à jour du stock (à implémenter selon les règles métier)

**Statut final :** `TRANSFERE`

---

### 2. Transfert BSP

**Endpoint :** `POST /consumable-transfers`

**Type :** `BSP`

**Données requises :**
- `type` = "BSP"
- `consumable_id` (int) : Consommable à transférer
- `service_destination_id` (int) : Service destinataire
- `quantite` (decimal) : Quantité transférée

**Données optionnelles (BSP) :**
- `service_id` (int) : Service BSP
- `beneficiaire_id` (int) : Bénéficiaire BSP
- `quantiteDemandee` (int)
- `quantiteAccordee` (int)
- `quantiteServie` (int) - défaut = quantité du transfert
- `dateEtablissement` (date)
- `observations` (text)
- `piecesJointes[]` (fichiers) - vont sur le BSP

**Processus :**
1. Récupération de l'utilisateur connecté
2. Création du BSP avec les champs spécifiques
3. Validation du BSP
4. Création du ConsumableTransfer avec :
   - `type` = "BSP"
   - `statut` = "SORTI" (auto)
   - `serviceSource` = service de l'utilisateur (auto)
5. Création du ConsumableBsp liant les deux
6. Upload des pièces jointes sur le BSP
7. Transaction atomique (tout ou rien)
8. Mise à jour du stock (à implémenter selon les règles métier)

**Statut final :** `SORTI`

---

## Système BSP (Bon de Sortie Provisionnel)

### Concept

Le BSP est un document formel pour la sortie de consommables avec :
- Numéro unique auto-généré
- Bénéficiaire identifié
- Suivi des quantités (demandée, accordée, servie)
- Pièces jointes spécifiques

### Relation avec Transfert

Un transfert BSP crée simultanément :
1. Un enregistrement `ConsumableTransfer` (type = BSP, statut = SORTI)
2. Un enregistrement `Bsp` avec les détails BSP
3. Un enregistrement `ConsumableBsp` liant les deux

### Différences Transfert Direct vs BSP

| Aspect | Transfert Direct | BSP |
|--------|-----------------|-----|
| Type | TRANSFERT_DIRECT | BSP |
| Statut | TRANSFERE | SORTI |
| Bénéficiaire | Non | Oui |
| Quantités | Simple | Demandée/Accordée/Servie |
| Pièces jointes | Sur transfert | Sur BSP |
| Numéro | Non | Oui (auto) |

---

## Relations entre entités

```
Consumable (1) ----< (N) ConsumableTransfer
                             |
                             | (1,1)
                             |
                        ConsumableBsp
                             |
                             | (1,1)
                             |
                             Bsp

Service (1) ----< (N) ConsumableTransfer (serviceDestination)
Service (1) ----< (N) ConsumableTransfer (serviceSource)
Service (1) ----< (N) Bsp

User (1) ----< (N) Bsp (beneficiaire)
User (1) ----< (N) Bsp (createdBy)
```

**Clés étrangères :**
- `consumable_transfer.consumable_id` → `consumable.id`
- `consumable_transfer.service_destination_id` → `service.id`
- `consumable_transfer.service_source_id` → `service.id`
- `consumable_bsp.consumable_transfer_id` → `consumable_transfer.id`
- `consumable_bsp.bsp_id` → `bsp.id`
- `bsp.service_id` → `service.id`
- `bsp.beneficiaire_id` → `user.id`
- `bsp.created_by` → `user.id`

---

## Règles métier

### Règles de validation

**Consumable :**
- `nom` : obligatoire, max 255 caractères
- `quantite` : obligatoire, positive ou nulle
- `category`, `assetType`, `assetSubType` : optionnels

**ConsumableTransfer :**
- `consumable_id` : obligatoire, consommable doit exister et être actif
- `service_destination_id` : obligatoire, service doit exister
- `quantite` : obligatoire, positive
- `type` : doit être "TRANSFERT_DIRECT" ou "BSP"

**BSP :**
- `quantiteServie` : obligatoire (si non fourni, = quantité du transfert)
- `numero` : auto-généré, unique

### Règles de stock

**À implémenter :**
- Déduction de la quantité transférée du stock du consommable
- Vérification de disponibilité du stock avant transfert
- Gestion du stock par service (si nécessaire)

### Règles de suppression

- Suppression logique (`isDelete = true`)
- Cascade sur les relations (CASCADE en base)
- Possibilité de restauration

---

## API Endpoints

### Consommables

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/consumables` | Liste des consommables |
| POST | `/consumables` | Créer un consommable |
| GET | `/consumables/{id}` | Détail d'un consommable |
| PUT | `/consumables/{id}` | Modifier un consommable |
| DELETE | `/consumables/{id}` | Supprimer un consommable |

### Transferts de consommables

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/consumable-transfers` | Liste des transferts |
| POST | `/consumable-transfers` | Créer un transfert (direct ou BSP) |
| GET | `/consumable-transfers/{id}` | Détail d'un transfert |
| PUT | `/consumable-transfers/{id}` | Modifier un transfert |
| DELETE | `/consumable-transfers/{id}` | Supprimer un transfert |
| GET | `/consumable-transfers/by-consumable/{id}` | Transferts par consommable |
| GET | `/consumable-transfers/by-service/{id}` | Transferts par service |

### BSP

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/bsps` | Liste des BSP |
| POST | `/bsps` | Créer un BSP |
| GET | `/bsps/{id}` | Détail d'un BSP |
| PUT | `/bsps/{id}` | Modifier un BSP |
| DELETE | `/bsps/{id}` | Supprimer un BSP |

---

## Recommandations pour la gestion

### 1. Gestion du stock

**Problème actuel :** Le stock est géré au niveau de l'entité `Consumable` sans distinction par service.

**Recommandations :**

**Option A - Stock global par consommable :**
- Maintenir la quantité dans `Consumable.quantite`
- Créer une vue ou une table de stock par service calculée à partir des transferts
- Utiliser des requêtes d'agrégation pour connaître le stock par service

**Option B - Stock par service (recommandée) :**
- Créer une entité `ConsumableStock` avec :
  - `consumable_id`
  - `service_id`
  - `quantite`
- Mettre à jour le stock lors de chaque transfert :
  - Déduction du `serviceSource`
  - Ajout au `serviceDestination`
- Implémenter des triggers ou événements Doctrine pour automatiser

### 2. Validation du stock

**Avant transfert :**
- Vérifier que le stock disponible dans `serviceSource` est suffisant
- Retourner une erreur si stock insuffisant

**Implémentation :**
```php
// Dans ConsumableTransferService
$stockSource = $this->consumableStockRepository->findByConsumableAndService(
    $consumable, 
    $currentUser->getService()
);
if ($stockSource->getQuantite() < $quantite) {
    throw new ValidationFailedException(['quantite' => 'Stock insuffisant']);
}
```

### 3. Historique des mouvements

**Recommandation :**
- Créer une entité `ConsumableMovement` pour tracer chaque mouvement :
  - `consumable_id`
  - `service_id`
  - `type` (ENTREE, SORTIE, TRANSFERT)
  - `quantite`
  - `date`
  - `reference_id` (vers Transfer ou Entry)
  - `created_by`

### 4. Rapports et statistiques

**Endpoints à créer :**
- `GET /consumables/stock-by-service` : Stock par service
- `GET /consumables/movements` : Historique des mouvements
- `GET /consumables/low-stock` : Consommables en rupture de stock
- `GET /consumables/transfers-summary` : Résumé des transferts par période

### 5. Alertes et notifications

**Recommandations :**
- Alerte lorsque stock < seuil minimum
- Notification lors de création de BSP
- Rapport quotidien des sorties

### 6. Workflow de validation

**Pour les BSP :**
- Ajouter un champ `statut` (BROUILLON, EN_ATTENTE, VALIDE, REJETE)
- Créer un workflow de validation par responsable
- Permettre la modification avant validation

### 7. Gestion des retours

**Recommandation :**
- Créer un endpoint pour les retours de consommables
- Inverser la logique de transfert :
  - Ajout au `serviceSource`
  - Déduction du `serviceDestination`
- Créer un `ConsumableReturn` entity

### 8. Audit trail

**Recommandation :**
- Logger toutes les modifications de stock
- Enregistrer qui a fait quoi et quand
- Utiliser un bundle d'audit ou créer une table `audit_log`

---

## Schéma de base de données proposé

### Tables existantes

```sql
-- Consommables
consumable (id, nom, description, category_id, asset_type_id, 
           asset_sub_type_id, quantite, prix_initial, prix_total, 
           created_at, updated_at, is_delete)

-- Transferts
consumable_transfer (id, consumable_id, service_destination_id, 
                     service_source_id, type, statut, quantite, 
                     date_transfert, observations, created_at, 
                     updated_at, is_delete)

-- Lien BSP
consumable_bsp (id, consumable_transfer_id, bsp_id, created_at, is_delete)

-- BSP
bsp (id, numero, service_id, beneficiaire_id, quantite_demandee, 
     quantite_accordee, quantite_servie, date_etablissement, 
     observations, asset_exit_id, created_by, created_at, 
     updated_at, is_delete)
```

### Tables recommandées

```sql
-- Stock par service
consumable_stock (id, consumable_id, service_id, quantite, 
                  seuil_min, created_at, updated_at)

-- Mouvements
consumable_movement (id, consumable_id, service_id, type, 
                     quantite, date, reference_type, reference_id, 
                     created_by, created_at)

-- Retours
consumable_return (id, consumable_transfer_id, quantite, 
                   date_retour, observations, created_by, 
                   created_at, is_delete)
```

---

## Conclusion

Le système actuel fournit une base solide pour la gestion des consommables avec :
- Création de consommables
- Transferts directs et BSP
- Traçabilité des opérations
- Gestion des pièces jointes

**Améliorations prioritaires :**
1. Implémentation de la gestion du stock par service
2. Validation de disponibilité avant transfert
3. Création de rapports et statistiques
4. Workflow de validation pour les BSP
5. Gestion des retours

Ces améliorations permettront d'avoir un système complet et robuste pour la gestion des consommables.
