<?php

namespace App\Service\Core;

use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class SendEmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private ParameterBagInterface $parameterBag
    ) {
    }

    /**
     * Envoie un email à un ou plusieurs utilisateurs donnés, avec optionnellement des destinataires en Cc et Cci.
     *
     * @param string $sender L'utilisateur expéditeur (adresse email)
     * @param array $recipients Les utilisateurs destinataires (tableau d'adresses emails)
     * @param string $subject Le sujet de l'email
     * @param string $body Le contenu de l'email en HTML
     * @param array|null $cc (optionnel) Les destinataires en copie (Cc)
     * @param array|null $bcc (optionnel) Les destinataires en copie cachée (Cci)
     */
    public function sendEmail(string $sender, array $recipients, string $subject, string $body, ?array $cc = null, ?array $bcc = null): void
    {
        $email = (new Email())
            ->from(new Address(
                $sender, // Adresse de l'expéditeur
                $this->parameterBag->get('app_name') // Nom de l'expéditeur
            ))
            ->subject($subject)  // Sujet de l'email
            ->html($body);  // Contenu en HTML
        ;

        // Ajouter les destinataires principaux
        foreach ($recipients as $recipient) {
            $email->addTo($recipient);  // Ajouter chaque destinataire
        }

        // Ajouter les destinataires en copie (Cc) si fournis
        if ($cc) {
            foreach ($cc as $ccRecipient) {
                $email->addCc($ccRecipient);  // Ajouter chaque destinataire en Cc
            }
        }

        // Ajouter les destinataires en copie cachée (Cci) si fournis
        if ($bcc) {
            foreach ($bcc as $bccRecipient) {
                $email->addBcc($bccRecipient);  // Ajouter chaque destinataire en Cci
            }
        }

        $this->mailer->send($email);  // Envoi de l'email
    }
}
