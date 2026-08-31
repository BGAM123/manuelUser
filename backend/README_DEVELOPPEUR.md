# 📘 README COMPLET - API Gestion du Patrimoine

**Version**: 1.0.0  
**Dernière mise à jour**: 2 août 2026  
**Environnement**: Symfony 6.4, PHP 8.2+, MySQL/PostgreSQL

---

## 🎯 Vue d'Ensemble du Projet

### Description
C'est une **API REST complète de gestion du patrimoine** destinée aux organisations pour:
- Gérer leurs biens (ordinateurs, mobiliers, équipements, etc.)
- Organiser les biens par catégories et types
- Affecter les biens aux services/structures
- Assigner les biens aux projets
- Gérer les utilisateurs et leurs rôles
- Authentifier les utilisateurs avec 2FA par email

### Cas d'Usage
```
Organisation (MINEPIA)
  ├─ Direction Générale (Service)
  │  ├─ Direaction Administrative (Service enfant)
  │  │  └─ Secrétariat (Service enfant)
  │  └─ DSI (Service enfant)
  │     └─ Biens: Ordinateurs, Imprimantes, etc.
  ├─ Catégories: Matériel Informatique, Mobilier, Équipement
  ├─ Types de Biens: Ordinateur Portable, Bureau, Chaise
  ├─ Projets: Modernisation SI, Transformation Numérique
  ├─ Utilisateurs: Admin, Gestionnaires, Opérateurs
  └─ 2FA: Authentification sécurisée par OTP email
```

---

## 🚀 Démarrage Rapide

### 1. Installation
```bash
# Cloner le dépôt
git clone https://github.com/votre-org/gestion-patrimoine-api.git
cd gestion-patrimoine-api

# Installer les dépendances
composer install

# Configurer l'environnement
cp .env .env.local
# Éditer .env.local avec vos credentials
```

### 2. Configuration Base de Données
```bash
# .env.local
DATABASE_URL="mysql://user:password@127.0.0.1:3306/patrimoine_db"

# Créer la BD et exécuter les migrations
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

### 3. Configuration Email (2FA)
```bash
# .env.local
MAILER_DSN="smtp://your-email@gmail.com:your-password@smtp.gmail.com:587?encryption=tls&auth_mode=login"
MAIL_FROM_ADDRESS="noreply@patrimoine.cm"
MAIL_FROM_NAME="Gestion du Patrimoine"
```

Voir [EMAIL_CONFIGURATION.md](EMAIL_CONFIGURATION.md) pour les autres fournisseurs.

### 4. Générer les Clés JWT
```bash
# Générer la paire RSA (déjà générée en dev)
php bin/console lexik:jwt:generate-keypair
```

### 5. Lancer le Serveur
```bash
# Option 1: Serveur PHP natif
php -S localhost:8000 -t public

# Option 2: Symfony CLI (plus robuste)
symfony serve -d

# Accéder à l'API
# Swagger UI: http://localhost:8000/
# JSON: http://localhost:8000/doc.json
```

---

## 🏗️ Architecture Globale

### Structure des Dossiers
```
src/
├── Controller/
│   ├── Auth/                    # Authentification & 2FA
│   │   ├── VerifyOtpController.php
│   │   └── ...
│   ├── Users/                   # Gestion des utilisateurs
│   │   ├── CreateUserController.php
│   │   ├── UpdateUserController.php
│   │   ├── ListUsersController.php
│   │   ├── UserDetailController.php
│   │   ├── ProfileController.php
│   │   ├── TwoFactorController.php
│   │   └── ...
│   ├── Services/                # Gestion des services/organigramme
│   │   ├── OrganigrammeController.php
│   │   └── ...
│   └── Assets/                  # Gestion des biens
│       ├── CreateAssetController.php
│       ├── UpdateAssetController.php
│       ├── ListAssetsController.php
│       ├── AssetDetailController.php
│       └── ...
├── Entity/
│   ├── User.php
│   ├── Service.php
│   ├── Asset.php
│   ├── Category.php
│   ├── AssetType.php
│   ├── Role.php
│   ├── Permission.php
│   ├── Project.php
│   └── ...
├── Repository/
│   ├── UserRepository.php
│   ├── AssetRepository.php
│   ├── ServiceRepository.php
│   └── ...
├── Service/
│   ├── OtpService.php
│   ├── AssetManagementService.php
│   ├── ServiceHierarchyBuilder.php
│   ├── AssetResponseBuilder.php
│   ├── ApiResponseFactory.php
│   └── ...
├── Serializer/
│   ├── User*Normalizer.php
│   └── ServiceFlatNormalizer.php
└── Exception/
    ├── ResourceNotFoundException.php
    └── ValidationFailedException.php
