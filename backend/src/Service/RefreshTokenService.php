<?php

namespace App\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

final class RefreshTokenService
{
    private const DEFAULT_TTL = 604800; // 7 days in seconds

    public function __construct(
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function createRefreshToken(User $user): RefreshToken
    {
        $refreshToken = new RefreshToken(
            $user,
            bin2hex(random_bytes(32)),
            new \DateTimeImmutable(sprintf('+%d seconds', self::DEFAULT_TTL))
        );

        $this->refreshTokenRepository->save($refreshToken);

        return $refreshToken;
    }

    public function getValidRefreshToken(string $token): ?RefreshToken
    {
        return $this->refreshTokenRepository->findValidToken($token);
    }

    public function rotateRefreshToken(RefreshToken $currentToken): RefreshToken
    {
        $currentToken->revoke();
        $this->entityManager->flush();

        return $this->createRefreshToken($currentToken->getUser());
    }
}
