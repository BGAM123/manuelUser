# Implémentation de la Double Authentification (2FA) - OTP

## Vue d'ensemble

L'implémentation 2FA (Two-Factor Authentication) par code OTP (One-Time Password) a été ajoutée à l'API Symfony existante. Cette feature est **complètement optionnelle** par utilisateur et ne casse en rien le fonctionnement existant.

## Architecture Implémentée

### 1. Modifications de la Base de Données

**Migration Doctrine: `Version20260729174556`**

Trois nouveaux champs ont été ajoutés à la table `user`:
- `two_factor_enabled` (TINYINT, défaut: 0) - Booléen indiquant si 2FA est activé
- `otp_code` (VARCHAR(10), nullable) - Code OTP stocké temporairement
- `otp_expires_at` (DATETIME, nullable) - Date/heure d'expiration du code

```sql
ALTER TABLE user ADD two_factor_enabled TINYINT DEFAULT 0 NOT NULL;
ALTER TABLE user ADD otp_code VARCHAR(10) DEFAULT NULL;
ALTER TABLE user ADD otp_expires_at DATETIME DEFAULT NULL;
```

### 2. Entité User (`src/Entity/User.php`)

Propriétés ajoutées:
```php
#[ORM\Column(name: 'two_factor_enabled', type: 'boolean', options: ['default' => false])]
#[Groups(['user:read', 'user:detail'])]
#[SerializedName('twoFactorEnabled')]
private bool $twoFactorEnabled = false;

#[ORM\Column(name: 'otp_code', type: 'string', length: 10, nullable: true)]
private ?string $otpCode = null;

#[ORM\Column(name: 'otp_expires_at', type: 'datetime', nullable: true)]
private ?\DateTimeInterface $otpExpiresAt = null;
```

Getters/setters:
- `isTwoFactorEnabled(): bool`
- `getIsTwoFactorEnabled(): bool`
- `setTwoFactorEnabled(bool): self`
- `getOtpCode(): ?string`
- `setOtpCode(?string): self`
- `getOtpExpiresAt(): ?\DateTimeInterface`
- `setOtpExpiresAt(?\DateTimeInterface): self`

### 3. Service OTP (`src/Service/OtpService.php`)

Responsabilités:
- **Génération d'OTP**: Code aléatoire à 6 chiffres
- **Sauvegarde**: Stockage du code et de l'expiration (5 minutes)
- **Validation**: Vérification du code avec contrôle d'expiration
- **Nettoyage**: Suppression du code après validation

Méthodes publiques:
```php
generateAndSendOtp(User $user): string
validateOtp(User $user, string $providedOtp): bool
clearOtp(User $user): void
```

Configuration `.env`:
```
MAILER_DSN=smtp://USERNAME:PASSWORD@smtp.gmail.com:587?encryption=tls&auth_mode=login
MAIL_FROM_ADDRESS=nengue382@gmail.com
MAIL_FROM_NAME="Library patnuc"
```

### 4. Contrôleurs Implémentés

#### **AuthenticationSuccessHandler** (`src/Security/AuthenticationSuccessHandler.php`)

Modifié pour gérer deux cas:

**Cas 1: Utilisateur sans 2FA** (`twoFactorEnabled = false`)
- Validation email + password
- Génération directe du JWT
- Retour du token JWT comme avant

```json
{
  "success": true,
  "status": 200,
  "message": "Authentication successful.",
  "data": {
    "token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "a1b2c3d4..."
  }
}
```

**Cas 2: Utilisateur avec 2FA** (`twoFactorEnabled = true`)
- Validation email + password
- Génération d'un OTP aléatoire à 6 chiffres
- Envoi de l'OTP par email
- Retour `requires_otp: true` (pas de JWT)

```json
{
  "success": true,
  "status": 200,
  "message": "Code OTP envoyé avec succès.",
  "data": {
    "requires_otp": true
  }
}
```

#### **VerifyOtpController** (`src/Controller/Auth/VerifyOtpController.php`)

**Endpoint**: `POST /auth/verify-otp`

**Payload**:
```json
{
  "email": "user@example.com",
  "otp": "583921"
}
```

**Traitement**:
1. Recherche de l'utilisateur par email
2. Vérification que 2FA est activé
3. Validation du code OTP (contenu + expiration)
4. Suppression du code après validation
5. Génération du JWT
6. Retour de la réponse d'authentification

**Réponses**:

Succès (200):
```json
{
  "success": true,
  "status": 200,
  "message": "OTP vérifié avec succès.",
  "data": {
    "token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": null
  }
}
```

Code invalide (400):
```json
{
  "success": false,
  "status": 400,
  "message": "Code OTP invalide.",
  "data": null
}
```

Code expiré (400):
```json
{
  "success": false,
  "status": 400,
  "message": "Code OTP expiré.",
  "data": null
}
```

#### **TwoFactorController** (`src/Controller/Users/TwoFactorController.php`)

**Endpoint**: `PATCH /users/{id}/two-factor`