```

### Flux de Données Typique

```
HTTP Request
    ↓
Controller (ex: CreateAssetController)
    ├─ Extrait & valide le payload
    ├─ Appelle le Service métier (AssetManagementService)
    │   ├─ Applique les données au modèle
    │   ├─ Charge les relations (Category, AssetType, etc.)
    │   ├─ Valide l'entité complète
    │   ├─ Persiste en BD via Repository
    │   └─ Retourne l'entité complète
    ├─ Construit la réponse JSON (ResponseBuilder)
    └─ Retourne JsonResponse
        ↓
HTTP Response (format uniforme)
{
  "success": true/false,
  "status": 200/404/400,
  "message": "...",
  "data": {...}
}
```

---

## 🔐 Authentification & Sécurité

### 1. Authentification JWT

**Flux**:
```
POST /login_check
{
  "email": "user@example.com",
  "password": "password123"
}
↓
Pas 2FA activé → Retour JWT immédiat ✅
{
  "data": {
    "token": "eyJhbGc...",
    "refresh_token": "..."
  }
}

Avec 2FA activé → Requête OTP ⏳
{
  "data": {
    "requires_otp": true
  }
}
```

**Utilisation du token**:
```bash
curl -H "Authorization: Bearer eyJhbGc..." http://localhost:8000/users
```

### 2. Authentification 2FA par Email

**Flux Complet**:
```
1. POST /login_check
   {"email": "user@example.com", "password": "..."}
   ↓ (2FA activé)
   Retour: {"requires_otp": true}
   L'utilisateur reçoit un email avec un code OTP à 6 chiffres

2. POST /auth/verify-otp
   {"email": "user@example.com", "otp": "583921"}
   ↓
   Retour: {"token": "JWT_TOKEN", "refresh_token": "..."}

3. Utiliser le token pour accéder à l'API
   GET /users -H "Authorization: Bearer JWT_TOKEN"
```

### 3. Autorisations & Rôles

Les rôles sont gérés via une relation ManyToMany User↔Role:

```php
// Limité à 2 rôles maximum par utilisateur
$user->addAssignedRole($role);  // Autorisé
$user->addAssignedRole($role2); // Autorisé
$user->addAssignedRole($role3); // ❌ Exception levée (MAX_ASSIGNED_ROLES = 2)
```

Rôles typiques: Admin, Gestionnaire, Responsable Structure, Opérateur

---

## 📊 Modèles de Données Clés

### User (Utilisateur)
```json
{
  "id": 1,
  "firstName": "Jean",
  "lastName": "NKOMO",
  "email": "jean@example.com",
  "is_active": true,
  "twoFactorEnabled": true,
  "createdAt": "2026-07-30 14:30:45",
  "service": {
    "id": 16,
    "nom": "DSI",
    "sigle": "DSI",
    "type_service": "SERVICE",
    "ordre": 1,
    "is_active": true
  },
  "assignedRoles": [
    { "id": 1, "nom": "Administrateur" },
    { "id": 2, "nom": "Gestionnaire" }
  ]
}
```

### Asset (Bien)
```json
{
  "id": 1,
  "reference": "PAT-2026-00001",
  "nom": "Ordinateur Portable HP ProBook 450 G10",
  "numeroSerie": "HP-PB-2026-0001",
  "description": "...",
  "statut": "ACTIF",
  "valeur": 850000,
  "dateAcquisition": "2026-07-30",
  "sourceFinancement": "Budget État",
  "modeAcquisition": "Achat",
  "categorie": { "id": 2, "nom": "Matériel Informatique" },
  "typeBien": { "id": 5, "nom": "Ordinateur Portable", "dureeVie": 5, "taux": 20 },
  "etatBien": { "id": 1, "nom": "Fonctionnel" },
  "projets": [
    { "id": 3, "nom": "Modernisation SI" }
  ],
  "service": { "id": 16, "nom": "DSI" },
  "utilisateur": { "id": 8, "firstName": "Jean", "lastName": "NGONO", "email": "..." },
  "fournisseur": { "type": "ENTREPRISE", "nom": "CAMTEL TECHNOLOGIES", ... },
  "photos": [ { "id": 15, "nom": "photo1.jpg", "chemin": "/uploads/assets/photos/photo1_abc.jpg" } ],
  "piecesJointes": [ { "id": 21, "nom": "Facture d'achat", "chemin": "/..." } ],
  "createdAt": "2026-07-30 15:45:10",
  "updatedAt": "2026-07-31 09:10:42"
}
```

### Service (Hiérarchie)
```
Direction Générale (parent: null)
  ├─ Direction Administrative (parent: Direction Générale)
  │   ├─ Secrétariat (parent: Direction Administrative)
  │   └─ Ressources Humaines (parent: Direction Administrative)
  └─ DSI (parent: Direction Générale)
      └─ Infrastructure (parent: DSI)
