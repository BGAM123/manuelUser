<?php

namespace App\Repository;

use App\Entity\RefreshToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
final class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    public function save(RefreshToken $refreshToken): void
    {
        $em = $this->getEntityManager();
        $em->persist($refreshToken);
        $em->flush();
    }

    public function findValidToken(string $refreshToken): ?RefreshToken
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.refreshToken = :token')
            ->andWhere('r.isRevoked = false')
            ->andWhere('r.expiresAt > :now')
            ->setParameter('token', $refreshToken)
            ->setParameter('now', new \DateTimeImmutable())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
