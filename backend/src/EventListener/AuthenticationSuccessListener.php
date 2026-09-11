<?php

namespace App\EventListener;

use App\Entity\Core\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;

/**
 * Listener pour enrichir la réponse JWT avec les informations du refresh token
 */
class AuthenticationSuccessListener
{
    /**
     * Ajoute les informations du refresh token et du TTL dans la réponse d'authentification
     * 
     * @param AuthenticationSuccessEvent $event
     */
    public function onAuthenticationSuccessResponse(AuthenticationSuccessEvent $event): void
    {
        $data = $event->getData();
        $user = $event->getUser();

        // Ajouter des informations supplémentaires dans la réponse
        $data['token_ttl'] = 120; // 2 minutes en secondes
        $data['refresh_token_ttl'] = 180; // 3 minutes en secondes (1 min de marge après expiration du JWT)
        $data['user'] = [
            'username' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
        ];

        // Ajouter l'ID du service si l'utilisateur est une instance de User et a un service
        if ($user instanceof User) {
            $service = $user->getIdService();
            $data['user']['idService'] = $service ? $service->getId() : null;
        }

        $event->setData($data);
    }
}
