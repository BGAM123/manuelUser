<?php

namespace App\Service\Cour;

use App\Entity\Cour\Courrier;
use App\Entity\Cour\Transmission;
use App\Entity\Core\Service;
use App\Entity\Core\User;
use Doctrine\ORM\EntityManagerInterface;

class CourrierService
{
    public function __construct(private EntityManagerInterface $em) {}

    public function createCourrier(array $data, User $user): Courrier
    {
        $courrier = new Courrier();
        $courrier
            ->setReference($data['reference'] ?? null)
            ->setObjet($data['objet'] ?? null)
            ->setCommentaire($data['commentaire'] ?? null)
            ->setPriorite($data['priorite'] ?? null)
            ->setConfidentiel($data['isConfidentiel'] ?? false)
            ->setDateArrivee(new \DateTime($data['dateArrivee'] ?? 'now'))
            ->setDateEnregistrement(new \DateTime())
            ->setIdProvenance($data['idProvenance'] ?? null)
            ->setIdServiceTraitant($data['idServiceDestinataire'] ?? null)
            ->setIdCreateur($user);

        $this->em->persist($courrier);

        // 🔹 Transmission automatique
        $transmission = new Transmission();
        $transmission
            ->setIdCourrier($courrier)
            ->setIdServiceDestinataire($data['idServiceDestinataire'] ?? null)
            ->setTypeTransfert($data['typeTransfert'] ?? 'Initial')
            ->setDateInstruction(new \DateTime())
            ->setIdEmetteur($user);

        $this->em->persist($transmission);
        $this->em->flush();

        return $courrier;
    }
}