```

**Récupération hiérarchisée**:
```bash
GET /organigramme
```

Retourne:
```json
{
  "data": [
    {
      "id": 1,
      "nom": "Direction Générale",
      "children": [
        {
          "id": 2,
          "nom": "Direction Administrative",
          "children": [...]
        },
        {
          "id": 3,
          "nom": "DSI",
          "children": [...]
        }
      ]
    }
  ]
}
```

---

## 🔄 Endpoints API Principaux

### Authentication
```
POST   /login_check              # Obtenir JWT
POST   /auth/verify-otp          # Vérifier OTP et obtenir JWT
```

### Users
```
POST   /users                     # Créer utilisateur
GET    /users                     # Lister (paginé)
GET    /users/{id}                # Détail utilisateur
GET    /profile                   # Profil utilisateur connecté
PATCH  /users/{id}                # Modifier utilisateur
DELETE /users/{id}/soft-delete    # Supprimer (soft)
PATCH  /users/{id}/two-factor     # Activer/désactiver 2FA
```

### Services (Organigramme)
```
GET    /organigramme              # Hiérarchie complète
GET    /organigramme?type_organigramme_id=1  # Filtrer par type
GET    /organigramme?page=1&limit=10         # Pagination
```

### Assets (Biens)
```
POST   /assets                    # Créer bien (avec upload)
GET    /assets                    # Lister (paginé)
GET    /assets/{id}               # Détail bien
PATCH  /assets/{id}               # Modifier bien
DELETE /assets/{id}/soft-delete   # Supprimer bien
```

**Filtres GET /assets**:
```
?page=1&limit=10
&search=ordinateur              # Recherche nom/référence
&category_id=2                  # Filtrer par catégorie
&asset_type_id=5                # Filtrer par type
&service_id=16                  # Filtrer par service
&is_delete=false                # Inclure suppressions logiques
```

### Categories
```
GET    /categories                # Lister
POST   /categories                # Créer
GET    /categories/{id}           # Détail
PATCH  /categories/{id}           # Modifier
DELETE /categories/{id}           # Supprimer (et réaffecter)
```

### Asset Types
```
GET    /asset-types               # Lister
POST   /asset-types               # Créer
GET    /asset-types/{id}          # Détail
PATCH  /asset-types/{id}          # Modifier
DELETE /asset-types/{id}          # Supprimer (et réaffecter)
```

---

## 📝 Format des Réponses

### Succès (200, 201, etc.)
```json
{
  "success": true,
  "status": 200,
  "message": "Bien retourné avec succès.",
  "data": {
    "id": 1,
    "nom": "Ordinateur"
  }
}
```

### Erreur (400, 401, 404, etc.)
```json
{
  "success": false,
  "status": 404,
  "message": "Le bien demandé est introuvable.",
  "data": null
}
```

### Erreur de Validation
```json
{
  "success": false,
  "status": 400,
  "message": "La validation a échoué.",
  "data": {
    "email": "L'email n'est pas valide.",
    "valeur": "La valeur doit être un nombre positif."
  }
}
```

### Pagination
```json
{
  "success": true,
  "status": 200,
  "message": "Biens retournés avec succès.",
  "data": {
    "meta": {
      "current_page": 1,
      "limit": 10,
      "total_items": 42,
      "total_pages": 5
    },
    "data": [...]
  }
}
```

---

## 🧪 Exemples de Requêtes

### 1. S'Authentifier
```bash
curl -X POST http://localhost:8000/login_check \
  -H "Content-Type: application/json" \
  -d '{"email":"jean@example.com","password":"password123"}'
```

### 2. Vérifier OTP
```bash
curl -X POST http://localhost:8000/auth/verify-otp \
  -H "Content-Type: application/json" \
  -d '{"email":"jean@example.com","otp":"583921"}'
```

### 3. Récupérer les Utilisateurs
```bash
curl -X GET "http://localhost:8000/users?page=1&limit=10" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### 4. Créer un Bien
```bash
curl -X POST http://localhost:8000/assets \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -F "nom=Ordinateur Portable" \
  -F "category_id=2" \
  -F "asset_type_id=5" \
  -F "valeur=850000" \
  -F "photos[0]=@photo1.jpg" \
  -F "piecesJointes[0]=@facture.pdf" \
  -F "piecesJointesNoms[0]=Facture d'achat"
```

### 5. Modifier un Bien
```bash
curl -X PATCH http://localhost:8000/assets/1 \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"nom":"Ordinateur HP ProBook Modifié","valeur":950000}'
```

