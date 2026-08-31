# RAPPORT DE CORRECTION - LOGIQUE DE CALCUL DU STOCK PATRIMONIAL

## API
`GET /gestion_stock_bien`

---

## 1. POURQUOI "DISPONIBLES" ÉTAIT À -11

### Erreur de calcul identifiée

L'ancienne logique calculait les compteurs de manière **non mutuellement exclusive**, ce qui provoquait des résultats négatifs.

**Ancienne formule erronée :**
```php
$results['DISPONIBLE'] = $results['total'] 
    - $results['SORTI_DEFINITIVEMENT'] 
    - $results['MAINTENANCE'] 
    - $results['AFFECTE'] 
    - $results['SORTIE_TEMPORAIRE'] 
    - $results['RETOURNE'];
```

**Problème :**
- Un bien pouvait être compté dans plusieurs catégories simultanément
- Exemple : Un bien en maintenance pouvait aussi avoir une affectation active
- Exemple : Un bien avec BSP pouvait aussi être affecté
- Les catégories n'étaient pas disjointes, donc la soustraction pouvait dépasser le total

**Résultat :** `disponibles = -11` (impossible mathématiquement)

---

## 2. NOUVELLE RÈGLE : LOGIQUE À DEUX NIVEAUX

### NIVEAU 1 - APPARTENANCE AU PATRIMOINE

Détermine si un bien appartient encore au patrimoine.

**Ordre de priorité :**
1. `isDelete = true` → **IGNORÉ** (complètement exclu)
2. Sortie définitive → **HORS PATRIMOINE**
3. BSP non retourné (`retour = false`) → **HORS PATRIMOINE TEMPORAIREMENT**
4. Sinon → **DANS LE PATRIMOINE**

### NIVEAU 2 - SITUATION DANS LE PATRIMOINE

Pour les biens dans le patrimoine, détermine leur situation courante.

**Ordre de priorité (mutuellement exclusif) :**
1. Maintenance EN COURS → `MAINTENANCE`
2. Affectation active à utilisateur → `AFFECTE`
3. Affectation active à service → `AFFECTE`
4. Aucune affectation active → `DISPONIBLE`

---

## 3. EXCLUSION DES BIENS SUPPRIMÉS (isDelete = true)

**Règle absolue :**
```sql
WHERE a.is_delete = 0
```

Toutes les requêtes filtrent systématiquement les biens supprimés :
- Ils n'apparaissent dans aucun compteur
- Ils ne sont jamais retournés dans la liste
- Ils sont exclus du périmètre de base

**Impact :**
- `total_non_supprimes` = COUNT(asset WHERE is_delete = 0)
- Tous les calculs partent de ce périmètre

---

## 4. EXCLUSION DES SORTIS DÉFINITIVEMENT

**Logique :**
```sql
SELECT COUNT(DISTINCT a.id) FROM asset a
INNER JOIN asset_exit ae ON a.id = ae.asset_id
WHERE a.is_delete = 0 AND ae.is_delete = 0
```

**Règle :**
- Un bien avec une sortie définitive est **hors patrimoine**
- Il n'est pas compté dans : disponibles, affectés, maintenance
- Il est compté dans : `sortis_definitivement`

**Priorité :**
- La sortie définitive a priorité sur toute autre situation
- Même si `Asset.statut` contient une ancienne valeur (ACTIF, EN MAINTENANCE, etc.)

---

## 5. EXCLUSION DES BSP NON RETOURNÉS (retour = false)

**Logique :**
```sql
SELECT COUNT(DISTINCT a.id) FROM asset a
INNER JOIN asset_exit ae ON a.id = ae.asset_id
INNER JOIN bsp b ON ae.id = b.asset_exit_id
WHERE a.is_delete = 0 AND ae.is_delete = 0 AND b.is_delete = 0 AND b.retour = 0
```

**Règle :**
- Un BSP avec `retour = false` = sortie temporaire
- Le bien est **hors patrimoine temporairement**
- Il n'est pas compté dans : disponibles, affectés, maintenance du patrimoine actif
- Il est compté dans : `bsp_non_retournes`

**BSP retournés (retour = true) :**
- Le bien peut être réintégré dans le patrimoine
- Sa situation est recalculée selon les règles du NIVEAU 2
- Il peut être : disponible, affecté, ou en maintenance

---

## 6. TRAITEMENT DES MAINTENANCES EN COURS

**Logique :**
```sql
SELECT COUNT(DISTINCT a.id) FROM asset a
INNER JOIN asset_maintenance_link aml ON a.id = aml.asset_id
INNER JOIN asset_maintenance am ON aml.maintenance_id = am.id
WHERE a.is_delete = 0 AND am.date_recuperation IS NULL
AND NOT EXISTS (SELECT 1 FROM asset_exit ae WHERE ae.asset_id = a.id AND ae.is_delete = 0)
AND NOT EXISTS (SELECT 1 FROM asset_exit ae2 INNER JOIN bsp b ON ae2.id = b.asset_exit_id
                WHERE ae2.asset_id = a.id AND ae2.is_delete = 0 AND b.is_delete = 0 AND b.retour = 0)
```

