# Restitution Automatique des Biens de Projets Expirés

## Overview

Ce système permet de restituer automatiquement les biens dont le projet est expiré (date de fin prévue dépassée). La restitution est déclenchée par une tâche planifiée qui s'exécute périodiquement.

## Fonctionnement

### 1. Détection des Projets Expirés

Un projet est considéré comme expiré si :
- Il n'est pas supprimé (`isDelete = false`)
- Il a une date de fin prévue renseignée (`dateFinPrevue IS NOT NULL`)
- La date de fin prévue est antérieure à aujourd'hui (`dateFinPrevue < aujourd'hui`)

### 2. Conditions de Restitution d'un Bien

Pour qu'un bien soit restitué automatiquement, il doit remplir TOUTES les conditions suivantes :
- Le bien n'est pas supprimé (`isDelete = false`)
- Le bien a le statut `ACTIF`
- Le bien a un `serviceRestitution` renseigné
- Aucune restitution n'est déjà en cours pour ce bien

Une restitution est considérée comme "en cours" si :
- `typeAffectation = 'RESTITUTION'`
- `detenteur = true` (affectation active)
- `dateFin IS NULL` (pas terminée)
- `isDelete = false` (non supprimée)

### 3. Processus de Restitution

Lorsqu'un bien remplit toutes les conditions :
1. Le système crée une nouvelle affectation de type `RESTITUTION`
2. L'affectation est liée au `serviceRestitution` du bien
3. La date de début de l'affectation est fixée à la date du jour
4. Si une affectation en cours existe, elle est automatiquement clôturée (dateFin = date du jour)
5. Une notification est envoyée à tous les utilisateurs du service de restitution

## Architecture Technique

### Fichiers Impliqués

#### 1. `src/Repository/ProjectRepository.php`

**Méthode ajoutée :** `findExpiredProjects()`

Cette méthode récupère tous les projets expirés depuis la base de données en utilisant une requête Doctrine QueryBuilder.

```php
public function findExpiredProjects(): array
{
    $today = new \DateTimeImmutable('today');

    return $this->createQueryBuilder('p')
        ->where('p.isDelete = false')
        ->andWhere('p.dateFinPrevue IS NOT NULL')
        ->andWhere('p.dateFinPrevue < :today')
        ->setParameter('today', $today)
        ->getQuery()
        ->getResult();
}
```

#### 2. `src/Command/RestituerBiensProjetsExpiresCommand.php`

**Commande Symfony :** `app:restituer-biens-projets-expires`

Cette commande exécute le processus de restitution automatique. Elle :
- Récupère les projets expirés via `ProjectRepository::findExpiredProjects()`
- Pour chaque projet, récupère les biens associés via `AssetRepository` (relation ManyToMany)
- Vérifie les conditions de restitution pour chaque bien
- Crée les restitutions via `AssetAssignmentService::restituerAsset()`
- Affiche un résumé détaillé des opérations effectuées

**Note importante :** La relation entre Asset et Project est ManyToMany définie du côté Asset (table `asset_project`). La commande utilise un QueryBuilder pour récupérer les biens d'un projet.

**Utilisation manuelle :**
```bash
php bin/console app:restituer-biens-projets-expires
```

#### 3. `config/scheduler.yaml`

**Configuration du scheduler :**

```yaml
when@prod:
    scheduler:
        schedules:
            app_restituer_biens_projets_expires:
                # S'exécute tous les jours à minuit
                cron: '0 0 * * *'
                command: 'php bin/console app:restituer-biens-projets-expires'
                description: 'Restitution automatique des biens de projets expirés'
```

La commande est planifiée pour s'exécuter tous les jours à minuit en environnement de production.

### Services Utilisés

- `ProjectRepository` : Récupération des projets expirés
- `AssetAssignmentRepository` : Vérification des restitutions existantes
- `AssetAssignmentService` : Création des restitutions

## Logs et Rapports

La commande fournit des logs détaillés en sortie console :

### Exemple de Sortie

```
Restitution automatique des biens de projets expirés
==============================

3 projet(s) expiré(s) trouvé(s).

Projet : Projet A (ID: 1)
==========================
  - Bien #10 : restitué au service Direction Informatique
  - Bien #11 : ignoré (pas de service de restitution)
  - Bien #12 : ignoré (statut: EN MAINTENANCE)
  - Bien #13 : ignoré (restitution déjà en cours)
  Résumé projet : 4 biens traités, 1 restitués, 3 ignorés

Projet : Projet B (ID: 2)
==========================
  - Bien #20 : restitué au service Direction Financière
  Résumé projet : 1 biens traités, 1 restitués, 0 ignorés

