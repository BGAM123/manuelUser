<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception UserDeleteException
 * 
 * Exception personnalisée pour indiquer qu'une tentative de connection d'un utilisateur a échoué
 * en raison de mauvaises informations d'identification.
 */
class UserDeleteException extends HttpException
{
    /**
     * Constructeur
     *
     * Initialise l'exception avec le code HTTP 401 et un message spécifique
     * indiquant que les informations d'identification sont invalides.
     */
    public function __construct()
    {
        // Appel au constructeur de la classe parent (HttpException)
        // avec le code HTTP 401 (Unauthorized) et le message d'erreur.
        parent::__construct(401, 'Invalid credentials.');
    }
}
