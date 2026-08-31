# Guide de l'Amortissement des Biens Patrimoniaux

## Vue d'ensemble

Le système d'amortissement calcule automatiquement la dépréciation d'un bien patrimonial en fonction de sa valeur d'acquisition, de sa date d'acquisition et des paramètres de son type de bien.

## Fichiers impliqués dans le calcul de l'amortissement

### 1. **Calculateur principal**
**Fichier :** `src/Service/AssetDepreciation/AssetDepreciationCalculator.php`

C'est le fichier principal qui contient la logique de calcul de l'amortissement.

**Méthode principale :** `calculate(Asset $asset): AssetDepreciationResult`

**Ce que fait le calcul actuel :**
- Récupère la valeur d'acquisition du bien
- Récupère la date d'acquisition
- Récupère la durée de vie et le taux du type de bien
- Calcule le nombre d'années écoulées depuis l'acquisition
- Calcule l'amortissement annuel (valeur / durée de vie)
- Calcule l'amortissement cumulé (amortissement annuel × années écoulées)
- Calcule la valeur actuelle (valeur d'acquisition - amortissement cumulé)

**Pour modifier le calcul :**
C'est dans ce fichier que vous devez intervenir. Modifiez la méthode `calculate()` pour changer la logique.

### 2. **Résultat du calcul**
**Fichier :** `src/Service/AssetDepreciation/AssetDepreciationResult.php`

Classe qui contient les données retournées par le calcul :
- `valeurAcquisition` : Valeur d'origine du bien
- `valeurActuelle` : Valeur après amortissement
- `amortissementAnnuel` : Montant amorti chaque année
- `amortissementCumule` : Total amorti depuis l'acquisition
- `dureeVie` : Durée de vie du bien (en années)
- `taux` : Taux d'amortissement
- `anneesEcoulees` : Nombre d'années depuis l'acquisition
- `dateAcquisition` : Date d'acquisition

### 3. **Intégration dans la réponse API**
**Fichier :** `src/Service/AssetResponseBuilder.php`

Dans la méthode `buildDetail()`, le calcul est appelé et le résultat est ajouté à la réponse :

```php
$depreciationResult = $this->depreciationCalculator->calculate($asset);

return [
    // ... autres champs
    'amortissement' => [
        'valeurAcquisition' => $depreciationResult->valeurAcquisition,
        'valeurActuelle' => $depreciationResult->valeurActuelle,
        // ... etc
    ],
];
```

### 4. **Entités liées**

#### Dépréciations manuelles
**Fichier :** `src/Entity/AssetDepreciation.php`

Les dépréciations créées manuellement via l'API. Elles sont stockées dans la table `asset_depreciation` et liées aux biens via une relation ManyToMany.

#### Réévaluations
**Fichier :** `src/Entity/AssetReevaluation.php`

Les réévaluations qui modifient la valeur d'un bien. Elles sont stockées dans la table `asset_reevaluation` et liées aux biens via une relation ManyToMany.

#### Maintenances
**Fichier :** `src/Entity/Maintenance.php`

Les maintenances effectuées sur un bien, avec leurs coûts.

## Comment modifier le calcul d'amortissement

### Étape 1 : Modifier le calculateur

Ouvrez `src/Service/AssetDepreciation/AssetDepreciationCalculator.php` et modifiez la méthode `calculate()`.

**Exemple : Ajouter la prise en compte des réévaluations**

```php
public function calculate(Asset $asset): AssetDepreciationResult
{
    $valeur = $this->parseValeur($asset->getValeur());
    $dateAcquisition = $asset->getDateAcquisition();
    $typeBien = $asset->getAssetTypes()->first();

    // ✅ Récupérer la dernière réévaluation si elle existe
    $lastReevaluation = $asset->getReevaluations()->last();
    if ($lastReevaluation && !$lastReevaluation->isDelete()) {
        $valeur = $this->parseValeur($lastReevaluation->getNouvelleValeur());
        // Optionnel : utiliser la date de réévaluation comme nouvelle date de départ
        // $dateAcquisition = $lastReevaluation->getDateReevaluation();
    }

    // ... suite du calcul
}
```

**Exemple : Ajouter la prise en compte des dépréciations manuelles**

```php
public function calculate(Asset $asset): AssetDepreciationResult
{
    // ... récupération des données de base

    // ✅ Récupérer la dernière dépréciation manuelle si elle existe
    $lastDepreciation = $asset->getDepreciations()->last();
    if ($lastDepreciation && !$lastDepreciation->isDelete()) {
        // Utiliser la valeur actuelle de la dépréciation manuelle
        $valeurActuelle = $this->parseValeur($lastDepreciation->getValeurActuelle());
        // Retourner directement cette valeur
        return new AssetDepreciationResult(
            valeurAcquisition: $valeur,
            valeurActuelle: $valeurActuelle,
            // ... autres valeurs
        );
    }

    // ... suite du calcul standard
}
```

### Étape 2 : Mettre à jour le résultat si nécessaire

Si vous ajoutez de nouveaux champs au calcul, modifiez `src/Service/AssetDepreciation/AssetDepreciationResult.php` pour les inclure.

### Étape 3 : Mettre à jour la réponse API si nécessaire

Si vous changez la structure du résultat, modifiez `src/Service/AssetResponseBuilder.php` dans la méthode `buildDetail()` pour refléter ces changements.

### Étape 4 : Vider le cache

Après modification, exécutez :
```bash
php bin/console cache:clear
```

## Logique actuelle du calcul

Le calcul suit cet ordre de priorité :

1. **Dépréciations manuelles (priorité 1)** : Si le bien a une dépréciation manuelle :
   - La valeur actuelle de la dépréciation est utilisée comme valeur de départ
   - La durée de vie de la dépréciation est utilisée
   - Le taux de la dépréciation est utilisé
   - La date de dépréciation est utilisée comme date de départ
   - L'amortissement annuel et cumulé sont calculés à partir de ces valeurs
   - Les années écoulées sont calculées entre la date de dépréciation et aujourd'hui
   - **Si une réévaluation existe après la dépréciation** : La nouvelle valeur de la réévaluation est utilisée mais la date de départ reste toujours la date de dépréciation

2. **Réévaluations (priorité 2)** : Si le bien a une réévaluation (sans dépréciation) :
   - La nouvelle valeur de la dernière réévaluation est utilisée comme valeur de départ
   - Le calcul automatique continue avec cette nouvelle valeur

3. **Calcul automatique (priorité 3)** : Si aucune dépréciation manuelle ni réévaluation n'existe, le calcul standard est effectué :
   - Vérification des données requises : Si la valeur, la date d'acquisition ou le type de bien sont manquants, retourne des valeurs null.
   - Calcul des années écoulées : Nombre d'années complètes entre la date d'acquisition et aujourd'hui.
   - Amortissement annuel : Valeur d'acquisition / Durée de vie
   - Amortissement cumulé : Amortissement annuel × Années écoulées
   - Valeur actuelle : Valeur d'acquisition - Amortissement cumulé (jamais négative)

## Améliorations possibles

- ✅ Prendre en compte la dernière réévaluation comme nouvelle valeur de départ (IMPLEMENTÉ)
- ✅ Prendre en compte les dépréciations manuelles existantes (IMPLEMENTÉ)
- Prendre en compte les coûts de maintenance
- Utiliser la date de la dernière réévaluation comme nouvelle date de départ (commenté dans le code)
- Calculer l'amortissement au prorata (mois/jours) au lieu d'années complètes

## Test du calcul

Pour tester vos modifications, appelez l'API :
```bash
curl -X GET 'https://localhost:8000/assets/{id}' \
  -H 'Authorization: Bearer YOUR_TOKEN'
```

Le bloc `amortissement` dans la réponse contiendra le résultat de votre calcul modifié.
