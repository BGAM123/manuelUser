<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\InventaireCampagne;
use App\Entity\InventoryCampaignItem;
use App\Entity\Service;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InventaireCampagne>
 */
class InventaireCampagneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventaireCampagne::class);
    }

    public function save(InventaireCampagne $campaign): void
    {
        $em = $this->getEntityManager();
        $em->persist($campaign);
        $em->flush();
    }

    public function softDelete(InventaireCampagne $campaign): void
    {
        $campaign->setIsDelete(true);
        $this->save($campaign);
    }

    public function getCampaignById(int $id): ?InventaireCampagne
    {
        return $this->find($id);
    }

    /**
     * @return InventaireCampagne[]
     */
    public function findPaginatedCampaigns(int $page, int $limit, ?string $statut = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.isDelete = false')
            ->orderBy('c.dateDebut', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if (null !== $statut && '' !== $statut) {
            $qb->andWhere('c.statut = :statut')->setParameter('statut', $statut);
        }

        return $qb->getQuery()->getResult();
    }

    public function countCampaigns(?string $statut = null): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.isDelete = false');

        if (null !== $statut && '' !== $statut) {
            $qb->andWhere('c.statut = :statut')->setParameter('statut', $statut);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Construit la campagne ET fige immédiatement ses items à partir du payload et de la
     * liste d'assets déjà résolue par le contrôleur (via AssetRepository::findAllActiveForCampaignScope).
     *
     * @param \App\Entity\Asset[] $assetsToFreeze
     */
    public function buildCampaignFromPayload(array $payload, array $assetsToFreeze, ?Service $service, ?Category $category): InventaireCampagne
    {
        $campaign = new InventaireCampagne();
        $campaign->setNom((string) $payload['nom']);
        if (array_key_exists('description', $payload)) {
            $campaign->setDescription(null !== $payload['description'] ? (string) $payload['description'] : null);
        }
        $campaign->setService($service);
        $campaign->setCategory($category);

        foreach ($assetsToFreeze as $asset) {
            $item = new InventoryCampaignItem();
            $item->setAsset($asset);
            $campaign->addItem($item);
        }

        return $campaign;
    }

    /**
     * Nombre de biens non retrouvés sur une campagne encore ouverte - c'est la donnée
     * exploitée par le futur module IA (catégorie d'anomalie "inventaire").
     */
    public function countMissingItems(InventaireCampagne $campaign): int
    {
        return (int) $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(i.id)')
            ->from(InventoryCampaignItem::class, 'i')
            ->andWhere('i.campaign = :campaign')
            ->andWhere('i.retrouve = false')
            ->setParameter('campaign', $campaign)
            ->getQuery()
            ->getSingleScalarResult();
    }
}