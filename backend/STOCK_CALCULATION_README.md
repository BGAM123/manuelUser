# Documentation du Calcul de Stock

Ce document décrit toutes les formules de calcul de stock pour les consommables et les biens dans l'application.

---

## Table des matières

1. [Stock des Consommables](#stock-des-consommables)
   - [Stock par Service](#stock-par-service)
   - [Stock Global du Consommable](#stock-global-du-consommable)
   - [Vérification de Transfert](#vérification-de-transfert)
2. [Stock des Biens (Assets)](#stock-des-biens-assets)
3. [Services Impliqués](#services-impliqués)
4. [Repositories Impliqués](#repositories-impliqués)

---

## Stock des Consommables

### Stock par Service

**Méthode :** `GestionStockConsommableService::getStock(int $consumableId, int $serviceId)`

**Fichier :** `src/Service/GestionStockConsommableService.php` (lignes 23-35)

**Formule :**

```
stockActuel = stockInitial + transfertsRecus - transfertsEffectues - sorties
```

**Détail des composants :**

- **stockInitial** : Somme des quantités des entrées de consommables pour ce service
  - Repository : `ConsumableEntryRepository::sumQuantiteByConsumableAndService()`
  - Table : `consumable_entry`
  - Filtres : `consumable_id = ?`, `service_id = ?`, `is_delete = false`

- **transfertsRecus** : Somme des quantités reçues via transferts (service comme destination)
  - Repository : `ConsumableTransferRepository::sumQuantiteReceivedByConsumableAndService()`
  - Table : `consumable_transfer`
  - Filtres : `consumable_id = ?`, `service_destination_id = ?`, `is_delete = false`

- **transfertsEffectues** : Somme des quantités envoyées via transferts (service comme source)
  - Repository : `ConsumableTransferRepository::sumQuantiteSentByConsumableAndService()`
  - Table : `consumable_transfer`
  - Filtres : `consumable_id = ?`, `service_source_id = ?`, `is_delete = false`

- **sorties** : Somme des sorties de consommables
  - Repository : `ConsumableTransferRepository::sumSortiesByConsumableAndService()`
  - Table : `consumable_transfer`
  - Filtres : `consumable_id = ?`, `service_id = ?`, `is_delete = false`

**Code PHP :**

```php
$stockInitial = $this->consumableEntryRepository->sumQuantiteByConsumableAndService($consumableId, $serviceId);
$transfertsRecus = $this->consumableTransferRepository->sumQuantiteReceivedByConsumableAndService($consumableId, $serviceId);
$transfertsEffectues = $this->consumableTransferRepository->sumQuantiteSentByConsumableAndService($consumableId, $serviceId);
$sorties = $this->consumableTransferRepository->sumSortiesByConsumableAndService($consumableId, $serviceId);

$stockActuel = bcadd($stockInitial, $transfertsRecus, 2);
$stockActuel = bcsub($stockActuel, $transfertsEffectues, 2);
$stockActuel = bcsub($stockActuel, $sorties, 2);

return max(0, (float) $stockActuel);
```

**Note :** Le résultat est borné à 0 minimum (pas de stock négatif).

---

### Stock Global du Consommable

**Méthode :** `ConsumableRepository::getStockActuel(int $consumableId)`

**Fichier :** `src/Repository/ConsumableRepository.php` (lignes 60-103)

**Formule :**

```
stockActuel = totalEntrees - totalTransferts + totalRetours
```

**Détail des composants :**

- **totalEntrees** : Somme de toutes les entrées de consommables (tous services confondus)
  - Table : `consumable_entry`
  - Filtres : `consumable_id = ?`, `is_delete = false`

- **totalTransferts** : Somme de tous les transferts (tous services confondus)
  - Table : `consumable_transfer`
  - Filtres : `consumable_id = ?`, `is_delete = false`

- **totalRetours** : Somme des retours BSP (quantiteServie des BSP avec retour = true)
  - Tables : `bsp`, `consumable_bsp`, `consumable_transfer`
  - Filtres : `consumable_id = ?`, `bsp.retour = true`, `is_delete = false` (pour les 3 tables)

**Code PHP :**

```php
// Somme des entrées
$qbEntrees = $this->getEntityManager()->createQueryBuilder()
    ->select('COALESCE(SUM(ce.quantite), 0)')
    ->from('App\Entity\ConsumableEntry', 'ce')
    ->where('ce.consumable = :id')
    ->andWhere('ce.isDelete = false')
    ->setParameter('id', $consumableId);
$totalEntrees = (float) $qbEntrees->getQuery()->getSingleScalarResult();

// Somme des transferts
$qbTransferts = $this->getEntityManager()->createQueryBuilder()
    ->select('COALESCE(SUM(ct.quantite), 0)')
    ->from('App\Entity\ConsumableTransfer', 'ct')
    ->where('ct.consumable = :id')
    ->andWhere('ct.isDelete = false')
    ->setParameter('id', $consumableId);
$totalTransferts = (float) $qbTransferts->getQuery()->getSingleScalarResult();

// Somme des retours BSP
$qbRetours = $this->getEntityManager()->createQueryBuilder()
    ->select('COALESCE(SUM(b.quantiteServie), 0)')
    ->from('App\Entity\Bsp', 'b')
    ->innerJoin('App\Entity\ConsumableBsp', 'cb', 'WITH', 'cb.bsp = b.id')
    ->innerJoin('App\Entity\ConsumableTransfer', 'ct', 'WITH', 'ct.id = cb.consumableTransfer')
    ->where('ct.consumable = :id')
    ->andWhere('b.retour = true')
    ->andWhere('b.isDelete = false')
    ->andWhere('ct.isDelete = false')
    ->andWhere('cb.isDelete = false')
    ->setParameter('id', $consumableId);
$totalRetours = (float) $qbRetours->getQuery()->getSingleScalarResult();

return $totalEntrees - $totalTransferts + $totalRetours;
```

**Note importante :** Le champ `quantite` de la table `consumable` n'est PAS utilisé dans ce calcul. Il sert uniquement de référence initiale lors de la création.

---

### Vérification de Transfert

**Méthode :** `GestionStockConsommableService::canTransfer(int $consumableId, int $serviceSourceId, string $quantite)`

**Fichier :** `src/Service/GestionStockConsommableService.php` (lignes 131-151)

**Formule :**

```
if (stockActuel < quantiteDemandee) {
    return [canTransfer: false, message: 'Stock insuffisant']
} else {
    return [canTransfer: true, nouveauStock: stockActuel - quantiteDemandee]
}
```

**Détail :**

- **stockActuel** : Calculé via `getStock($consumableId, $serviceSourceId)`
- **quantiteDemandee** : La quantité demandée pour le transfert

**Code PHP :**

```php
$stockActuel = $this->getStock($consumableId, $serviceSourceId);
$quantiteDemandee = (float) $quantite;

if ($stockActuel < $quantiteDemandee) {
    return [
        'canTransfer' => false,
        'stockDisponible' => $stockActuel,
        'quantiteDemandee' => $quantiteDemandee,
        'message' => 'Stock insuffisant',
    ];
}

return [
    'canTransfer' => true,
    'stockDisponible' => $stockActuel,
    'quantiteDemandee' => $quantiteDemandee,
    'nouveauStock' => $stockActuel - $quantiteDemandee,
];
```

**Utilisation :** Cette méthode est appelée dans `ConsumableTransferService::createDirectTransfer()` avant de créer un transfert pour vérifier que le service source a suffisamment de stock.

---

## Stock des Biens (Assets)

**Note :** Les biens (Assets) n'ont pas de système de gestion de stock complexe comme les consommables. Ils ont un champ `quantiteStock` qui représente la quantité physique disponible.

**Champ :** `Asset::quantiteStock`

**Fichier :** `src/Entity/Asset.php`

**Type :** DECIMAL(18, 2)

**Utilisation :** Ce champ est géré manuellement et n'a pas de calcul automatique basé sur les mouvements. Il est simplement stocké et affiché dans les réponses API.

---

## Services Impliqués

### GestionStockConsommableService

**Fichier :** `src/Service/GestionStockConsommableService.php`

**Responsabilités :**
- Calcul du stock par service
- Vérification de la possibilité de transfert
- Récupération de la situation des stocks avec filtres

**Méthodes principales :**
- `getStock(int $consumableId, int $serviceId): string` - Stock par service
- `canTransfer(int $consumableId, int $serviceSourceId, string $quantite): array` - Vérification transfert
- `getStockSituation(array $filters, int $page, int $limit): array` - Situation globale des stocks

### ConsumableStockManager

**Fichier :** `src/Service/ConsumableStockManager.php`

**Responsabilités :**
- Recalcul et persistance du stock actuel dans le champ `stockActuel` de l'entité `Consumable`
- Appelé après chaque mouvement de stock (entrée, transfert, retour, consommation)

**Méthode principale :**
- `recalculateAndPersist(?Consumable $consumable): void` - Recalcule et persiste le stock

**Note :** Ce service met à jour le champ `stockActuel` dans la base de données, mais ce champ n'est actuellement pas utilisé dans les calculs. Le stock est toujours recalculé à la volée via `ConsumableRepository::getStockActuel()`.

---

## Repositories Impliqués

### ConsumableEntryRepository

**Fichier :** `src/Repository/ConsumableEntryRepository.php`

**Méthodes de calcul :**
- `sumQuantiteByConsumableAndService(int $consumableId, int $serviceId): string` - Somme des entrées pour un consommable et un service
- `sumQuantiteByService(int $serviceId): array` - Somme des entrées pour un service (groupé par consommable)

### ConsumableTransferRepository

**Fichier :** `src/Repository/ConsumableTransferRepository.php`

**Méthodes de calcul :**
- `sumQuantiteReceivedByConsumableAndService(int $consumableId, int $serviceId): string` - Somme des transferts reçus (service comme destination)
- `sumQuantiteSentByConsumableAndService(int $consumableId, int $serviceId): string` - Somme des transferts envoyés (service comme source)
- `sumSortiesByConsumableAndService(int $consumableId, int $serviceId): string` - Somme des sorties

### ConsumableRepository

**Fichier :** `src/Repository/ConsumableRepository.php`

**Méthodes de calcul :**
- `getStockActuel(int $consumableId): float` - Stock global du consommable
- `calculateAllStocks(array $filters): array` - Calcul des stocks pour tous les services
- `countServicesWithStock(array $filters): int` - Compte des services avec stock

---

## Résumé des Formules

### Stock par Service

```
stockActuel = stockInitial + transfertsRecus - transfertsEffectues - sorties
```

### Stock Global du Consommable

```
stockActuel = totalEntrees - totalTransferts + totalRetours
```

### Vérification de Transfert

```
if (stockActuel < quantiteDemandee) {
    // Refus : Stock insuffisant
} else {
    // Accepté : nouveauStock = stockActuel - quantiteDemandee
}
```

---

## Points d'Attention

1. **Double comptage évité :** Le champ `quantite` de la table `consumable` n'est PAS utilisé dans le calcul du stock global pour éviter le double comptage avec les entrées créées automatiquement.

2. **Stock minimum :** Le stock par service est borné à 0 minimum (pas de stock négatif).

3. **Soft delete :** Tous les calculs filtrent sur `is_delete = false` pour exclure les enregistrements supprimés.

4. **Précision décimale :** Les calculs utilisent `bcadd()` et `bcsub()` avec une précision de 2 décimales pour éviter les erreurs d'arrondi.

5. **Service source automatique :** Lors de la création d'un transfert direct, le service source est récupéré automatiquement depuis l'utilisateur connecté. Si l'utilisateur n'a pas de service, le service de destination est utilisé comme fallback.

---

## Date de mise à jour

26 août 2026