Terminé : 5 biens traités, 2 restitués, 3 ignorés
```

## Installation et Configuration

### Configuration du Cron Système

Le système de restitution automatique utilise le cron système natif pour exécuter la commande tous les jours à minuit. Aucun package Symfony supplémentaire n'est requis.

#### Sur Linux (VPS)

**Éditer le crontab :**
```bash
crontab -e
```

**Ajouter la ligne suivante :**
```bash
# Exécution tous les jours à minuit
0 0 * * * cd var/www/html/Minepia_gestion_patrimoine/api_gestion_patrimoine && php bin/console app:restituer-biens-projets-expires >> /var/log/restitution.log 2>&1
```

**Remplacez `/chemin/vers/votre/projet` par le chemin réel de votre projet sur le VPS.**

**Vérifier le crontab actuel :**
```bash
crontab -l
```

**Voir les logs :**
```bash
tail -f /var/log/restitution.log
```

#### Sur Windows

**Ouvrir le Planificateur de tâches :**
- Appuyez sur `Win + R`, tapez `taskschd.msc` et appuyez sur Entrée

**Créer une nouvelle tâche :**
1. Clic droit sur "Planificateur de tâches" → "Créer une tâche de base"
2. **Nom** : Restitution automatique des biens
3. **Déclencheur** :
   - Sélectionner "Quotidien"
   - Heure : 00:00
   - Répéter tous les 1 jours
4. **Action** :
   - Sélectionner "Démarrer un programme"
   - Programme : `php`
   - Arguments : `bin/console app:restituer-biens-projets-expires`
   - Démarrer dans : `e:\GESTION DU PATRIMOINE\api_gestion_patrimoine`
5. Cliquez sur "Terminer"

**Tester la tâche manuellement :**
- Clic droit sur la tâche → "Exécuter"

**Voir les résultats :**
- Clic droit sur la tâche → "Historique"

## Maintenance

### Modifier la Fréquence d'Exécution

#### Sur Linux (crontab)

Pour changer la fréquence d'exécution, éditez le crontab :

```bash
crontab -e
```

**Exemples de fréquences :**
```bash
# Toutes les heures
0 * * * * cd /chemin/vers/votre/projet && php bin/console app:restituer-biens-projets-expires >> /var/log/restitution.log 2>&1

# Tous les lundis à 8h
0 8 * * 1 cd /chemin/vers/votre/projet && php bin/console app:restituer-biens-projets-expires >> /var/log/restitution.log 2>&1

# Tous les 1er du mois à minuit
0 0 1 * * cd /chemin/vers/votre/projet && php bin/console app:restituer-biens-projets-expires >> /var/log/restitution.log 2>&1
```

#### Sur Windows (Planificateur de tâches)

Pour changer la fréquence d'exécution :
1. Ouvrir le Planificateur de tâches
2. Clic droit sur la tâche → "Propriétés"
3. Onglet "Déclencheurs"
4. Sélectionner le déclencheur existant → "Modifier"
5. Modifier les paramètres selon vos besoins

### Désactiver la Tâche Planifiée

#### Sur Linux (crontab)

```bash
crontab -e
# Commentez ou supprimez la ligne correspondante
# 0 0 * * * cd /chemin/vers/votre/projet && php bin/console app:restituer-biens-projets-expires >> /var/log/restitution.log 2>&1
```

#### Sur Windows (Planificateur de tâches)

1. Ouvrir le Planificateur de tâches
2. Clic droit sur la tâche → "Désactiver"
3. Ou supprimer la tâche avec "Supprimer"

### Tester la Commande

Exécutez la commande manuellement pour tester :

```bash
php bin/console app:restituer-biens-projets-expires
```

## Dépannage

### Aucun projet expiré trouvé

Si la commande affiche "Aucun projet expiré trouvé", vérifiez :
- Que les projets ont une `dateFinPrevue` renseignée
- Que la `dateFinPrevue` est bien antérieure à la date actuelle
- Que les projets ne sont pas supprimés (`isDelete = false`)

### Biens ignorés

Les biens peuvent être ignorés pour plusieurs raisons :
- **Supprimé** : Le bien a été marqué comme supprimé
- **Statut non ACTIF** : Le bien n'est pas en statut ACTIF (ex: EN MAINTENANCE, SORTIE)
- **Pas de service de restitution** : Le bien n'a pas de `serviceRestitution` renseigné
- **Restitution déjà en cours** : Une restitution est déjà active pour ce bien

### Erreurs lors de la restitution

Si une erreur survient lors de la restitution d'un bien :
- L'erreur est loggée dans la console
- Le bien est marqué comme ignoré
- Le processus continue avec les biens suivants
- Vérifiez les logs pour identifier la cause de l'erreur

## Sécurité

- La commande ne restitue que les biens avec un `serviceRestitution` valide
- Les notifications sont envoyées uniquement aux utilisateurs du service de restitution
- Les biens supprimés ou non ACTIFS ne sont jamais restitués
- Les restitutions en double sont évitées par vérification préalable

## Notes Importantes

- La restitution automatique utilise le même service que la restitution manuelle (`AssetAssignmentService::restituerAsset`)
- Les notifications sont envoyées à tous les utilisateurs du service, pas à un utilisateur spécifique
- La commande est idempotente : elle peut être exécutée plusieurs fois sans créer de duplications
- En cas d'erreur sur un bien, la commande continue avec les biens suivants

php bin/console app:restituer-biens-projets-expires