<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Service de gestion des codes OTP pour la double authentification.
 * Responsable de:
 * - Génération de codes OTP aléatoires
 * - Sauvegarde et gestion de l'expiration
 * - Envoi de codes par email
 * - Validation de codes
 */
final class OtpService
{
    private const OTP_LENGTH = 6;
    private const OTP_VALIDITY_MINUTES = 5;

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly string $mailFromAddress,
        private readonly string $mailFromName
    ) {
    }

    /**
     * Génère un code OTP à 6 chiffres, le stocke et l'envoie par email.
     *
     * @return string Le code OTP généré (pour les tests)
     */
    public function generateAndSendOtp(User $user): string
    {
        // Générer un code OTP aléatoire à 6 chiffres
        $otp = str_pad(
            (string) random_int(0, 999999),
            self::OTP_LENGTH,
            '0',
            STR_PAD_LEFT
        );

        // Calculer l'expiration (5 minutes à partir de maintenant)
        $expiresAt = new \DateTime('+' . self::OTP_VALIDITY_MINUTES . ' minutes');

        // Sauvegarder le code et l'expiration
        $user->setOtpCode($otp);
        $user->setOtpExpiresAt($expiresAt);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Envoyer le code par email
        $this->sendOtpEmail($user, $otp);

        return $otp;
    }

    /**
     * Valide le code OTP fourni pour un utilisateur.
     *
     * @return true si le code est valide et non expiré
     */
    public function validateOtp(User $user, string $providedOtp): bool
    {
        $storedOtp = $user->getOtpCode();
        $expiresAt = $user->getOtpExpiresAt();

        // Vérifier que le code existe
        if (!$storedOtp) {
            return false;
        }

        // Vérifier que le code n'a pas expiré
        if (!$expiresAt || $expiresAt < new \DateTime()) {
            return false;
        }

        // Vérifier que le code correspond
        return hash_equals($storedOtp, $providedOtp);
    }

    /**
     * Supprime le code OTP après validation réussie.
     */
    public function clearOtp(User $user): void
    {
        $user->setOtpCode(null);
        $user->setOtpExpiresAt(null);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    /**
     * Envoie un email contenant le code OTP.
     */
    private function sendOtpEmail(User $user, string $otp): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFromAddress, $this->mailFromName))
            ->to($user->getEmail())
            ->subject('Votre code de vérification à 2FA')
            ->htmlTemplate('email/otp_code.html.twig')
            ->context([
                'firstName' => $user->getFirstName(),
                'otp' => $otp,
                'validityMinutes' => self::OTP_VALIDITY_MINUTES,
            ]);

        $this->mailer->send($email);
    }
}
