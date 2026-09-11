<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception InvalidFieldException
 * 
 * Exception personnalisée pour indiquer qu'une entité ne prend pas en charge 
 * la suppression logique (logical delete).
 */
class InvalidFieldException extends HttpException
{
    /**
     * Constructeur
     *
     * Initialise l'exception avec un code HTTP 500 (Exception Server) et un message spécifique
     * indiquant que l'entité ne supporte pas la suppression logique.
     */
    public function __construct()
    {
        // Appel au constructeur de la classe parent (HttpException)
        // avec le code HTTP 500 (Exception Server) et le message d'erreur.
        parent::__construct(500, 'The field or its setter was not found in the entity.');
    }
}
