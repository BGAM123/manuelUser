<?php

namespace App\Service;

use App\Entity\Security;

final class SecurityResponseBuilder
{
    public function buildDetail(Security $security): array
    {
        $assets = [];
        $location = null;

        foreach ($security->getAssetSecurities() as $assetSecurity) {
            if (!$assetSecurity->isDelete()) {
                $asset = $assetSecurity->getAsset();
                if ($asset) {
                    $assets[] = [
                        'id' => $asset->getId(),
                        'reference' => $asset->getReference(),
                        'nom' => $asset->getNom(),
                        'code' => $asset->getCode(),
                    ];

                    if (!$location) {
                        $assetLocation = $asset->getAssetLocations()->first();
                        if ($assetLocation && !$assetLocation->isDelete()) {
                            $loc = $assetLocation->getLocation();
                            if ($loc) {
                                $location = [
                                    'id' => $loc->getId(),
                                    'latitude' => $loc->getLatitude(),
                                    'longitude' => $loc->getLongitude(),
                                ];
                            }
                        }
                    }
                }
            }
        }

        $documents = [];
        foreach ($security->getSecurityDocuments() as $securityDocument) {
            if (!$securityDocument->isDelete()) {
                $piece = $securityDocument->getPieceJointe();
                if ($piece) {
                    $documents[] = [
                        'id' => $piece->getId(),
                        'nom' => $piece->getNom(),
                        'chemin' => $piece->getChemin(),
                        'isPhoto' => $piece->isPhoto(),
                    ];
                }
            }
        }

        return [
            'id' => $security->getId(),
            'securityMode' => $security->getSecurityMode(), // ✅ Directement le texte
            'dateSecurisation' => $security->getDateSecurisation()?->format('Y-m-d'),
            'location' => $location,
            'assets' => $assets,
            'documents' => $documents,
            'createdAt' => $security->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $security->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    public function buildListItem(Security $security): array
    {
        $assetsCount = 0;
        $location = null;

        foreach ($security->getAssetSecurities() as $assetSecurity) {
            if (!$assetSecurity->isDelete()) {
                $assetsCount++;
                $asset = $assetSecurity->getAsset();
                if ($asset && !$location) {
                    $assetLocation = $asset->getAssetLocations()->first();
                    if ($assetLocation && !$assetLocation->isDelete()) {
                        $loc = $assetLocation->getLocation();
                        if ($loc) {
                            $location = [
                                'latitude' => $loc->getLatitude(),
                                'longitude' => $loc->getLongitude(),
                            ];
                        }
                    }
                }
            }
        }

        return [
            'id' => $security->getId(),
            'securityMode' => $security->getSecurityMode(), // ✅ Directement le texte
            'dateSecurisation' => $security->getDateSecurisation()?->format('Y-m-d'),
            'location' => $location,
            'assetsCount' => $assetsCount,
            'createdAt' => $security->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    public function buildList(array $securities): array
    {
        return array_map([$this, 'buildListItem'], $securities);
    }
}