<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception EntityNotFoundException
 * 
 * Exception personnalisée pour indiquer que l'entité est introuvable.
 */
class EntityNotFoundException extends HttpException
{
    /**
     * Constructeur
     *
     * Initialise l'exception avec le code HTTP 500 et un message spécifique
     * indiquant que l'entité est introuvable.
     */
    public function __construct()
    {
        // Appel au constructeur de la classe parent (HttpException)
        // avec le code HTTP 500 (Exception Server) et le message d'erreur.
        parent::__construct('500', 'Entity not found.');
    }
}
