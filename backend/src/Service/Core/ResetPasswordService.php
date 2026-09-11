<?php

namespace App\Service\Core;

use Symfony\Component\Uid\Uuid;

class ResetPasswordService
{
    public function generateToken(): string
    {
        return Uuid::v4()->toRfc4122();
    }

    public function isTokenValid(\DateTimeInterface $expiresAt): bool
    {
        return $expiresAt > new \DateTime();
    }
}
