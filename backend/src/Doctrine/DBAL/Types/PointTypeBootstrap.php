<?php

namespace App\Doctrine\DBAL\Types;

use Doctrine\DBAL\Types\Type;

class PointTypeBootstrap
{
    public static function register(): void
    {
        if (!Type::hasType('point')) {
            Type::addType('point', PointType::class);
        }
    }
}
