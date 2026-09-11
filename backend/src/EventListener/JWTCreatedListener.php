<?php

namespace App\EventListener;

use App\Entity\Core\User;
use App\Exception\UserDeleteException;
use App\Exception\UserNotVerifiedException;
use App\Exception\UserInactiveException;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;

/**
 * EventListener JWTCreatedListener
 * 
 * Écouteur d'événements pour la création d'un JWT (JSON Web Token).
 * Cette classe écoute l'événement de création de JWT et effectue des vérifications sur l'utilisateur
 * avant de permettre la création du token.
 */
class JWTCreatedListener
{
    /**
     * Méthode appelée lors de la création d'un JWT.
     * Elle vérifie l'état de l'utilisateur (supprimé, non vérifié ou inactif) avant de permettre la création du JWT.
     *
     * @param JWTCreatedEvent $event L'événement de création du JWT.
     */
    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        // Récupère l'utilisateur associé à l'événement
        $user = $event->getUser();
        
        // Vérifie si l'utilisateur est une instance de UserInterface (ce qui devrait être le cas)
        if (!$user instanceof User) {
            return; // Si ce n'est pas le cas, on arrête l'exécution de la méthode.
        }

        // Vérifie si l'utilisateur est marqué comme supprimé (isDelete est true)
        if ($user->isDelete()) {
            // Si l'utilisateur est supprimé, une exception UserDeleteException est levée.
            throw new UserDeleteException();
        }

        // Vérifie si l'utilisateur n'est pas vérifié (isVerified est false)
        if (!$user->isVerified()) {
            // Si l'utilisateur n'est pas vérifié, une exception UserNotVerifiedException est levée.
            throw new UserNotVerifiedException();
        }

        // 🆕 Vérifie si l'utilisateur est inactif (isActive est false)
        if (!$user->isActive()) {
            // Si l'utilisateur est inactif, une exception UserInactiveException est levée.
            throw new UserInactiveException();
        }
    }
}
