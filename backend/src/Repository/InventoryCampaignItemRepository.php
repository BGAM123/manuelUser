<?php

namespace App\Repository;

use App\Entity\InventaireCampagne;
use App\Entity\InventoryCampaignItem;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InventoryCampaignItem>
 */
class InventoryCampaignItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventoryCampaignItem::class);
    }

    public function save(InventoryCampaignItem $item): void
    {
        $em = $this->getEntityManager();
        $em->persist($item);
        $em->flush();
    }

    public function getItemById(int $id): ?InventoryCampaignItem
    {
        return $this->find($id);
    }

    /**
     * @return InventoryCampaignItem[]
     */
    public function findPaginatedByCampaign(InventaireCampagne $campaign, int $page, int $limit, ?bool $retrouve = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->leftJoin('i.asset', 'a')->addSelect('a')
            ->andWhere('i.campaign = :campaign')
            ->setParameter('campaign', $campaign)
            ->orderBy('i.id', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if (null !== $retrouve) {
            $qb->andWhere('i.retrouve = :retrouve')->setParameter('retrouve', $retrouve);
        }

        return $qb->getQuery()->getResult();
    }

    public function countByCampaign(InventaireCampagne $campaign, ?bool $retrouve = null): int
    {
        $qb = $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.campaign = :campaign')
            ->setParameter('campaign', $campaign);

        if (null !== $retrouve) {
            $qb->andWhere('i.retrouve = :retrouve')->setParameter('retrouve', $retrouve);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function markAsFound(InventoryCampaignItem $item, User $verifiePar, ?string $observations = null): void
    {
        $item->markAsFound($verifiePar, $observations);
        $this->save($item);
    }
}