<?php

namespace App\Service;

use App\Entity\ExitType;

final class ExitTypeResponseBuilder
{
    public function buildDetail(ExitType $exitType): array
    {
        return [
            'id' => $exitType->getId(),
            'nom' => $exitType->getNom(),
            'code' => $exitType->getCode(),
            'description' => $exitType->getDescription(),
            'isActive' => $exitType->isActive(),
            'beneficiaire' => $exitType->isBeneficiaire(),
            'createdAt' => $exitType->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $exitType->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    public function buildList(array $exitTypes): array
    {
        return array_map([$this, 'buildDetail'], $exitTypes);
    }

    /**
     * Construit une réponse paginée
     */
    public function buildPaginated(array $items, int $page, int $limit, int $total): array
    {
        return [
            'items' => $this->buildList($items),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int) ceil($total / $limit)
            ]
        ];
    }
}