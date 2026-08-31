<?php

namespace App\Service;

use App\Doctrine\Filter\SoftDeleteQueryFilter;
use App\Entity\Asset;
use App\Entity\AssetLocation;
use App\Entity\AssetSecurity;
use App\Entity\Location;
use App\Entity\PieceJointe;
use App\Entity\Security;
use App\Entity\SecurityDocument;
use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use App\Repository\AssetRepository;
use App\Repository\SecurityRepository;
use App\Repository\LocationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class SecurityService
{
    public function __construct(
        private readonly SecurityRepository $securityRepository,
        private readonly AssetRepository $assetRepository,
        private readonly LocationRepository $locationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly FileUploadService $fileUploadService,
        private readonly ForceDeleteService $forceDeleteService
    ) {
    }

    /**
     * @param list<int> $assetIds
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     */
    public function create(
        array $assetIds,
        string $securityMode, // ✅ Plus d'ID, mais un texte
        ?\DateTimeImmutable $dateSecurisation = null,
        ?float $latitude = null,
        ?float $longitude = null,
        array $documents = [],
        array $documentLabels = []
    ): Security {
        if (empty($assetIds)) {
            throw new ValidationFailedException(['asset_ids' => 'Au moins un ID de bien est requis.']);
        }

        if (empty(trim($securityMode))) {
            throw new ValidationFailedException(['security_mode' => 'Le mode de sécurisation est obligatoire.']);
        }

        // Validation des coordonnées
        if (($latitude !== null && $longitude === null) || ($latitude === null && $longitude !== null)) {
            throw new ValidationFailedException(['coordinates' => 'La latitude et la longitude doivent être fournies ensemble.']);
        }

        if ($latitude !== null && ($latitude < -90 || $latitude > 90)) {
            throw new ValidationFailedException(['latitude' => 'La latitude doit être entre -90 et 90.']);
        }

        if ($longitude !== null && ($longitude < -180 || $longitude > 180)) {
            throw new ValidationFailedException(['longitude' => 'La longitude doit être entre -180 et 180.']);
        }

        // Vérifier que tous les biens existent
        $assets = [];
        $hasExistingLocation = false;
        $firstLocation = null;

        foreach ($assetIds as $assetId) {
            $asset = $this->assetRepository->find($assetId);
            if (!$asset) {
                throw new ResourceNotFoundException("Le bien avec l'identifiant {$assetId} n'existe pas.");
            }
            $assets[$assetId] = $asset;

            // Vérifier si le bien a déjà une localisation
            $assetLocation = $asset->getAssetLocations()->first();
            if ($assetLocation && $assetLocation->getLocation()) {
                $hasExistingLocation = true;
                if (!$firstLocation) {
                    $firstLocation = $assetLocation->getLocation();
                }
            }
        }

        // Gestion des coordonnées
        $location = null;
        $isNewLocation = false;

        if ($latitude !== null && $longitude !== null) {
            $location = new Location();
            $location->setLatitude($latitude);
            $location->setLongitude($longitude);
            $this->entityManager->persist($location);
            $isNewLocation = true;
        } elseif ($hasExistingLocation && $firstLocation) {
            $location = $firstLocation;
            $isNewLocation = false;
        }

        // Transaction
        $this->entityManager->beginTransaction();
        try {
            // Créer la sécurisation
            $security = new Security();
            $security->setSecurityMode(trim($securityMode)); // ✅ Stockage direct du texte
            $security->setDateSecurisation($dateSecurisation);

            // Gestion de la localisation
            if ($isNewLocation && $location) {
                foreach ($assets as $asset) {
                    $assetLocation = new AssetLocation();
                    $assetLocation->setAsset($asset);
                    $assetLocation->setLocation($location);
                    $this->entityManager->persist($assetLocation);
                    $asset->addAssetLocation($assetLocation);
                }
            } elseif ($location && !$isNewLocation) {
                foreach ($assets as $asset) {
                    $hasLocation = false;
                    foreach ($asset->getAssetLocations() as $assetLoc) {
                        if ($assetLoc->getLocation() && $assetLoc->getLocation()->getId() === $location->getId()) {
                            $hasLocation = true;
                            break;
                        }
                    }
                    
                    if (!$hasLocation) {
                        $assetLocation = new AssetLocation();
                        $assetLocation->setAsset($asset);
                        $assetLocation->setLocation($location);
                        $this->entityManager->persist($assetLocation);
                        $asset->addAssetLocation($assetLocation);
                    }
                }
            }

            // Attacher les pièces jointes
            $documentLabels = UploadedFilesNormalizer::parseLabelList($documentLabels);
            foreach (array_values($documents) as $index => $document) {
                if (!$document instanceof UploadedFile) {
                    continue;
                }
                if (UPLOAD_ERR_NO_FILE === $document->getError()) {
                    continue;
                }
                if (!$document->isValid()) {
                    throw new ValidationFailedException(['piecesJointes' => 'Fichier invalide : ' . $document->getErrorMessage()]);
                }

                $label = $documentLabels[$index] ?? null;
                if (null === $label || '' === trim((string) $label)) {
                    $label = $document->getClientOriginalName();
                }

                $piece = $this->fileUploadService->upload(
                    $document,
                    FileUploadService::KIND_DOCUMENT,
                    (string) $label,
                    true
                );

                $securityDocument = new SecurityDocument();
                $securityDocument->setSecurity($security);
                $securityDocument->setPieceJointe($piece);
                $this->entityManager->persist($securityDocument);
                $security->addSecurityDocument($securityDocument);
            }

            $this->validate($security);
            $this->entityManager->persist($security);

            // Créer les relations AssetSecurity
            foreach ($assets as $asset) {
                $assetSecurity = new AssetSecurity();
                $assetSecurity->setSecurity($security);
                $assetSecurity->setAsset($asset);
                $this->entityManager->persist($assetSecurity);
                $security->addAssetSecurity($assetSecurity);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();
            
        } catch (\Exception $e) {
            $this->entityManager->rollBack();
            throw $e;
        }

        return $security;
    }

    public function list(
        int $page = 1,
        int $limit = 20,
        ?string $securityMode = null, // ✅ Recherche par texte
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?int $assetId = null,
        ?string $search = null,
        string $orderBy = 'createdAt',
        string $orderDir = 'DESC',
        ?string $isDelete = 'false'
    ): array {
        $qb = $this->securityRepository->createQueryBuilder('s')
            ->leftJoin('s.assetSecurities', 'assetSec')
            ->leftJoin('assetSec.asset', 'a')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
        SoftDeleteQueryFilter::apply($qb, 's', $isDelete);

        // ✅ Filtre par mode de sécurisation (texte)
        if ($securityMode && !empty(trim($securityMode))) {
            $qb->andWhere('LOWER(s.securityMode) LIKE LOWER(:securityMode)')
               ->setParameter('securityMode', '%' . trim($securityMode) . '%');
        }

        // Filtre par plage de dates
        if ($dateFrom) {
            $qb->andWhere('s.dateSecurisation >= :dateFrom')
               ->setParameter('dateFrom', new \DateTimeImmutable($dateFrom));
        }

        if ($dateTo) {
            $qb->andWhere('s.dateSecurisation <= :dateTo')
               ->setParameter('dateTo', new \DateTimeImmutable($dateTo));
        }

        // Filtre par bien
        if ($assetId) {
            $qb->andWhere('a.id = :assetId')
               ->setParameter('assetId', $assetId);
        }

        // Recherche textuelle
        if ($search && !empty(trim($search))) {
            $qb->andWhere('LOWER(s.securityMode) LIKE LOWER(:search) OR LOWER(a.nom) LIKE LOWER(:search)')
               ->setParameter('search', '%' . trim($search) . '%');
        }

        // Tri
        switch ($orderBy) {
            case 'dateSecurisation':
                $qb->orderBy('s.dateSecurisation', $orderDir);
                break;
            case 'id':
                $qb->orderBy('s.id', $orderDir);
                break;
            case 'securityMode':
                $qb->orderBy('s.securityMode', $orderDir);
                break;
            case 'createdAt':
            default:
                $qb->orderBy('s.createdAt', $orderDir);
                break;
        }

        $securities = $qb->getQuery()->getResult();

        // Compter le total
        $countQb = $this->securityRepository->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->leftJoin('s.assetSecurities', 'assetSec')
            ->leftJoin('assetSec.asset', 'a');
        SoftDeleteQueryFilter::apply($countQb, 's', $isDelete);

        if ($securityMode && !empty(trim($securityMode))) {
            $countQb->andWhere('LOWER(s.securityMode) LIKE LOWER(:securityMode)')
                    ->setParameter('securityMode', '%' . trim($securityMode) . '%');
        }

        if ($dateFrom) {
            $countQb->andWhere('s.dateSecurisation >= :dateFrom')
                    ->setParameter('dateFrom', new \DateTimeImmutable($dateFrom));
        }

        if ($dateTo) {
            $countQb->andWhere('s.dateSecurisation <= :dateTo')
                    ->setParameter('dateTo', new \DateTimeImmutable($dateTo));
        }

        if ($assetId) {
            $countQb->andWhere('a.id = :assetId')
                    ->setParameter('assetId', $assetId);
        }

        if ($search && !empty(trim($search))) {
            $countQb->andWhere('LOWER(s.securityMode) LIKE LOWER(:search) OR LOWER(a.nom) LIKE LOWER(:search)')
                    ->setParameter('search', '%' . trim($search) . '%');
        }

        $total = $countQb->getQuery()->getSingleScalarResult();

        return [
            'items' => $securities,
            'total' => (int) $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => (int) ceil($total / $limit),
        ];
    }

    public function get(int $id): Security
    {
        $security = $this->securityRepository->find($id);
        
        if (!$security) {
            throw new ResourceNotFoundException('La sécurisation demandée n\'existe pas.');
        }

        if ($security->isDelete()) {
            throw new ResourceNotFoundException('Cette sécurisation a été supprimée.');
        }

        return $security;
    }

    /**
     * @param list<int>|null $assetIds
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     */
    public function update(
        int $id,
        ?array $assetIds = null,
        ?string $securityMode = null, // ✅ Plus d'ID, mais un texte
        ?\DateTimeImmutable $dateSecurisation = null,
        ?float $latitude = null,
        ?float $longitude = null,
        array $documents = [],
        array $documentLabels = []
    ): Security {
        $security = $this->get($id);

        // Validation du mode de sécurisation
        if ($securityMode !== null && empty(trim($securityMode))) {
            throw new ValidationFailedException(['security_mode' => 'Le mode de sécurisation ne peut pas être vide.']);
        }

        // Validation des coordonnées
        if (($latitude !== null && $longitude === null) || ($latitude === null && $longitude !== null)) {
            throw new ValidationFailedException(['coordinates' => 'La latitude et la longitude doivent être fournies ensemble.']);
        }

        if ($latitude !== null && ($latitude < -90 || $latitude > 90)) {
            throw new ValidationFailedException(['latitude' => 'La latitude doit être entre -90 et 90.']);
        }

        if ($longitude !== null && ($longitude < -180 || $longitude > 180)) {
            throw new ValidationFailedException(['longitude' => 'La longitude doit être entre -180 et 180.']);
        }

        // Mettre à jour le mode de sécurisation
        if ($securityMode !== null) {
            $security->setSecurityMode(trim($securityMode));
        }

        // Mettre à jour la date
        if ($dateSecurisation !== null) {
            $security->setDateSecurisation($dateSecurisation);
        }

        // Transaction
        $this->entityManager->beginTransaction();
        try {
            // Récupérer les biens actuels
            $currentAssets = [];
            foreach ($security->getAssetSecurities() as $assetSecurity) {
                if (!$assetSecurity->isDelete()) {
                    $asset = $assetSecurity->getAsset();
                    if ($asset) {
                        $currentAssets[] = $asset;
                    }
                }
            }

            // Gestion de la localisation
            if ($latitude !== null && $longitude !== null) {
                $existingLocation = null;
                foreach ($currentAssets as $asset) {
                    $assetLocation = $asset->getAssetLocations()->first();
                    if ($assetLocation && $assetLocation->getLocation()) {
                        $existingLocation = $assetLocation->getLocation();
                        break;
                    }
                }

                if ($existingLocation) {
                    $location = $existingLocation;
                    $location->setLatitude($latitude);
                    $location->setLongitude($longitude);
                } else {
                    $location = new Location();
                    $location->setLatitude($latitude);
                    $location->setLongitude($longitude);
                    $this->entityManager->persist($location);
                }

                // Mettre à jour la localisation pour tous les biens
                $allAssetIds = [];
                foreach ($currentAssets as $asset) {
                    $allAssetIds[] = $asset->getId();
                }
                if ($assetIds) {
                    foreach ($assetIds as $assetId) {
                        if (!in_array($assetId, $allAssetIds)) {
                            $allAssetIds[] = $assetId;
                        }
                    }
                }

                foreach ($allAssetIds as $assetId) {
                    $asset = $this->assetRepository->find($assetId);
                    if (!$asset) {
                        continue;
                    }

                    $hasLocation = false;
                    foreach ($asset->getAssetLocations() as $assetLoc) {
                        if ($assetLoc->getLocation()) {
                            $assetLoc->setLocation($location);
                            $hasLocation = true;
                            break;
                        }
                    }

                    if (!$hasLocation) {
                        $assetLocation = new AssetLocation();
                        $assetLocation->setAsset($asset);
                        $assetLocation->setLocation($location);
                        $this->entityManager->persist($assetLocation);
                        $asset->addAssetLocation($assetLocation);
                    }
                }
            }

            // Mettre à jour les biens si nécessaire
            if ($assetIds !== null) {
                foreach ($security->getAssetSecurities() as $assetSecurity) {
                    if (!$assetSecurity->isDelete()) {
                        $assetSecurity->setDelete(true);
                    }
                }

                foreach ($assetIds as $assetId) {
                    $asset = $this->assetRepository->find($assetId);
                    if (!$asset) {
                        throw new ResourceNotFoundException("Le bien avec l'identifiant {$assetId} n'existe pas.");
                    }

                    $assetSecurity = new AssetSecurity();
                    $assetSecurity->setSecurity($security);
                    $assetSecurity->setAsset($asset);
                    $this->entityManager->persist($assetSecurity);
                    $security->addAssetSecurity($assetSecurity);
                }
            }

            // Ajouter de nouvelles pièces jointes
            $documentLabels = UploadedFilesNormalizer::parseLabelList($documentLabels);
            foreach (array_values($documents) as $index => $document) {
                if (!$document instanceof UploadedFile) {
                    continue;
                }
                if (UPLOAD_ERR_NO_FILE === $document->getError()) {
                    continue;
                }
                if (!$document->isValid()) {
                    throw new ValidationFailedException(['piecesJointes' => 'Fichier invalide : ' . $document->getErrorMessage()]);
                }

                $label = $documentLabels[$index] ?? null;
                if (null === $label || '' === trim((string) $label)) {
                    $label = $document->getClientOriginalName();
                }

                $piece = $this->fileUploadService->upload(
                    $document,
                    FileUploadService::KIND_DOCUMENT,
                    (string) $label,
                    true
                );

                $securityDocument = new SecurityDocument();
                $securityDocument->setSecurity($security);
                $securityDocument->setPieceJointe($piece);
                $this->entityManager->persist($securityDocument);
                $security->addSecurityDocument($securityDocument);
            }

            $this->validate($security);
            $this->entityManager->flush();
            $this->entityManager->commit();
            
        } catch (\Exception $e) {
            $this->entityManager->rollBack();
            throw $e;
        }

        return $security;
    }

    public function delete(int $id): void
    {
        $security = $this->get($id);
        
        $security->setDelete(true);
        
        foreach ($security->getAssetSecurities() as $assetSecurity) {
            $assetSecurity->setDelete(true);
        }

        foreach ($security->getSecurityDocuments() as $securityDocument) {
            $securityDocument->setDelete(true);
        }

        $this->securityRepository->save($security, true);
    }

    /**
     * Suppression définitive : agit même sur une sécurisation déjà soft-deleted
     * (donc find() plutôt que get(), qui rejette les lignes déjà supprimées).
     * AssetSecurity/SecurityDocument sont en onDelete: CASCADE en base, aucune
     * suppression manuelle des enfants n'est nécessaire côté PHP.
     */
    public function deleteForced(int $id): void
    {
        $security = $this->securityRepository->find($id);
        if (!$security) {
            throw new ResourceNotFoundException('La sécurisation demandée n\'existe pas.');
        }

        $this->forceDeleteService->delete($security);
    }

    private function validate(Security $security): void
    {
        $errors = $this->validator->validate($security);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            throw new ValidationFailedException($errorMessages);
        }
    }
}