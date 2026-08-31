<?php

namespace App\Service;

use App\Entity\AssetReformRequest;

final class AssetReformResponseBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function buildDetail(AssetReformRequest $request): array
    {
        $pieceJointes = [];
        foreach ($request->getPieceJointes() as $piece) {
            $pieceJointes[] = [
                'id' => $piece->getId(),
                'nom' => $piece->getNom(),
                'chemin' => $piece->getChemin(),
            ];
        }

        return [
            'id' => $request->getId(),
            'asset' => [
                'id' => $request->getAsset()->getId(),
                'reference' => $request->getAsset()->getReference(),
                'nom' => $request->getAsset()->getNom(),
            ],
            'statut' => $request->getStatut(),
            'pieceJointes' => $pieceJointes,
            'createdAt' => $request->getCreatedAt()->format('Y-m-d H:i:s'),
            'createdBy' => $request->getCreatedBy() ? [
                'id' => $request->getCreatedBy()->getId(),
                'nom' => $request->getCreatedBy()->getLastName(),
                'prenom' => $request->getCreatedBy()->getFirstName(),
            ] : null,
            'validatedAt' => $request->getValidatedAt()?->format('Y-m-d H:i:s'),
            'validatedBy' => $request->getValidatedBy() ? [
                'id' => $request->getValidatedBy()->getId(),
                'nom' => $request->getValidatedBy()->getLastName(),
                'prenom' => $request->getValidatedBy()->getFirstName(),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildListItem(AssetReformRequest $request): array
    {
        return [
            'id' => $request->getId(),
            'asset' => [
                'id' => $request->getAsset()->getId(),
                'reference' => $request->getAsset()->getReference(),
                'nom' => $request->getAsset()->getNom(),
            ],
            'statut' => $request->getStatut(),
            'createdAt' => $request->getCreatedAt()->format('Y-m-d H:i:s'),
            'validatedAt' => $request->getValidatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
