<?php

namespace App\Service;

use App\Entity\SecurityMode;

final class SecurityModeResponseBuilder
{
    public function buildDetail(SecurityMode $securityMode): array
    {
        return [
            'id' => $securityMode->getId(),
            'nom' => $securityMode->getNom(),
            'description' => $securityMode->getDescription(),
            'createdAt' => $securityMode->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $securityMode->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    public function buildListItem(SecurityMode $securityMode): array
    {
        return [
            'id' => $securityMode->getId(),
            'nom' => $securityMode->getNom(),
            'description' => $securityMode->getDescription(),
        ];
    }

    public function buildList(array $securityModes): array
    {
        return array_map([$this, 'buildListItem'], $securityModes);
    }
}