<?php

namespace App\Repository\Core;

use App\Entity\Core\Coffre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Coffre>
 */
class CoffreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Coffre::class);
    }

    /**
     * Trouve tous les coffres non supprimés
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isDelete = false')
            ->andWhere('c.isActive = true')
            ->orderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les coffres par salle
     */
    public function findBySalle(int $idSalle, bool $activeOnly = false): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.idSalle = :idSalle')
            ->andWhere('c.isDelete = false')
            ->setParameter('idSalle', $idSalle)
            ->orderBy('c.nom', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('c.isActive = true');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les coffres avec des places disponibles
     */
    public function findWithPlacesDisponibles(?int $idSalle = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.isDelete = false')
            ->andWhere('c.isActive = true')
            ->andWhere('c.nombrePlaceActuelle < c.tailleMaximale')
            ->orderBy('c.nom', 'ASC');

        if ($idSalle !== null) {
            $qb->andWhere('c.idSalle = :idSalle')
                ->setParameter('idSalle', $idSalle);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les coffres pleins
     */
    public function findPleins(?int $idSalle = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.isDelete = false')
            ->andWhere('c.nombrePlaceActuelle >= c.tailleMaximale')
            ->orderBy('c.nom', 'ASC');

        if ($idSalle !== null) {
            $qb->andWhere('c.idSalle = :idSalle')
                ->setParameter('idSalle', $idSalle);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte le nombre total de coffres avec filtres optionnels
     */
    public function countTotal(?string $search = null, ?int $idSalle = null): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.isDelete = false');

        if ($search) {
            $qb->andWhere('c.nom LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($idSalle !== null) {
            $qb->andWhere('c.idSalle = :idSalle')
                ->setParameter('idSalle', $idSalle);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Recherche avec pagination
     */
    public function findWithPagination(int $page, int $limit, ?string $search = null, ?int $idSalle = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.isDelete = false');

        if ($search) {
            $qb->andWhere('c.nom LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($idSalle !== null) {
            $qb->andWhere('c.idSalle = :idSalle')
                ->setParameter('idSalle', $idSalle);
        }

        return $qb->orderBy('c.nom', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des coffres par salle
     */
    public function getStatistiquesBySalle(int $idSalle): array
    {
        $coffres = $this->findBySalle($idSalle);
        
        $stats = [
            'total' => count($coffres),
            'actifs' => 0,
            'pleins' => 0,
            'places_totales' => 0,
            'places_occupees' => 0,
            'places_disponibles' => 0,
            'taux_remplissage_moyen' => 0,
        ];

        foreach ($coffres as $coffre) {
            if ($coffre->isActive()) {
                $stats['actifs']++;
            }
            if ($coffre->isPlein()) {
                $stats['pleins']++;
            }
            $stats['places_totales'] += $coffre->getTailleMaximale();
            $stats['places_occupees'] += $coffre->getNombrePlaceActuelle();
        }

        $stats['places_disponibles'] = $stats['places_totales'] - $stats['places_occupees'];
        
        if ($stats['places_totales'] > 0) {
            $stats['taux_remplissage_moyen'] = round(($stats['places_occupees'] / $stats['places_totales']) * 100, 2);
        }

        return $stats;
    }
}
