<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception UserNotVerifiedException
 * 
 * Exception personnalisée pour indiquer qu'un compte utilisateur n'a pas été vérifié.
 */
class UserNotVerifiedException extends HttpException
{
    /**
     * Constructeur
     *
     * Initialise l'exception avec le code HTTP 403 et un message spécifique
     * indiquant que le compte de l'utilisateur n'a pas encore été vérifié.
     */
    public function __construct()
    {
        // Appel au constructeur de la classe parent (HttpException)
        // avec le code HTTP 403 (Forbidden) et le message d'erreur.
        parent::__construct(403, 'Account not verified.');
    }
}
