<?php
namespace App\Serializer;

class CircularReferenceHandler
{
    public static function handle($object)
    {
        // Retourner l'ID de l'objet pour éviter la sérialisation complète
        return $object->getId();
    }
}
