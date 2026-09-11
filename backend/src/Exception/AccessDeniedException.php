<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception AccessDeniedException
 * 
 * Exception personnalisée pour indiquer que l'access n'est pas autorisee a l'utilisateur connecter.
 */
class AccessDeniedException extends HttpException
{
    /**
     * Constructeur
     *
     * Initialise l'exception avec le code HTTP 403 et un message spécifique
     * indiquant que l'access n'est pas autorisee a l'utilisateur connecter.
     */
    public function __construct()
    {
        // Appel au constructeur de la classe parent (HttpException)
        // avec le code HTTP 403 (AccessDenied) et le message d'erreur.
        parent::__construct(403, 'Access denied.');
    }
}
