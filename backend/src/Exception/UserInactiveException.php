<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception UserInactiveException
 * 
 * Exception personnalisée pour indiquer qu'un compte utilisateur a été désactivé.
 */
class UserInactiveException extends HttpException
{
    /**
     * Constructeur
     *
     * Initialise l'exception avec le code HTTP 403 et un message spécifique
     * indiquant que le compte de l'utilisateur a été désactivé.
     */
    public function __construct()
    {
        // Appel au constructeur de la classe parent (HttpException)
        // avec le code HTTP 403 (Forbidden) et le message d'erreur.
        parent::__construct(403, 'Votre compte a été désactivé. Vous ne pouvez pas vous connecter. Veuillez contacter l\'administrateur.');
    }
}