### 6. Obtenir l'Organigramme
```bash
curl -X GET "http://localhost:8000/organigramme?type_organigramme_id=1" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

---

## 🔧 Maintenance & Administration

### Commandes Utiles
```bash
# Nettoyer le cache
php bin/console cache:clear

# Vérifier l'état des migrations
php bin/console doctrine:migrations:status

# Exécuter les migrations
php bin/console doctrine:migrations:migrate

# Générer les clés JWT
php bin/console lexik:jwt:generate-keypair

# Exécuter les tests
php bin/phpunit tests/
php test_2fa_implementation.php
```

### Logs
```
var/log/dev.log          # Logs de développement
var/log/prod.log         # Logs de production
```

Activer le logging avancé dans config/services.yaml pour les opérations critiques.

---

## 🐛 Débogage

### Problème: "Circular reference detected"
**Cause**: Sérialisation récursive des entités  
**Solution**: Utiliser les Normalizers personnalisés (UserProfileNormalizer, ServiceFlatNormalizer)

Voir `src/Serializer/` pour les patterns.

### Problème: "Relation introuvable" lors de création de bien
**Cause**: ID de catégorie/type invalide  
**Solution**: Vérifier que category_id, asset_type_id, service_id existent et sont actifs

### Problème: Photos non uploadées
**Cause**: Permissions de dossier ou limite de taille  
**Solution**: 
```bash
chmod -R 777 public/uploads
# Vérifier upload_max_filesize et post_max_size dans php.ini
```

---

## 🔒 Bonnes Pratiques de Sécurité

1. **Jamais commiter de secrets** dans le repo
   - Utiliser `.env.local` (non suivi par git)
   - Utiliser les variables d'environnement en prod

2. **Valider TOUJOURS les inputs**
   - Les Normalizers valident automatiquement
   - Ajouter des validations personnalisées si nécessaire

3. **Utiliser HTTPS en production**
   - Les tokens JWT doivent voyager en HTTPS seulement

4. **Limiter les uploads**
   - Valider le type MIME
   - Limiter la taille (config dans config/packages/framework.yaml)

5. **Soft-delete plutôt que vraie suppression**
   - Permet l'audit trail et la récupération
   - Tous les deletes utilisent is_delete flag

---

## 📚 Documentation Complémentaire

- [IMPLEMENTATION_2FA_DOCUMENTATION.md](IMPLEMENTATION_2FA_DOCUMENTATION.md) - Détails 2FA
- [ANALYSE_CORRECTION_API_ASSETS.md](ANALYSE_CORRECTION_API_ASSETS.md) - Correction PATCH /assets
- [SUIVI_EVOLUTION_PROJET.md](SUIVI_EVOLUTION_PROJET.md) - Évolution globale du projet
- [EMAIL_CONFIGURATION.md](EMAIL_CONFIGURATION.md) - Configuration mailer
- [API Swagger](http://localhost:8000/) - Documentation interactive

---

## 🤝 Contributing

Avant de modifier le code:

1. **Lire cette documentation**
2. **Vérifier les conventions** (noms de contrôleurs, services, etc.)
3. **Ajouter des tests** pour les nouvelles fonctionnalités
4. **Documenter les changements** dans SUIVI_EVOLUTION_PROJET.md
5. **Nettoyer le cache** avant de tester

### Convention de Nommage
- **Controllers**: `FeaturesController.php` ( SingleResponsibilityPrinciple)
- **Services**: `FeatureService.php` ou `FeatureBuilder.php`
- **Repositories**: `FeatureRepository.php`
- **Entities**: `Feature.php` (CamelCase)

---

## ❓ FAQ

**Q: Peut-on supprimer une catégorie avec des biens?**  
R: Oui, mais les biens seront automatiquement réaffectés à "Non catégorisé" (enregistrement système par défaut).

**Q: Puis-je modifier un bien supprimé (is_delete=true)?**  
R: Non, une erreur 409 sera retournée. Il faudrait d'abord le restaurer.

**Q: Combien de temps dure un OTP?**  
R: 5 minutes. Passé ce délai, un nouveau OTP doit être demandé.

**Q: Les photos sont-elles sauvegardées en BD?**  
R: Non, en dossier public/uploads. La BD garde les chemins et métadonnées.

**Q: Comment gérer le multi-tenant?**  
R: Actuellement, tous les utilisateurs partagent les mêmes données. Un système d'isolation per-organization peut être ajouté via Voters.

---

## 📞 Support

Pour les questions ou problèmes:
1. Consulter le fichier de documentation pertinent
2. Vérifier les logs dans var/log/
3. Tester avec Swagger UI (http://localhost:8000/)
4. Contacter le responsable du projet

---

**Dernière mise à jour**: 2 août 2026  
**Prochaine révision**: 9 août 2026 (après sprint 3)
