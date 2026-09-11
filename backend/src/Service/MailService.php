<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class MailService
{
    private MailerInterface $mailer;
    private string $senderEmail;

    public function __construct(MailerInterface $mailer, ParameterBagInterface $params)
    {
        $this->mailer = $mailer;
        $this->senderEmail = $params->get('app.mailer_sender'); // Chargé depuis .env
    }

    //envoie des mail à un seul et unique destinataire
    public function sendEmail(string $to, string $subject, string $htmlContent): void
    {
        $email = (new Email())
            ->from(new Address($this->senderEmail, 'MINEPIA'))
            ->to($to)
            ->subject($subject)
            ->html($htmlContent);

        $this->mailer->send($email);
    }

    //envoie des mail avec logo embarqué
    public function sendEmailWithLogo(string $to, string $subject, string $htmlContent, string $logoPath): void
    {
        $email = (new Email())
            ->from(new Address($this->senderEmail, 'MINEPIA'))
            ->to($to)
            ->subject($subject)
            ->html($htmlContent)
            ->embedFromPath($logoPath, 'logo');

        $this->mailer->send($email);
    }



    //envoie en masse des mails
    public function sendBulkEmail(array $recipients, string $subject, string $htmlContent): void
{
    foreach ($recipients as $email) {
        $emailMessage = (new Email())
            ->from(new Address($this->senderEmail, 'MINEPIA'))
            ->to($email)
            ->subject($subject)
            ->html($htmlContent);

        $this->mailer->send($emailMessage);
    }
}
}
