<?php

namespace App\DataFixtures;

use App\Entity\Champ;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ChampFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $now = new \DateTimeImmutable();
        $definitions = [
            ['nom' => 'Couleur', 'typeChamp' => 'texte', 'valeur' => 'Blanc'],
            ['nom' => 'Puissance', 'typeChamp' => 'nombre', 'valeur' => '150'],
            ['nom' => 'Année de fabrication', 'typeChamp' => 'date', 'valeur' => '2025'],
            ['nom' => 'Immatriculation', 'typeChamp' => 'texte', 'valeur' => null],
        ];

        foreach ($definitions as $definition) {
            $champ = new Champ();
            $champ->setNom($definition['nom']);
            $champ->setTypeChamp($definition['typeChamp']);
            $champ->setValeur($definition['valeur']);
            $champ->setCreatedAt($now);
            $champ->setUpdatedAt($now);
            $manager->persist($champ);
        }

        $manager->flush();
    }
}
