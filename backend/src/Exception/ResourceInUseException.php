<?php

namespace App\Exception;

/**
 * Suppression définitive impossible car la ressource est encore référencée
 * par une contrainte de clé étrangère — toujours mappée en HTTP 400 JSON.
 */
final class ResourceInUseException extends \RuntimeException
{
}
