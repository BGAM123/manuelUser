<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Ressource métier introuvable — toujours mappée en HTTP 404 JSON.
 */
final class ResourceNotFoundException extends NotFoundHttpException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }

    public static function for(string $resourceLabel): self
    {
        return new self(sprintf('Le %s demandé est introuvable.', $resourceLabel));
    }

    public static function forFeminine(string $resourceLabel): self
    {
        return new self(sprintf('La %s demandée est introuvable.', $resourceLabel));
    }
}