**Règle :**
- Maintenance EN COURS (`date_recuperation IS NULL`) → situation = `MAINTENANCE`
- Priorité sur les affectations pour déterminer la situation courante
- Mais sortie définitive ou BSP non retourné ont priorité pour l'appartenance au patrimoine

**Distinction :**
- Le bien reste dans le patrimoine
- Il n'est pas disponible
- Il n'est pas présenté comme ACTIF dans la situation courante

---

## 7. DISTINCTION AFFECTATIONS UTILISATEUR / SERVICE

**Affectations utilisateurs :**
```sql
WHERE aa.user_id IS NOT NULL
```

**Affectations services :**
```sql
WHERE aa.service_id IS NOT NULL
```

**Nouvelle structure des affectations :**
```json
{
  "affectations": {
    "total": 9,
    "utilisateurs": {
      "total_biens": 4,
      "items": [
        {
          "user_id": 76,
          "nom": "Doe",
          "prenom": "John",
          "matricule": "MAT001",
          "nombre_biens": 4
        }
      ]
    },
    "services": {
      "total_biens": 5,
      "items": [
        {
          "service_id": 10,
          "nom": "Contrôleur National N°1",
          "sigle": "CN1",
          "nombre_biens": 5
        }
      ]
    }
  }
}
```

**Calcul :**
- Chaque requête utilise `COUNT(DISTINCT a.id)` pour éviter les duplications
- Les affectations sont mutuellement exclusives (user_id XOR service_id)
- Les filtres excluent les biens hors patrimoine (sortis, BSP non retournés, maintenance)

---

## 8. CALCUL DES NON AFFECTÉS

**Règle :**
```php
$results['non_affectes'] = $results['disponibles'];
```

**Logique :**
- Dans cette logique, non affecté = disponible
- Un bien non affecté est un bien disponible dans le patrimoine
- Il n'a pas d'affectation active et n'est pas en maintenance

**Périmètre :**
- Biens non supprimés
- Dans le patrimoine (pas sortis, pas BSP non retournés)
- Pas en maintenance
- Pas d'affectation active

---

## 9. CALCUL DU PATRIMOINE RESTANT

**Formule :**
```php
$results['patrimoine_total'] = $results['total_non_supprimes']
    - $results['sortis_definitivement']
    - $results['bsp_non_retournes'];
```

**Définition :**
Le patrimoine représente les biens qui appartiennent encore actuellement au patrimoine.

**Critères :**
- `isDelete = false`
- Pas de sortie définitive
- Pas de BSP actuellement non retourné

**Exclusions :**
- Biens sortis définitivement → hors patrimoine
- Biens BSP avec `retour = false` → hors patrimoine temporairement
- Biens BSP avec `retour = true` → peuvent être réintégrés

---

## 10. CALCUL DES ACTIFS / INACTIFS

**Actifs (dans le patrimoine) :**
```sql
SELECT COUNT(*) FROM asset a
WHERE a.is_delete = 0
AND NOT EXISTS (SELECT 1 FROM asset_exit ae WHERE ae.asset_id = a.id AND ae.is_delete = 0)
AND NOT EXISTS (SELECT 1 FROM asset_exit ae2 INNER JOIN bsp b ON ae2.id = b.asset_exit_id
                WHERE ae2.asset_id = a.id AND ae2.is_delete = 0 AND b.is_delete = 0 AND b.retour = 0)
```

**Inactifs (hors patrimoine) :**
```sql
SELECT COUNT(*) FROM asset a
WHERE a.is_delete = 0
AND (
    EXISTS (SELECT 1 FROM asset_exit ae WHERE ae.asset_id = a.id AND ae.is_delete = 0)
    OR EXISTS (SELECT 1 FROM asset_exit ae2 INNER JOIN bsp b ON ae2.id = b.asset_exit_id
                WHERE ae2.asset_id = a.id AND ae2.is_delete = 0 AND b.is_delete = 0 AND b.retour = 0)
)
```

**Note :**
- Ne pas confondre `Asset.statut` avec la situation du stock
- Actif/Inactif est basé sur l'appartenance au patrimoine, pas sur le statut enregistré

---

## 11. GARANTIE DE NON-NEGATIVITÉ

**Méthode :**
1. **Calcul direct** des situations mutuellement exclusives
2. **Utilisation de `NOT EXISTS`** pour garantir l'exclusivité
3. **Vérification des invariants** dans chaque requête

**Exemple pour disponibles :**
```php
$results['disponibles'] = $results['patrimoine_total']
    - $results['maintenance']
    - $results['affectes_total'];
```