**Payload**:
```json
{
  "twoFactorEnabled": true
}
```

**Traitement**:
1. Validation du booléen
2. Mise à jour du statut 2FA
3. Si désactivation: nettoyage des codes OTP existants
4. Retour du nouveau statut

**Réponse**:
```json
{
  "success": true,
  "status": 200,
  "message": "Double authentification mise à jour avec succès.",
  "data": {
    "twoFactorEnabled": true
  }
}
```

### 5. Contrôleurs Modifiés

#### **CreateUserController** (`POST /users`)

Payload accepté:
```json
{
  "firstName": "alex",
  "lastName": "nengue",
  "email": "nengue382@gmail.com",
  "password": "password123",
  "twoFactorEnabled": true
}
```

- Champ `twoFactorEnabled` optionnel
- Défaut: `false` si absent
- Validation: doit être un booléen

#### **UpdateUserController** (`PUT/PATCH /users/{id}`)

Payload accepté:
```json
{
  "firstName": "alex",
  "lastName": "nengue",
  "twoFactorEnabled": true
}
```

- Permet le passage de `false` vers `true`
- Permet le passage de `true` vers `false`
- Si désactivation: nettoyage des codes OTP

#### **ProfileController** (`GET /profile`)

Réponse enrichie avec `twoFactorEnabled`:
```json
{
  "success": true,
  "status": 200,
  "message": "Profil récupéré avec succès.",
  "data": {
    "id": 9,
    "firstName": "John",
    "lastName": "Doe",
    "email": "john@example.com",
    "is_active": true,
    "twoFactorEnabled": false,
    "service": {...},
    "assignedRoles": [...]
  }
}
```

#### **ListUsersController** (`GET /users`)

Champ `twoFactorEnabled` ajouté à chaque utilisateur de la liste

#### **UserDetailController** (`GET /users/{id}`)

Champ `twoFactorEnabled` inclus dans la réponse de détail

### 6. Template Email (`templates/email/otp_code.html.twig`)

Email HTML professionnel contenant:
- Salutation personnalisée
- Code OTP formaté et visible
- Durée de validité (5 minutes)
- Avertissements de sécurité
- Notes de confidentialité

## Flux Utilisateur

### Scénario 1: Utilisateur sans 2FA (comportement par défaut)

```
1. POST /login_check
   Email: user@example.com
   Password: password123
   
2. ✓ Réponse 200
   {
     "token": "eyJ...",
     "refresh_token": "abc..."
   }
   
3. ✓ Connexion directe, accès à l'API
```

### Scénario 2: Activation de 2FA

```
1. POST /users/{id}
   { "twoFactorEnabled": true }
   
2. ✓ Profil utilisateur mis à jour
   Ou: PATCH /users/{id}/two-factor
       { "twoFactorEnabled": true }
```

### Scénario 3: Connexion avec 2FA activée

```
1. POST /login_check
   Email: user@example.com
   Password: password123
   
2. ✓ Réponse 200
   {
     "requires_otp": true
   }
   
3. ✓ Email envoyé à user@example.com
   Contient le code OTP à 6 chiffres
   
4. User copie le code reçu par email
   
5. POST /auth/verify-otp
   Email: user@example.com
   OTP: 583921
   
6. ✓ Réponse 200
   {
     "token": "eyJ...",
     "refresh_token": "abc..."
   }
   
7. ✓ Connexion complète, accès à l'API
```

## Gestion des Erreurs OTP

| Cas | Code HTTP | Message |
|-----|-----------|---------|
| Code invalide | 400 | "Code OTP invalide." |
| Code expiré | 400 | "Code OTP expiré." |
| Email non trouvé | 404 | "User not found." |
| 2FA non activée | 400 | "Two-factor authentication is not enabled for this user." |
| Email manquant | 400 | "Email and OTP are required." |

## Configuration Requise

### 1. Variables d'Environnement (.env)

```env
# Mailer Configuration
MAILER_DSN=smtp://USERNAME:PASSWORD@smtp.gmail.com:587?encryption=tls&auth_mode=login
MAIL_FROM_ADDRESS=nengue382@gmail.com
MAIL_FROM_NAME="Library patnuc"
```

### 2. Dépendances Installées

```bash
composer require symfony/mailer
```

Packages installés:
- `symfony/mailer` (v7.4.15)
- `symfony/mime` (v7.4.15)
- `egulias/email-validator` (4.0.4)

### 3. Configuration Symfony (`config/services.yaml`)

```yaml
App\Service\OtpService:
    arguments:
        $mailFromAddress: '%env(MAIL_FROM_ADDRESS)%'
        $mailFromName: '%env(MAIL_FROM_NAME)%'
```

## Validation & Sécurité

### Contraintes Appliquées

1. **OTP Code**
   - Longueur: 6 chiffres
   - Aléatoire: `random_int(0, 999999)`
   - Format: Zéro-padded

2. **Expiration**
   - Durée: 5 minutes
   - Vérification: Comparaison avec DateTime actuel
   - Nettoyage: Après validation réussie

