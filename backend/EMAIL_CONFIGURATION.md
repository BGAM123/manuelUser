# Configuration Gmail pour Symfony Mailer

## Configuration Gmail SMTP

Pour utiliser Gmail comme fournisseur d'email:

### 1. Créer un mot de passe d'application Gmail

Gmail ne permet plus les connexions directes au compte principal via SMTP par défaut.
Vous devez créer un "mot de passe d'application":

1. Aller à: https://myaccount.google.com/security
2. Activer "2-Step Verification" si non fait
3. Aller à: https://myaccount.google.com/apppasswords
4. Sélectionner:
   - App: "Mail"
   - Device: "Windows Computer" (ou votre OS)
5. Gmail génère un mot de passe spécial (16 caractères sans espaces)
6. Copier ce mot de passe

### 2. Ajouter au fichier .env

```env
# Mailer Configuration - Gmail
MAILER_DSN=smtp://nengue382@gmail.com:votre_mot_de_passe_app@smtp.gmail.com:587?encryption=tls&auth_mode=login
MAIL_FROM_ADDRESS=nengue382@gmail.com
MAIL_FROM_NAME="Library patnuc"
```

⚠️ **IMPORTANT**: Ne jamais commiter le mot de passe dans Git!
Utiliser `.env.local` pour les secrets:

```bash
# Créer .env.local (ignoré par .gitignore)
echo "MAILER_DSN=smtp://nengue382@gmail.com:PASSWORD@smtp.gmail.com:587?encryption=tls&auth_mode=login" > .env.local
```

### 3. Tester la configuration

```bash
# Envoyer un email de test
php bin/console mailer:test admin@example.com

# Ou via PHP:
php -r "
    \$mailer = \$container->get('mailer');
    \$email = (new Symfony\Component\Mime\Email())
        ->from('nengue382@gmail.com')
        ->to('test@example.com')
        ->subject('Test')
        ->text('OTP Test');
    \$mailer->send(\$email);
"
```

## Configuration Autres Fournisseurs

### Mailgun

```env
MAILER_DSN=mailgun://nengue382@gmail.com:key-xxx@default
```

### SendGrid

```env
MAILER_DSN=sendgrid://SG.xxx@default
```

### Postmark

```env
MAILER_DSN=postmark://SERVER_TOKEN@default
```

### AWS SES

```env
MAILER_DSN=aws+smtp://USERNAME:PASSWORD@email.region.amazonaws.com:587
```

## Configuration SMTP Générique

```env
# Format générique SMTP
MAILER_DSN=smtp://USERNAME:PASSWORD@HOST:PORT?encryption=tls&auth_mode=login

# Options:
# - encryption: tls, ssl
# - auth_mode: login, plain, cram-md5
```

## Dépannage

### Erreur: "SMTP Error: Could not connect"
- Vérifier host et port corrects
- Vérifier firewall/pare-feu bloque port 587 ou 465
- Tester avec: `telnet smtp.gmail.com 587`

### Erreur: "Authentication failed"
- Vérifier username/password corrects
- Pour Gmail: utiliser "mot de passe d'application", pas mot de passe principal
- Vérifier pas d'espaces dans password

### Email non reçu
- Vérifier dossier Spam/Junk
- Vérifier sender_address existe
- Consulter logs mailer: `tail -f var/log/dev.log | grep mailer`

### Développement local sans email réel

Option 1: Mailer Null (supprime les emails)
```env
MAILER_DSN=null://null
```
Emails loggés mais non envoyés.

Option 2: Mailer Test
```yaml
# config/packages/dev/mailer.yaml
framework:
    mailer:
        transports:
            main: 'symfony+test://default'
```
Emails stockés dans `$container->get('mailer.transport')`

Option 3: MailHog (serveur SMTP de développement)
```bash
# Installer MailHog: https://github.com/mailhog/MailHog

# Démarrer MailHog:
MailHog

# Configurer Symfony:
MAILER_DSN=smtp://localhost:1025
```
Interface Web: http://localhost:8025

## Bonnes Pratiques

✓ Stocker les secrets dans `.env.local` (non commité)
✓ Utiliser des variables d'environnement en production
✓ Tester avec MailHog en développement
✓ Implémenter queuing pour emails en production
✓ Ajouter logging des erreurs mailer
✓ Valider adresses email avant envoi

## Ressources

- Symfony Mailer: https://symfony.com/doc/current/mailer.html
- Gmail SMTP: https://support.google.com/mail/answer/7126229
- App Passwords Gmail: https://support.google.com/accounts/answer/185833
