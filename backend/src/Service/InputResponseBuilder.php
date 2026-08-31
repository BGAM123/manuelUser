<?php

namespace App\Service;

use App\Entity\Input;

final class InputResponseBuilder
{
    public function buildDetail(Input $input): array
    {
        return [
            'id' => $input->getId(),
            'valeur' => $input->getValeur(),
            'champ' => [
                'id' => $input->getChamp()?->getId(),
                'nom' => $input->getChamp()?->getNom(),
                'type' => $input->getChamp()?->getType(),
                'sousType' => $input->getChamp()?->getSousType(),
            ],
            'createdAt' => $input->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $input->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    public function buildListItem(Input $input): array
    {
        return [
            'id' => $input->getId(),
            'valeur' => $input->getValeur(),
            'champ' => [
                'id' => $input->getChamp()?->getId(),
                'nom' => $input->getChamp()?->getNom(),
                'type' => $input->getChamp()?->getType(),
                'sousType' => $input->getChamp()?->getSousType(),
            ],
        ];
    }

    public function buildList(array $inputs): array
    {
        return array_map([$this, 'buildListItem'], $inputs);
    }
}