<?php

namespace App;

use App\Doctrine\DBAL\Types\PointTypeBootstrap;
use Doctrine\DBAL\Types\Type;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        parent::boot();
        // Enregistrer le type personnalisé PointType
        PointTypeBootstrap::register();
    }
}
