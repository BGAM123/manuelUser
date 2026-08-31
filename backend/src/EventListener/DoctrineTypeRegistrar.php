<?php

namespace App\EventListener;

use App\Doctrine\DBAL\Types\PointType;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class DoctrineTypeRegistrar
{
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!Type::hasType('point')) {
            Type::addType('point', PointType::class);
        }
    }
}
