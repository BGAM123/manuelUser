# 2FA Implementation - Quick Reference Guide

## 🎯 Architecture Globale

```
┌─────────────────────────────────────────────────────────────┐
│                    APPLICATION FLOW                         │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  User avec 2FA DISABLED (défaut)                             │
│  ════════════════════════════════════════════════════════    │
│                                                               │
│    POST /login_check                                         │
│    {email, password}                                         │
│            ↓                                                  │
│    ┌────────────────────────────────────┐                    │
│    │ AuthenticationSuccessHandler       │                    │
│    │ isTwoFactorEnabled() == false      │                    │
│    └────────────┬───────────────────────┘                    │
│                 ↓                                             │
│    ┌────────────────────────────────────┐                    │
│    │ Génère JWT Token                   │                    │
│    │ Retourne: {token, refresh_token}   │                    │
│    └────────────────────────────────────┘                    │
│                 ↓                                             │
│    GET /profile                                              │
│    GET /users                                                │
│    ... (avec Authorization: Bearer TOKEN)                    │
│                                                               │
│───────────────────────────────────────────────────────────────│
│                                                               │
│  User avec 2FA ENABLED                                       │
│  ═════════════════════════════════════════════════════════   │
│                                                               │
│    POST /login_check                                         │
│    {email, password}                                         │
│            ↓                                                  │
│    ┌────────────────────────────────────┐                    │
│    │ AuthenticationSuccessHandler       │                    │
│    │ isTwoFactorEnabled() == true       │                    │
│    └────────────┬───────────────────────┘                    │
│                 ↓                                             │
│    ┌────────────────────────────────────┐                    │
│    │ OtpService.generateAndSendOtp()    │                    │
│    │ • Génère OTP (6 chiffres)          │                    │
│    │ • Sauvegarde OTP en DB             │                    │
│    │ • Envoie email                     │                    │
│    └────────────┬───────────────────────┘                    │
│                 ↓                                             │
│    Retourne: {requires_otp: true}                            │
│                 ↓                                             │
│    [User reçoit email avec OTP]                              │
│                 ↓                                             │
│    POST /auth/verify-otp                                     │
│    {email, otp}                                              │
│            ↓                                                  │
│    ┌────────────────────────────────────┐                    │
│    │ VerifyOtpController                │                    │
│    │ • Valide OTP avec hash_equals      │                    │
│    │ • Vérifie pas expiré (5 min)       │                    │
│    │ • Supprime OTP de DB               │                    │
│    │ • Génère JWT Token                 │                    │
│    └────────────┬───────────────────────┘                    │
│                 ↓                                             │
│    Retourne: {token, refresh_token}                          │
│                 ↓                                             │
│    GET /profile                                              │
│    ... (avec Authorization: Bearer TOKEN)                    │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

## 📊 Structure Base de Données

```
TABLE: user
═════════════════════════════════════════════════════════════
│ Colonne               │ Type      │ Valeur défaut │ Nullable │
├──────────────────────┼───────────┼───────────────┼──────────┤
│ id                   │ INT       │ -             │ Non      │
│ email                │ VARCHAR   │ -             │ Non      │
│ password             │ VARCHAR   │ -             │ Non      │
│ first_name           │ VARCHAR   │ -             │ Non      │
│ last_name            │ VARCHAR   │ -             │ Non      │
│ ...                  │ ...       │ ...           │ ...      │
├──────────────────────┼───────────┼───────────────┼──────────┤
│ two_factor_enabled   │ TINYINT   │ 0 (false)     │ Non    ← │ NOUVEAU
│ otp_code             │ VARCHAR   │ NULL          │ Oui     ← │ NOUVEAU
│ otp_expires_at       │ DATETIME  │ NULL          │ Oui     ← │ NOUVEAU
│ ...                  │ ...       │ ...           │ ...      │
│ is_delete            │ TINYINT   │ 0             │ Non      │
│ created_at           │ DATETIME  │ -             │ Non      │
│ updated_at           │ DATETIME  │ -             │ Non      │
└──────────────────────┴───────────┴───────────────┴──────────┘
```

## 🔑 Clés Techniques

### OtpService
```
┌─────────────────────────────────────────┐
│         OtpService                      │
├─────────────────────────────────────────┤
│                                         │
│  generateAndSendOtp(User)               │
│  ├─ random_int(0, 999999)               │
│  ├─ pad to 6 digits                     │
│  ├─ expiresAt = now + 5 min             │
│  ├─ save otp_code + otp_expires_at DB   │
│  └─ send TemplatedEmail                 │
│                                         │
│  validateOtp(User, string)              │
│  ├─ hash_equals(provided, stored)       │
│  ├─ check not expired                   │
│  └─ return bool                         │
│                                         │
│  clearOtp(User)                         │
│  ├─ set otp_code = null                 │
│  ├─ set otp_expires_at = null           │
│  └─ flush DB                            │
│                                         │
└─────────────────────────────────────────┘
```

## 🔄 Endpoints Summary

```
AUTHENTIFICATION
═════════════════════════════════════════════════════════════