3. **Champ twoFactorEnabled**
   - Type: Booléen
   - Validation: `#[Assert\Type(type: "bool")]`
   - Sérialisation: Groupe `['user:read', 'user:detail']`

4. **Stockage**
   - OTP Code: NOT NULL, VARCHAR(10)
   - Expiration: DATETIME nullable
   - Nettoyage automatique après validation

### Bonnes Pratiques Sécurité

✓ OTP généré de manière cryptographiquement sûre
✓ OTP stocké temporairement en base (5 min max)
✓ OTP comparé avec `hash_equals()` (protection timing attack)
✓ Email jamais loggé ou exposé
✓ OTP supprimé immédiatement après validation
✓ Nettoyage des OTP lors de désactivation de 2FA
✓ Alertes de sécurité dans l'email

## Endpoints Swagger/OpenAPI

Tous les endpoints ont été documentés avec:
- Descriptions détaillées
- Payloads d'exemple
- Réponses de succès et d'erreur
- Paramètres de requête

Endpoints:
- `POST /auth/verify-otp` - Vérifier OTP
- `PATCH /users/{id}/two-factor` - Activer/désactiver 2FA
- `POST /users` - Créer utilisateur (avec twoFactorEnabled)
- `PUT/PATCH /users/{id}` - Mettre à jour utilisateur (avec twoFactorEnabled)
- `GET /users` - Lister utilisateurs (inclut twoFactorEnabled)
- `GET /users/{id}` - Détail utilisateur (inclut twoFactorEnabled)
- `GET /profile` - Profil connecté (inclut twoFactorEnabled)

## Tests

Script de test fourni: `test_2fa_implementation.php`

```bash
php test_2fa_implementation.php
```

Tests couverts:
1. Création d'utilisateur avec 2FA activée
2. Login avec 2FA (vérification requires_otp)
3. Création d'utilisateur sans 2FA (défaut)
4. Login sans 2FA (JWT direct)
5. Update du statut 2FA
6. Vérification du champ twoFactorEnabled dans les réponses

## Compatibilité Rétroactive

✓ **Pas de casse du système existant**
- Utilisateurs existants: `twoFactorEnabled = false` par défaut
- Login existants: Fonctionnent comme avant (JWT direct)
- Utilisateurs actifs: Aucun impact si 2FA désactivée
- API Rest: Structure de réponse inchangée pour non-2FA

✓ **Architecture existante respectée**
- Utilise `ApiResponseFactory` existant
- Utilise `SerializerInterface` existant
- Utilise `ValidatorInterface` existant
- Utilise `MailerInterface` standard Symfony
- Respecte les groupes de sérialisation Symfony

## Fichiers Créés/Modifiés

### Créés:
- `src/Service/OtpService.php` - Service OTP
- `src/Controller/Auth/VerifyOtpController.php` - Vérification OTP
- `src/Controller/Users/TwoFactorController.php` - Gestion 2FA
- `templates/email/otp_code.html.twig` - Template email OTP
- `test_2fa_implementation.php` - Script de test
- `migrations/Version20260729174556.php` - Migration DB

### Modifiés:
- `src/Entity/User.php` - Ajout propriétés 2FA
- `src/Security/AuthenticationSuccessHandler.php` - Logique OTP
- `src/Controller/Users/CreateUserController.php` - Support twoFactorEnabled
- `src/Controller/Users/UpdateUserController.php` - Support twoFactorEnabled
- `src/Controller/Users/ProfileController.php` - Inclut twoFactorEnabled
- `src/Controller/Users/ListUsersController.php` - Inclut twoFactorEnabled
- `src/Controller/Users/UserDetailController.php` - Inclut twoFactorEnabled
- `config/services.yaml` - Configuration OtpService
- `.env` - Variables mailer

## Prochaines Étapes

1. **Configurer l'email**
   - Mettre à jour `MAILER_DSN` avec vrais credentials Gmail/autre
   - Tester l'envoi d'email

2. **Tester les endpoints**
   - Créer utilisateur avec 2FA
   - Vérifier réception email
   - Tester workflow complet

3. **Optionnel: Améliorations futures**
   - Mise en cache OTP avec Redis
   - Tentatives de validation limitées
   - Audit logging des validations OTP
   - Webhook/SMS comme alternative à email
   - Codes de secours/backup

## Support & Débogage

Logs recommandés:
- Vérifier `MAILER_DSN` correctement configurée
- Consulter `var/log/` pour les erreurs de mailer
- Vérifier table `user` pour OTP codes/expiration
- Activer Symfony Profiler pour les détails de requête

Questions fréquentes:
- **Q: Comment tester sans email?**
  A: Utiliser `var_dump()` avant `$this->mailer->send()` dans OtpService
  
- **Q: Puis-je changer la durée d'expiration OTP?**
  A: Modifier `const OTP_VALIDITY_MINUTES = 5;` dans OtpService
  
- **Q: Puis-je customiser le format OTP?**
  A: Modifier la fonction de génération dans `generateAndSendOtp()`
