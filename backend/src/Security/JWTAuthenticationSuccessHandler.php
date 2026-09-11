<?php

namespace App\Security;

use App\Entity\Core\User;
use Symfony\Component\Security\Core\User\UserInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;

/**
 * Security JWTAuthenticationSuccessHandler
 *
 * Gère le succès de l'authentification via JWT.
 * Cette classe est utilisée pour personnaliser la réponse envoyée au client après une authentification réussie.
 * Généralement, elle renvoie un JWT au client dans les en-têtes ou le corps de la réponse.
 */
class JWTAuthenticationSuccessHandler
{

    
    /**
     * Méthode appelée lorsque l'authentification réussit.
     * Permet de modifier les données renvoyées après une authentification avec succès.
     *
     * @param AuthenticationSuccessEvent $event L'événement déclenché lors de l'authentification réussie.
     */
    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event)
    {
        // Récupère l'utilisateur authentifié depuis l'événement
        $user = $event->getUser();

        // Vérifie si l'utilisateur est une instance de notre entité User
        if (!$user instanceof User) {
            // Si ce n'est pas le cas, on quitte la méthode sans effectuer d'action
            return;
        }

        // Récupère les données actuelles de la réponse, qui contiennent généralement le token JWT
        $data = $event->getData();

        // Ajoute des informations supplémentaires à la réponse
        // Exemple : Ajouter l'ID de l'utilisateur et sa langue (locale)
        // Décommenter si nécessaire pour inclure ces champs
        $data['id'] = $user->getId();            // Ajout de l'ID utilisateur
        $data['username'] = $user->getUsername();            // Ajout de l'username utilisateur
        $data['email'] = $user->getEmail();            // Ajout de l'email utilisateur
        $data['lastName'] = $user->getLastName();            // Ajout du lastName utilisateur
        $data['langue'] = $user->getLangue();            // Ajout de la langue utilisateur
       

        // Met à jour les données de la réponse avec les informations ajoutées
        $event->setData($data);
    }
}
?>