POST /login_check
├─ Input:  {email, password}
├─ 2FA OFF: {token, refresh_token} → JWT direct
└─ 2FA ON:  {requires_otp: true}   → Attend OTP

POST /auth/verify-otp ← NOUVEAU
├─ Input:  {email, otp}
├─ 200:    {token, refresh_token} → OTP valide
└─ 400:    {message: "..."}        → OTP invalide/expiré


UTILISATEURS - 2FA
═════════════════════════════════════════════════════════════

PATCH /users/{id}/two-factor ← NOUVEAU
├─ Input:  {twoFactorEnabled: bool}
├─ 200:    {data: {id, ..., twoFactorEnabled}}
└─ 400:    Type validation error

PATCH /users/{id}
├─ Input:  {twoFactorEnabled: bool, ...}
├─ 200:    {data: {id, ..., twoFactorEnabled}}
└─ Peut inclure twoFactorEnabled


UTILISATEURS - LECTURE
═════════════════════════════════════════════════════════════

GET /profile
└─ 200: {data: {id, ..., twoFactorEnabled}}

GET /users
└─ 200: {data: {data: [{id, ..., twoFactorEnabled}, ...]}}

GET /users/{id}
└─ 200: {data: {id, ..., twoFactorEnabled}}

POST /users
├─ Input: {firstName, lastName, email, password, twoFactorEnabled?}
└─ 201: {data: {id, ..., twoFactorEnabled}}
```

## 📧 Email OTP Template

```
┌────────────────────────────────────────────────────┐
│                                                    │
│  Bonjour [FirstName],                              │
│                                                    │
│  Your One-Time Password:                           │
│  ┌──────────────────────┐                          │
│  │   6 DIGIT CODE       │                          │
│  │    (monospace)       │                          │
│  └──────────────────────┘                          │
│                                                    │
│  Valable pour: 5 minutes                           │
│  Expirera à: [timestamp]                           │
│                                                    │
│  ⚠️  SÉCURITÉ:                                     │
│  • Ne partagez jamais votre code OTP              │
│  • Aucun support ne demandera votre OTP           │
│  • Ne répondez pas à ce-mail                      │
│                                                    │
│  Auto-generated message                            │
│  © Library patnuc                                  │
│                                                    │
└────────────────────────────────────────────────────┘
```

## 🛡️ Sécurité

```
IMPLÉMENTÉ ✅
════════════════════════════════════════════════════════════

✓ OTP aléatoire (random_int)
✓ OTP aléatoire unique (pas de patterns)
✓ OTP expiration 5 minutes
✓ Comparaison timing-safe (hash_equals)
✓ OTP supprimé après utilisation
✓ OTP supprimé lors désactivation 2FA
✓ No hardcoded credentials (.env)
✓ Email validation côté serveur
✓ Password hashing (UserPasswordHasherInterface)

À CONSIDÉRER (optionnel) ⚠️
════════════════════════════════════════════════════════════

□ Rate limiting sur /auth/verify-otp
□ Rate limiting sur /login_check (2FA users)
□ Audit logging de tentatives OTP
□ Alertes email tentatives multiples échouées
□ Cache Redis pour OTP codes
□ TOTP comme alternative à email OTP
□ Backup codes pour account recovery
```

## 🧪 Scénarios de Test

```
SCÉNARIO 1: Utilisateur sans 2FA
════════════════════════════════════════════════════════════

$ curl -X POST /login_check -d '{"email":"...", "password":"..."}'
→ Response: {"data": {"token": "eyJ...", "refresh_token": "..."}}

[User peut utiliser le token immédiatement]
✓ PASS: Comportement existant inchangé


SCÉNARIO 2: Activer 2FA et login
════════════════════════════════════════════════════════════

$ curl -X PATCH /users/1/two-factor -d '{"twoFactorEnabled": true}'
→ Response: {"data": {"twoFactorEnabled": true}}

$ curl -X POST /login_check -d '{"email":"...", "password":"..."}'
→ Response: {"data": {"requires_otp": true}}

[Email reçu avec OTP dans inbox]

$ curl -X POST /auth/verify-otp -d '{"email":"...", "otp":"123456"}'
→ Response: {"data": {"token": "eyJ...", "refresh_token": "..."}}

[User peut utiliser le token]
✓ PASS: 2FA flow complet


SCÉNARIO 3: OTP invalide
════════════════════════════════════════════════════════════

