<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception InvalidArgumentException
 * 
 * Exception personnalisée pour indiquer qu'un argument est invalide.
 */
class InvalidArgumentException extends HttpException
{
    /**
     * Constructeur
     *
     * Initialise l'exception avec le code HTTP 500 et un message spécifique
     * indiquant qu'un argument est invalide.
     */
    public function __construct()
    {
        // Appel au constructeur de la classe parent (HttpException)
        // avec le code HTTP 500 (Exception Server) et le message d'erreur.
        parent::__construct(500, 'Invalid argument.');
    }
}
