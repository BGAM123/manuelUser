#!/bin/bash
# Script de configuration pour mailer - à exécuter APRÈS modification .env

echo "================================"
echo "Configuration Mailer pour 2FA"
echo "================================"
echo ""

# Afficher la configuration actuelle
echo "1️⃣  Vérification configuration actuelle:"
echo ""

if grep -q "^MAILER_DSN=" .env; then
    echo "✓ MAILER_DSN trouvé dans .env"
    MAILER_DSN=$(grep "^MAILER_DSN=" .env | cut -d'=' -f2)
    if [[ "$MAILER_DSN" == *"smtp"* ]]; then
        echo "  → Configuration semble valide (contient 'smtp')"
    else
        echo "  ⚠️  Configuration suspecte (ne contient pas 'smtp')"
    fi
else
    echo "❌ MAILER_DSN non trouvé dans .env"
    echo "   → À configurer (voir instructions ci-dessous)"
fi

echo ""

if grep -q "^MAIL_FROM_ADDRESS=" .env; then
    MAIL_FROM=$(grep "^MAIL_FROM_ADDRESS=" .env | cut -d'=' -f2)
    echo "✓ MAIL_FROM_ADDRESS = $MAIL_FROM"
else
    echo "❌ MAIL_FROM_ADDRESS non trouvé dans .env"
fi

echo ""

if grep -q "^MAIL_FROM_NAME=" .env; then
    MAIL_FROM_NAME=$(grep "^MAIL_FROM_NAME=" .env | cut -d'=' -f2)
    echo "✓ MAIL_FROM_NAME = $MAIL_FROM_NAME"
else
    echo "❌ MAIL_FROM_NAME non trouvé dans .env"
fi

echo ""
echo "2️⃣  Configuration requise:"
echo ""
echo "Ajouter ou modifier dans .env:"
echo ""
echo "# Gmail (recommandé pour développement)"
echo "MAILER_DSN=smtp://your-email@gmail.com:app-password@smtp.gmail.com:587?encryption=tls&auth_mode=login"
echo "MAIL_FROM_ADDRESS=your-email@gmail.com"
echo "MAIL_FROM_NAME=\"Library patnuc\""
echo ""
echo "3️⃣  Pour Gmail (App Password):"
echo ""
echo "  1. Aller sur: https://myaccount.google.com/apppasswords"
echo "  2. Sélectionner: Mail + Windows/Mac/Linux"
echo "  3. Copier le password généré"
echo "  4. Remplacer 'app-password' dans MAILER_DSN par ce password"
echo ""
echo "Exemple complet:"
echo "MAILER_DSN=smtp://nengue382@gmail.com:abcd1234efgh5678@smtp.gmail.com:587?encryption=tls&auth_mode=login"
echo ""

echo "4️⃣  Tests après configuration:"
echo ""
echo "# Vérifier la configuration"
echo "php bin/console config:dump framework.mailer"
echo ""
echo "# Tester l'envoi (si la commande exists)"
echo "php bin/console mailer:test your-email@example.com"
echo ""
echo "# Alternative: vérifier les logs"
echo "tail -f var/log/dev.log | grep -i mail"
echo ""

echo "5️⃣  Alternative pour développement local (MailHog):"
echo ""
echo "# Installer MailHog"
echo "# Puis configurer:"
echo "MAILER_DSN=smtp://127.0.0.1:1025"
echo ""
echo "# Accéder à l'interface: http://localhost:8025"
echo ""

echo "================================"
echo "Configuration Mailer - Fin"
echo "================================"