$ curl -X POST /auth/verify-otp -d '{"email":"...", "otp":"999999"}'
→ Response: {"code": 400, "message": "Code OTP invalide"}

[OTP remains in DB for retry within 5 min]
✓ PASS: Validation d'erreur


SCÉNARIO 4: OTP expiré
════════════════════════════════════════════════════════════

[Attendre > 5 minutes]

$ curl -X POST /auth/verify-otp -d '{"email":"...", "otp":"123456"}'
→ Response: {"code": 400, "message": "Code OTP expiré"}

[User doit re-login pour nouveau OTP]
✓ PASS: Expiration validation


SCÉNARIO 5: Compatibilité rétroactive
════════════════════════════════════════════════════════════

[Utilisateurs créés avant implémentation 2FA]

SELECT two_factor_enabled FROM user WHERE id = 1;
→ 0 (false) ← Défaut appliqué

$ curl -X POST /login_check -d '{"email":"...", "password":"..."}'
→ Response: {"data": {"token": "eyJ...", "refresh_token": "..."}}

[Flow inchangé, JWT direct]
✓ PASS: Aucun breaking change
```

## 📦 Dépendances

```
NOUVELLES
════════════════════════════════════════════════════════════

symfony/mailer (v7.4.15)
├─ symfony/mime (v7.4.15)
├─ egulias/email-validator (4.0.4)
└─ symfony/polyfill-intl-idn (v1.38.1)

EXISTANTES (Utilisées)
════════════════════════════════════════════════════════════

symfony/framework-bundle (v6.4)
symfony/security-bundle (v6.4)
doctrine/orm (v2.x)
symfony/serializer (v6.4)
symfony/validator (v6.4)
lexik/jwt-authentication-bundle
```

## 🚀 Déploiement Rapide

```
1. CONFIGURATION EMAIL (CRITIQUE)
   ══════════════════════════════════════════════════════════
   
   Éditer .env:
   ─────────────
   MAILER_DSN=smtp://email@gmail.com:app-password@smtp.gmail.com:587?...
   MAIL_FROM_ADDRESS=email@gmail.com
   MAIL_FROM_NAME="Library patnuc"
   
   (Puis créer .env.local pour prod avec vrais credentials)

2. VÉRIFIER INSTALLATION
   ══════════════════════════════════════════════════════════
   
   php bin/console cache:clear
   php bin/console doctrine:migrations:status
   php bin/console debug:router | grep -E "verify-otp|two-factor"

3. TESTER EMAIL
   ══════════════════════════════════════════════════════════
   
   php bin/console mailer:test your-email@example.com

4. TESTER ENDPOINTS
   ══════════════════════════════════════════════════════════
   
   bash test_2fa_curl.sh
   php test_2fa_implementation.php

5. VÉRIFIER SWAGGER
   ══════════════════════════════════════════════════════════
   
   Accéder: http://localhost:8000/doc
   Chercher: /auth/verify-otp, /users/{id}/two-factor
```

## 📚 Documentation Complète

```
Pour chaque aspect, consulter:

ASPECT                          FICHIER
════════════════════════════════════════════════════════════

Architecture complète           IMPLEMENTATION_2FA_DOCUMENTATION.md
Configuration email             EMAIL_CONFIGURATION.md
Checklist avant production      CHECKLIST_2FA.md
Tests automatisés (PHP)         test_2fa_implementation.php
Tests automatisés (curl)        test_2fa_curl.sh
Résumé exécutif                 RESUME_2FA_IMPLEMENTATION.md
Liste fichiers créés/modifiés   FILES_CREATED_MODIFIED.md
Quick reference (ce fichier)    QUICK_REFERENCE.md
```

## ❓ FAQ Rapide

```
Q: Comment tester sans vraie configuration email?
A: Utiliser MailHog:
   MAILER_DSN=smtp://127.0.0.1:1025
   Web UI: http://localhost:8025

Q: Puis-je changer durée expiration OTP?
A: Oui, dans src/Service/OtpService.php, ligne 20:
   private const OTP_VALIDITY_MINUTES = 5;

Q: Les users existants sont affectés?
A: Non! Par défaut twoFactorEnabled = false
   Login fonctionne exactement comme avant

Q: Quel format pour OTP?
A: 6 chiffres: 000000 à 999999
   Aléatoire via random_int()

Q: Email non reçu?
A: Vérifier:
   1. MAILER_DSN correctement configurée
   2. Dossier Spam
   3. var/log/dev.log pour erreurs mailer

Q: Comment désactiver 2FA?
A: PATCH /users/{id}/two-factor {"twoFactorEnabled": false}
   OTP codes sont nettoyés automatiquement
```

---

**Version**: 1.0
**Date**: 29 Juillet 2026
**Status**: ✅ Production Ready

Pour questions détaillées, voir documentation complète.
