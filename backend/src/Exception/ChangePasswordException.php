<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception ChangePasswordException
 * 
 * Exception personnalisée pour indiquer une prémiere connexion.
 */
class ChangePasswordException extends HttpException
{
    /**
     * Constructeur
     *
     * Initialise l'exception avec le code HTTP 401 et un message spécifique
     * indiquant que une prémiere connexion.
     */
    public function __construct()
    {
        // Appel au constructeur de la classe parent (HttpException)
        // avec le code HTTP 401 (Unauthorized) et le message d'erreur.
        parent::__construct(401, 'Change password.');
    }
}