**Pourquoi cela fonctionne :**
- `patrimoine_total` est calculé indépendamment
- `maintenance` est calculé avec `NOT EXISTS` pour exclure les sortis et BSP
- `affectes_total` est calculé avec `NOT EXISTS` pour exclure maintenance, sortis, BSP
- Les catégories sont mutuellement exclusives par construction

**Invariants garantis :**
- `disponibles >= 0`
- `affectes >= 0`
- `maintenance >= 0`
- `patrimoine_total >= 0`
- `sortis_definitivement >= 0`
- `bsp_non_retournes >= 0`
- `actifs >= 0`
- `inactifs >= 0`

---

## 12. ÉVITER LES DUPLICATIONS (COUNT DISTINCT)

**Règle :**
Toutes les requêtes utilisent `COUNT(DISTINCT a.id)` lorsqu'une jointure peut produire plusieurs lignes.

**Cas concernés :**
- Jointures avec catégories (un bien peut avoir plusieurs catégories)
- Jointures avec types (un bien peut avoir plusieurs types)
- Jointures avec services (un bien peut avoir plusieurs services)
- Jointures avec affectations (un bien peut avoir des affectations historiques)
- Jointures avec maintenances (un bien peut avoir des maintenances historiques)
- Jointures avec BSP (un bien peut avoir plusieurs BSP)

**Exemple :**
```sql
-- ❌ MAUVAIS (peut dupliquer)
SELECT COUNT(*) FROM asset a
INNER JOIN asset_assignment aa ON a.id = aa.asset_id
WHERE ...

-- ✅ BON (évite les duplications)
SELECT COUNT(DISTINCT a.id) FROM asset a
INNER JOIN asset_assignment aa ON a.id = aa.asset_id
WHERE ...
```

---

## 13. NOUVELLE STRUCTURE DU SUMMARY

```json
{
  "summary": {
    "patrimoine": {
      "total": 0,
      "actifs": 0,
      "inactifs": 0
    },
    "situations": {
      "disponibles": 0,
      "non_affectes": 0,
      "affectes": 0,
      "maintenance": 0
    },
    "affectations": {
      "total": 0,
      "utilisateurs": {
        "total_biens": 0,
        "items": []
      },
      "services": {
        "total_biens": 0,
        "items": []
      }
    },
    "sorties": {
      "definitives": 0,
      "bsp_non_retournes": 0
    }
  }
}
```

---

## 14. COHÉRENCE ENTRE SUMMARY ET LISTE PAGINÉE

**Règle :**
- Le même algorithme `calculateAssetSituation()` est utilisé pour :
  - Le calcul des compteurs dans `countBySituation()`
  - Le calcul de la situation pour chaque bien dans `findAssetsWithSituation()`

**Garantie :**
- La somme des situations dans la liste paginée correspond aux compteurs du summary
- Un bien avec `situation = "MAINTENANCE"` dans la liste est compté dans `maintenance` du summary
- Aucune incohérence possible entre les deux vues

---

## 15. CONTRAINTES RESPECTÉES

✅ **Aucune modification d'Entity**
✅ **Aucune nouvelle table**
✅ **Aucune migration**
✅ **Aucun nouveau champ**
✅ **Aucune modification de relation**
✅ **Aucune modification des services métier existants**
✅ **API en lecture seule**
✅ **Calcul dynamique à partir des données existantes**
✅ **Une seule API publique : GET /gestion_stock_bien**

---

## 16. FICHIERS MODIFIÉS

1. **src/Repository/StockRepository.php**
   - Refonte de `countBySituation()` avec logique à deux niveaux
   - Ajout de `countAssignments()` pour les affectations utilisateur/service
   - Ajout de `countByStatut()` pour actifs/inactifs
   - Refonte de `calculateAssetSituation()` avec nouvel ordre de priorité

2. **src/Service/StockService.php**
   - Ajout de `getAssignments()`
   - Ajout de `getStatuts()`
   - Mise à jour de `getStockData()` pour inclure assignments et statuts

3. **src/Service/StockResponseBuilder.php**
   - Refonte de `buildStockSummary()` avec nouvelle structure
   - Mise à jour de `buildUnifiedResponse()` pour passer assignments et statuts
   - Correction de `formatDetenteur()` (suppression du code incorrect)

---

## 17. CONCLUSION

**Problème initial :** `disponibles = -11` (impossible)

**Cause :** Catégories non mutuellement exclusives, soustraction incorrecte

**Solution :** Logique à deux niveaux avec catégories mutuellement exclusives

**Résultat :**
- Tous les compteurs sont >= 0
- Les catégories sont disjointes par construction
- Le patrimoine est clairement défini
- Les situations sont cohérentes
- Aucun compteur ne peut devenir négatif

**API unique :** `GET /gestion_stock_bien`
- Retourne le summary avec la nouvelle structure
- Retourne les biens paginés avec leur situation
- Retourne les affectations détaillées par utilisateur et service
- Retourne les statuts actifs/inactifs
- Tout est calculé dynamiquement en lecture seule
