<?php

namespace App\Service;

use App\Entity\Asset;
use App\Entity\Location;
use App\Entity\AssetLocation;
use App\Entity\Security;
use App\Entity\AssetSecurity;
use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use App\Repository\AssetRepository;
use App\Repository\AssetTypeRepository;
use App\Repository\AssetSubTypeRepository;
use App\Repository\ChampRepository;  // ✅ Ajouter
use App\Repository\CategoryRepository;
use App\Entity\Input; 
use App\Repository\EtatBienRepository;
use App\Repository\ProjectRepository;
use App\Repository\ServiceRepository;
use App\Repository\LocationRepository;
use App\Repository\InputRepository;  // ✅ Ajouter
use App\Repository\UserRepository;  // ✅ Ajouter
use App\Repository\AssetAssignmentRepository;  // ✅ Ajouter
use Doctrine\ORM\EntityManagerInterface;  // ✅ Ajouter
use App\Repository\AssetLocationRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AssetManagementService
{
    public function __construct(
        private readonly AssetRepository $assetRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly AssetTypeRepository $assetTypeRepository,
        private readonly EtatBienRepository $etatBienRepository,
        private readonly ServiceRepository $serviceRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly FileUploadService $fileUploadService,
        private readonly ValidatorInterface $validator,
        private readonly LocationRepository $locationRepository,
        private readonly AssetLocationRepository $assetLocationRepository,
        private readonly ChampRepository $champRepository,  // ✅ Ajouter
        private readonly AssetSubTypeRepository $assetSubTypeRepository,
        private readonly InputRepository $inputRepository,  // ✅ Ajouter
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,  // ✅ Ajouter
        private readonly AssetAssignmentRepository $assetAssignmentRepository,  // ✅ Ajouter
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<UploadedFile> $photos
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     */
    public function create(array $payload, array $photos = [], array $documents = [], array $documentLabels = [], array $champsExistants = [], array $champsNouveaux = [], array $champsValeurs = [], ?\App\Entity\User $currentUser = null): Asset
    {
        $asset = new Asset();
        $now = new \DateTimeImmutable();
        $asset->setCreatedAt($now);
        $asset->setUpdatedAt($now);

        // Définir l'utilisateur créateur si fourni
        if ($currentUser !== null) {
            $asset->setCreatedBy($currentUser);
        }

        // Référence absente / null / "null" / "undefined" / espaces → auto-génération
        if ($this->isBlankReference($payload['reference'] ?? null)) {
            $asset->setReference($this->assetRepository->generateNextReference());
            unset($payload['reference']);
        }

        if (!array_key_exists('statut', $payload) || $this->isBlankReference($payload['statut'] ?? null)) {
            $asset->setStatut('ACTIF');
            unset($payload['statut']);
        }

        // ✅ 1. Traiter les champs existants
        if (!empty($champsExistants)) {
            $this->processExistingChamps($asset, $champsExistants);
        }

        // ✅ 2. Traiter les nouveaux champs
        if (!empty($champsNouveaux)) {
            $this->processNewChamps($asset, $champsNouveaux);
        }

        // Ajouter les valeurs saisies (champs_valeurs) aux champs existants ou
        // nouvellement associés ci-dessus.
        if (!empty($champsValeurs)) {
            $this->processChampsValeurs($asset, $champsValeurs);
        }

        // ✅ 4. Gestion de la valeur et valeur initiale
        // Si 'valeur' est fourni, on l'utilise pour les deux champs
        if (array_key_exists('valeur', $payload) && null !== $payload['valeur'] && '' !== $payload['valeur']) {
            $asset->setValeur($payload['valeur']);
            $asset->setValeurInitiale($payload['valeur']);
        } 
        // Sinon, si 'valeurInitiale' est fourni seul, on l'utilise
        elseif (array_key_exists('valeurInitiale', $payload) && null !== $payload['valeurInitiale'] && '' !== $payload['valeurInitiale']) {
            $asset->setValeurInitiale($payload['valeurInitiale']);
            // Si 'valeur' n'est pas défini, on met la même valeur
            if (!array_key_exists('valeur', $payload) || null === $payload['valeur'] || '' === $payload['valeur']) {
                $asset->setValeur($payload['valeurInitiale']);
            }
        }

        // Lors de la création, on initialise la valeurInitiale avec la valeur actuelle
        if (!empty($payload['valeur'])) {
            $asset->setValeurInitiale($payload['valeur']);
            $asset->setValeur($payload['valeur']);
        } elseif (!empty($payload['valeurInitiale'])) {
            $asset->setValeurInitiale($payload['valeurInitiale']);
        }

        // Gestion des flags d'activation
        if (array_key_exists('activeAmortissement', $payload)) {
            $asset->setActiveAmortissement(filter_var($payload['activeAmortissement'], FILTER_VALIDATE_BOOLEAN));
        }
        if (array_key_exists('activeReevaluation', $payload)) {
            $asset->setActiveReevaluation(filter_var($payload['activeReevaluation'], FILTER_VALIDATE_BOOLEAN));
        }

        $this->applyPayload($asset, $payload);
        $this->attachFiles($asset, $photos, $documents, $documentLabels);
        $this->assertValid($asset);

        // ✅ Transaction atomique pour Asset + Securisation
        $this->entityManager->beginTransaction();

        try {
            // ✅ Créer une sécurisation automatique si securityMode est fourni
            $this->createSecurityIfProvided($asset, $payload);

            $this->assetRepository->save($asset);

            // ✅ Créer une affectation si user_id ou service_id est fourni
            $this->createOrUpdateAssignment($asset, $payload, $currentUser);

            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        return $asset;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<UploadedFile> $photos
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     */
    /**
 * @param array<string, mixed> $payload
 * @param list<UploadedFile> $photos
 * @param list<UploadedFile> $documents
 * @param list<?string> $documentLabels
 * @param array<array{input_id: int, valeur: string}> $champsValeursUpdate
 * @param array<array{champ_id: int, valeur: string}> $champsValeursAjout
 */
public function update(
    Asset $asset,
    array $payload,
    array $photos = [],
    array $documents = [],
    array $documentLabels = [],
    array $champsValeursUpdate = [],
    array $champsValeursAjout = [],
    ?\App\Entity\User $currentUser = null
): Asset {
    // ✅ 1. Mettre à jour les valeurs existantes
    if (!empty($champsValeursUpdate)) {
        $this->updateChampsValeurs($asset, $champsValeursUpdate);
    }

    // ✅ 2. Ajouter de nouvelles valeurs à des champs existants
    if (!empty($champsValeursAjout)) {
        $this->processChampsValeurs($asset, $champsValeursAjout);
    }

    // Valeur initiale : ne pas modifier si déjà définie, ou permettre la mise à jour si spécifiée explicitement
    if (array_key_exists('valeurInitiale', $payload)) {
        $asset->setValeurInitiale($payload['valeurInitiale']);
    }

    // Gestion des flags d'activation
    if (array_key_exists('activeAmortissement', $payload)) {
        $asset->setActiveAmortissement(filter_var($payload['activeAmortissement'], FILTER_VALIDATE_BOOLEAN));
    }
    if (array_key_exists('activeReevaluation', $payload)) {
        $asset->setActiveReevaluation(filter_var($payload['activeReevaluation'], FILTER_VALIDATE_BOOLEAN));
    }

    $this->applyPayload($asset, $payload);
    $this->attachFiles($asset, $photos, $documents, $documentLabels);
    $asset->setUpdatedAt(new \DateTimeImmutable());

    // Définir l'utilisateur modificateur si fourni
    if ($currentUser !== null) {
        $asset->setUpdatedBy($currentUser);
    }

    $this->assertValid($asset);

    $this->assetRepository->save($asset);

    // ✅ Mettre à jour l'affectation si user_id ou service_id est fourni
    $this->createOrUpdateAssignment($asset, $payload, $currentUser);

    return $asset;
}


    /**
 * ✅ Met à jour des valeurs existantes (sans table asset_input)
 * @param Asset $asset
 * @param array<array{input_id: int, valeur: string}> $champsValeursUpdate
 */
private function updateChampsValeurs(Asset $asset, array $champsValeursUpdate): void
{
    foreach ($champsValeursUpdate as $item) {
        $input = $this->inputRepository->find($item['input_id']);
        if (!$input || $input->isDelete()) {
            throw new ValidationFailedException([
                'champs_valeurs_update' => "La valeur avec l'ID {$item['input_id']} n'existe pas."
            ]);
        }

        $champ = $input->getChamp();
        if (!$champ || !$asset->getChamps()->contains($champ)) {
            throw new ValidationFailedException([
                'champs_valeurs_update' => "La valeur avec l'ID {$item['input_id']} n'est pas associée à ce bien."
            ]);
        }

        // Mettre à jour la valeur
        $input->setValeur($item['valeur']);
        // ✅ Ne pas appeler setUpdatedAt, onPreUpdate() le fera automatiquement
        $this->entityManager->persist($input);
    }
}


/**
 * Active ou désactive l'amortissement d'un bien
 */
public function updateAmortissement(Asset $asset, bool $active): Asset
{
    $asset->setActiveAmortissement($active);
    $asset->setUpdatedAt(new \DateTimeImmutable());
    $this->assertValid($asset);
    $this->assetRepository->save($asset);
    
    return $asset;
}

/**
 * Active ou désactive la réévaluation d'un bien
 */
public function updateReevaluation(Asset $asset, bool $active): Asset
{
    $asset->setActiveReevaluation($active);
    $asset->setUpdatedAt(new \DateTimeImmutable());
    $this->assertValid($asset);
    $this->assetRepository->save($asset);
    
    return $asset;
}

/**
 * Active ou désactive la dépréciation d'un bien
 */
public function updateDepreciation(Asset $asset, bool $active): Asset
{
    $asset->setActiveDepreciation($active);
    $asset->setUpdatedAt(new \DateTimeImmutable());
    $this->assertValid($asset);
    $this->assetRepository->save($asset);
    
    return $asset;
}

      /**
     * ✅ Ajoute des valeurs à des champs existants (sans table asset_input)
     * @param Asset $asset
     * @param array<array{champ_id: int, valeur: string}> $champsValeursAjout
     */
    private function processChampsValeursAjout(Asset $asset, array $champsValeursAjout): void
    {
        foreach ($champsValeursAjout as $item) {
            // 1. Vérifier que le champ existe et est actif
            $champ = $this->champRepository->getActiveById($item['champ_id']);
            if (!$champ) {
                throw new ValidationFailedException([
                    'champs_valeurs_ajout' => "Le champ avec l'ID {$item['champ_id']} n'existe pas."
                ]);
            }

            // 2. Vérifier que le champ est lié au bien
            if (!$asset->getChamps()->contains($champ)) {
                throw new ValidationFailedException([
                    'champs_valeurs_ajout' => "Le champ avec l'ID {$item['champ_id']} n'est pas associé à ce bien."
                ]);
            }

            // 3. Créer un nouvel input avec la valeur
            $input = new Input();
            $input->setChamp($champ);
            $input->setValeur($item['valeur']);
            $this->entityManager->persist($input);
        }
    }



    /**
     * ✅ Associe des champs existants au bien
     * @param Asset $asset
     * @param array<int> $champIds
     */
    private function processExistingChamps(Asset $asset, array $champIds): void
    {
        foreach ($champIds as $champId) {
            $champ = $this->champRepository->getActiveById($champId);
            if (!$champ) {
                throw new ValidationFailedException([
                    'champs_existants' => "Le champ avec l'ID {$champId} n'existe pas."
                ]);
            }
            $asset->addChamp($champ);
        }
    }

    /**
     * ✅ Crée de nouveaux champs et les associe au bien
     * @param Asset $asset
     * @param array<array{nom: string, valeur: string}> $champsNouveaux
     */
    private function processNewChamps(Asset $asset, array $champsNouveaux): void
    {
        foreach ($champsNouveaux as $index => $champData) {
            if (empty($champData['nom']) || empty($champData['valeur'])) {
                throw new ValidationFailedException([
                    'champs_nouveaux' => "Le champ à l'index {$index} doit avoir un nom et une valeur."
                ]);
            }

            // ✅ 1. Vérifier si le champ existe déjà par son nom
            $existingChamp = $this->champRepository->findOneBy(['nom' => $champData['nom']]);
            
            if ($existingChamp) {
                // ✅ Utiliser le champ existant
                $champ = $existingChamp;
            } else {
                // ✅ 2. Créer un nouveau champ
                $champ = new \App\Entity\Champ();
                $champ->setNom($champData['nom']);
                $champ->setCreatedAt(new \DateTimeImmutable());
                $champ->setUpdatedAt(new \DateTimeImmutable());
                $this->entityManager->persist($champ);
                $this->entityManager->flush(); // Flush pour avoir l'ID du champ
            }

            // ✅ 3. Créer l'input avec la valeur
            $input = new Input();
            $input->setChamp($champ);
            $input->setValeur($champData['valeur']);
            $this->entityManager->persist($input);
            
            // ✅ 4. Associer le champ au bien
            if (!$asset->getChamps()->contains($champ)) {
                $asset->addChamp($champ);
            }
        }
    }

    /**
 * ✅ Ajoute des valeurs à des champs existants
 * @param Asset $asset
 * @param array<array{champ_id: int, valeur: string}> $champsValeurs
 */
private function processChampsValeurs(Asset $asset, array $champsValeurs): void
{
    foreach ($champsValeurs as $item) {
        $champ = $this->champRepository->getActiveById($item['champ_id']);
        if (!$champ) {
            throw new ValidationFailedException([
                'champs_valeurs' => "Le champ avec l'ID {$item['champ_id']} n'existe pas."
            ]);
        }

        // Créer un input avec la nouvelle valeur
        $input = new Input();
        $input->setChamp($champ);
        $input->setValeur($item['valeur']);
        $this->entityManager->persist($input);

        // Associer le champ au bien s'il ne l'est pas déjà
        if (!$asset->getChamps()->contains($champ)) {
            $asset->addChamp($champ);
        }
    }
}

    /**
     * @param array<string, mixed> $payload
     */
    private function applyPayload(Asset $asset, array $payload): void
    {
        if (array_key_exists('reference', $payload)) {
            if ($this->isBlankReference($payload['reference'])) {
                // Ne jamais persister "null" / "" / "undefined"
                if (null === $asset->getReference()) {
                    $asset->setReference($this->assetRepository->generateNextReference());
                }
            } else {
                $ref = trim((string) $payload['reference']);
                if ($this->assetRepository->existsByReference($ref, $asset->getId())) {
                    throw new \InvalidArgumentException('Cette référence de bien est déjà utilisée.');
                }
                $asset->setReference($ref);
            }
        }

        if (array_key_exists('nom', $payload)) {
            $asset->setNom($this->nullableString($payload['nom']));
        }
        if (array_key_exists('numeroSerie', $payload)) {
            $asset->setNumeroSerie($this->nullableString($payload['numeroSerie']));
        }
        if (array_key_exists('description', $payload)) {
            $asset->setDescription($this->nullableString($payload['description']));
        }
        if (array_key_exists('code', $payload)) {
            $asset->setCode($this->nullableString($payload['code']));
        }
        if (array_key_exists('prixMercurial', $payload)) {
            $asset->setPrixMercurial($this->nullableString($payload['prixMercurial']));
        }

        if (array_key_exists('seuil', $payload)) {
            $asset->setSeuil($payload['seuil'] !== '' && $payload['seuil'] !== null ? (float) $payload['seuil'] : null);
        }

        if (array_key_exists('dateAcquisition', $payload)) {
            $raw = $this->nullableString($payload['dateAcquisition']);
            if (null === $raw) {
                $asset->setDateAcquisition(null);
            } else {
                try {
                    $asset->setDateAcquisition(new \DateTimeImmutable($raw));
                } catch (\Exception) {
                    throw new ValidationFailedException(['dateAcquisition' => 'La date d\'acquisition est invalide.']);
                }
            }
        }

        if (array_key_exists('valeur', $payload) || array_key_exists('valeurAcquisition', $payload)) {
            $raw = $payload['valeur'] ?? $payload['valeurAcquisition'] ?? null;
            $asset->setValeur($this->nullableString($raw));
        }

        // if (array_key_exists('sourceFinancement', $payload)) {
        //     $asset->setSourceFinancement($this->nullableString($payload['sourceFinancement']));
        // }
        if (array_key_exists('modeAcquisition', $payload)) {
            $asset->setModeAcquisition($this->nullableString($payload['modeAcquisition']));
        }
        if (array_key_exists('statut', $payload)) {
            $asset->setStatut($this->nullableString($payload['statut']));
        }

        if (array_key_exists('quantiteStock', $payload)) {
            $raw = $this->nullableString($payload['quantiteStock']);
            $asset->setQuantiteStock(null === $raw ? null : (int) $raw);
        }

        if (array_key_exists('typeFournisseur', $payload)) {
            $asset->setTypeFournisseur($this->nullableString($payload['typeFournisseur']));
        }
        if (array_key_exists('fournisseurNom', $payload)) {
            $asset->setFournisseurNom($this->nullableString($payload['fournisseurNom']));
        }
        if (array_key_exists('fournisseurEmail', $payload)) {
            $asset->setFournisseurEmail($this->nullableString($payload['fournisseurEmail']));
        }
        if (array_key_exists('fournisseurTelephone', $payload)) {
            $asset->setFournisseurTelephone($this->nullableString($payload['fournisseurTelephone']));
        }
        if (array_key_exists('fournisseurAdresse', $payload)) {
            $asset->setFournisseurAdresse($this->nullableString($payload['fournisseurAdresse']));
        }
        if (array_key_exists('fournisseurVille', $payload)) {
            $asset->setFournisseurVille($this->nullableString($payload['fournisseurVille']));
        }
        if (array_key_exists('fournisseurPays', $payload)) {
            $asset->setFournisseurPays($this->nullableString($payload['fournisseurPays']));
        }

        if ($this->hasRelationKey($payload, 'category_id', 'category_ids')) {
            $ids = $this->extractIds($payload, 'category_id', 'category_ids');
            $categories = [];
            foreach ($ids as $id) {
                $category = $this->categoryRepository->getActiveCategoryById($id);
                if (!$category) {
                    throw ResourceNotFoundException::forFeminine('catégorie');
                }
                $categories[] = $category;
            }
            $asset->syncCategories($categories);
        }

        if ($this->hasRelationKey($payload, 'asset_type_id', 'asset_type_ids')) {
            $ids = $this->extractIds($payload, 'asset_type_id', 'asset_type_ids');
            $types = [];
            foreach ($ids as $id) {
                $assetType = $this->assetTypeRepository->getActiveAssetTypeById($id);
                if (!$assetType) {
                    throw ResourceNotFoundException::for('type de bien');
                }
                $types[] = $assetType;
            }
            $asset->syncAssetTypes($types);
        }

        if ($this->hasRelationKey($payload, 'asset_sub_type_id', 'asset_sub_type_ids')) {
            $ids = $this->extractIds($payload, 'asset_sub_type_id', 'asset_sub_type_ids');
            $assetSubTypes = [];
            foreach ($ids as $id) {
                $assetSubType = $this->assetSubTypeRepository->find($id);
                if (!$assetSubType || $assetSubType->isDelete()) {
                    throw ResourceNotFoundException::for('sous-type de bien');
                }
                $assetSubTypes[] = $assetSubType;
            }
            $asset->syncAssetSubTypes($assetSubTypes);
        }

        if ($this->hasRelationKey($payload, 'etat_bien_id', 'etat_bien_ids')) {
            $ids = $this->extractIds($payload, 'etat_bien_id', 'etat_bien_ids');
            $etats = [];
            foreach ($ids as $id) {
                $etat = $this->etatBienRepository->getActiveById($id);
                if (!$etat) {
                    throw ResourceNotFoundException::for('état de bien');
                }
                $etats[] = $etat;
            }
            $asset->syncEtatBiens($etats);
        }

        if ($this->hasRelationKey($payload, 'service_id', 'service_ids')) {
            $ids = $this->extractIds($payload, 'service_id', 'service_ids');
            $services = [];
            foreach ($ids as $id) {
                $service = $this->serviceRepository->getServiceById($id);
                if (!$service || false === $service->isActive()) {
                    throw ResourceNotFoundException::for('service');
                }
                $services[] = $service;
            }
            $asset->syncServices($services);
        }

        if (array_key_exists('project_ids', $payload) || array_key_exists('project_id', $payload)) {
            $ids = $this->extractIds($payload, 'project_id', 'project_ids');
            $projects = [];
            foreach ($ids as $id) {
                $project = $this->projectRepository->find($id);
                if (!$project || $project->isDelete()) {
                    throw ResourceNotFoundException::for('projet');
                }
                $projects[] = $project;
            }
            $asset->syncProjects($projects);
        }

        // Traitement de latitude et longitude pour la localisation
        if ((array_key_exists('latitude', $payload) || array_key_exists('longitude', $payload))) {
            $latitude = isset($payload['latitude']) ? (float) $payload['latitude'] : null;
            $longitude = isset($payload['longitude']) ? (float) $payload['longitude'] : null;

            // Récupérer la localisation existante du bien
            $existingAssetLocation = $this->assetLocationRepository->findOneBy(['asset' => $asset]);
            $location = $existingAssetLocation ? $existingAssetLocation->getLocation() : null;

            // Si aucune coordonnée n'est fournie, ne rien faire
            if (null === $latitude && null === $longitude) {
                return;
            }

            // Validation des plages si fournies
            if (null !== $latitude && ($latitude < -90 || $latitude > 90)) {
                throw new ValidationFailedException([
                    'latitude' => 'La latitude doit être entre -90 et 90.'
                ]);
            }
            if (null !== $longitude && ($longitude < -180 || $longitude > 180)) {
                throw new ValidationFailedException([
                    'longitude' => 'La longitude doit être entre -180 et 180.'
                ]);
            }

            // Si une localisation existe, la mettre à jour
            if ($location) {
                if (null !== $latitude) {
                    $location->setLatitude($latitude);
                }
                if (null !== $longitude) {
                    $location->setLongitude($longitude);
                }
                
                // Valider la location
                $errors = $this->validator->validate($location);
                if (count($errors) > 0) {
                    $messages = [];
                    foreach ($errors as $error) {
                        $messages[$error->getPropertyPath()] = $error->getMessage();
                    }
                    throw new ValidationFailedException($messages);
                }

                $this->locationRepository->save($location);
            } else {
                // Si aucune localisation n'existe, en créer une nouvelle
                // Mais seulement si les deux coordonnées sont fournies
                if (null === $latitude || null === $longitude) {
                    throw new ValidationFailedException([
                        'location' => 'Pour créer une nouvelle localisation, la latitude et la longitude doivent être fournies ensemble.'
                    ]);
                }

                $location = new Location();
                $location->setLatitude($latitude);
                $location->setLongitude($longitude);

                // Valider la location
                $errors = $this->validator->validate($location);
                if (count($errors) > 0) {
                    $messages = [];
                    foreach ($errors as $error) {
                        $messages[$error->getPropertyPath()] = $error->getMessage();
                    }
                    throw new ValidationFailedException($messages);
                }

                $this->locationRepository->save($location);

                // Créer la relation asset_location
                $assetLocation = new AssetLocation();
                $assetLocation->setAsset($asset);
                $assetLocation->setLocation($location);
                $this->assetLocationRepository->save($assetLocation);
            }
        }
    }

    /**
     * @param list<UploadedFile> $photos
     * @param list<UploadedFile> $documents
     * @param list<?string> $documentLabels
     */
    private function attachFiles(Asset $asset, array $photos, array $documents, array $documentLabels): void
    {
        // Garantit un nom par document même si Swagger a envoyé une seule chaîne CSV
        $documentLabels = UploadedFilesNormalizer::parseLabelList($documentLabels);

        foreach ($photos as $photo) {
            if (!$photo instanceof UploadedFile) {
                continue;
            }
            if (UPLOAD_ERR_NO_FILE === $photo->getError()) {
                continue;
            }
            if (!$photo->isValid()) {
                throw new ValidationFailedException([
                    'photos' => 'Fichier photo invalide : ' . ($photo->getErrorMessage() ?: 'erreur d\'upload'),
                ]);
            }

            $piece = $this->fileUploadService->upload(
                $photo,
                FileUploadService::KIND_PHOTO,
                $photo->getClientOriginalName(),
                false
            );
            $asset->addPieceJointe($piece);
        }

        foreach (array_values($documents) as $index => $document) {
            if (!$document instanceof UploadedFile) {
                continue;
            }
            if (UPLOAD_ERR_NO_FILE === $document->getError()) {
                continue;
            }
            if (!$document->isValid()) {
                throw new ValidationFailedException([
                    'piecesJointes' => 'Fichier pièce jointe invalide : ' . ($document->getErrorMessage() ?: 'erreur d\'upload'),
                ]);
            }

            $label = $documentLabels[$index] ?? null;
            if (null === $label || '' === trim((string) $label)) {
                $label = $document->getClientOriginalName();
            }

            $piece = $this->fileUploadService->upload(
                $document,
                FileUploadService::KIND_DOCUMENT,
                (string) $label,
                false
            );
            $asset->addPieceJointe($piece);
        }
    }

    private function assertValid(Asset $asset): void
    {
        $errors = $this->validator->validate($asset);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }
            throw new ValidationFailedException($messages);
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }
        $string = trim((string) $value);
        if ('' === $string) {
            return null;
        }
        if (in_array(strtolower($string), ['null', 'undefined', 'none'], true)) {
            return null;
        }

        return $string;
    }

    /**
     * true si la référence doit être considérée comme absente.
     */
    private function isBlankReference(mixed $value): bool
    {
        if (null === $value) {
            return true;
        }
        if (is_array($value)) {
            return true;
        }
        $string = trim((string) $value);
        if ('' === $string) {
            return true;
        }

        return in_array(strtolower($string), ['null', 'undefined', 'none'], true);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hasRelationKey(array $payload, string $singular, string $plural): bool
    {
        return array_key_exists($singular, $payload) || array_key_exists($plural, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<int>
     */
    private function extractIds(array $payload, string $singular, string $plural): array
    {
        $raw = [];
        if (array_key_exists($plural, $payload)) {
            $value = $payload[$plural];
            if (is_array($value)) {
                $raw = $value;
            } elseif (null !== $value && '' !== $value) {
                // "1,2,3" ou valeur unique
                if (is_string($value) && str_contains($value, ',')) {
                    $raw = explode(',', $value);
                } else {
                    $raw = [$value];
                }
            }
        }
        if (array_key_exists($singular, $payload) && null !== $payload[$singular] && '' !== $payload[$singular]) {
            $raw[] = $payload[$singular];
        }

        $ids = [];
        foreach ($raw as $item) {
            if (null === $item || '' === $item) {
                continue;
            }
            $ids[] = (int) $item;
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    /**
     * ✅ Crée ou met à jour l'affectation d'un bien selon les règles
     * @param Asset $asset
     * @param array<string, mixed> $payload
     */
    // private function createOrUpdateAssignment(Asset $asset, array $payload): void
    // {
    //     $userId = $payload['user_id'] ?? null;
    //     $serviceId = $payload['service_id'] ?? null;

    //     // Si ni user_id ni service_id n'est fourni, ne rien faire
    //     if (null === $userId && null === $serviceId) {
    //         return;
    //     }

    //     // Récupérer la dernière affectation existante
    //     $lastAssignment = $this->assetAssignmentRepository->findLastActiveByAssetId($asset->getId());

    //     // Si aucune affectation n'existe, en créer une nouvelle
    //     if (!$lastAssignment) {
    //         $assignment = new \App\Entity\AssetAssignment();
    //         $assignment->setAsset($asset);
    //         $assignment->setTypeAffectation('AFFECTATION');
    //         $assignment->setDateDebut(new \DateTime());
    //         $assignment->setCommentaire('Affectation initiale lors de la création du bien.');
    //         $this->setAssignmentUserAndService($assignment, $userId, $serviceId);
    //         $this->entityManager->persist($assignment);
    //         $this->entityManager->flush();
    //         return;
    //     }

    //     // Mettre à jour l'affectation existante
    //     $this->setAssignmentUserAndService($lastAssignment, $userId, $serviceId);
    //     $lastAssignment->setUpdatedAt(new \DateTime());
    //     $this->entityManager->persist($lastAssignment);
    //     $this->entityManager->flush();
        
    // }

    // private function createOrUpdateAssignment(Asset $asset, array $payload): void
    private function createOrUpdateAssignment(Asset $asset, array $payload, ?\App\Entity\User $currentUser = null): void
    {
        $userId = $payload['user_id'] ?? null;
        $serviceId = $payload['service_id'] ?? null;

        // Si ni user_id ni service_id n'est fourni, ne rien faire
        if (null === $userId && null === $serviceId) {
            return;
        }

        // Récupérer la dernière affectation existante
        $lastAssignment = $this->assetAssignmentRepository->findLastActiveByAssetId($asset->getId());

        // Si aucune affectation n'existe, en créer une nouvelle
        if (!$lastAssignment) {
            $assignment = new \App\Entity\AssetAssignment();
            $assignment->setAsset($asset);
            $assignment->setTypeAffectation('AFFECTATION');
            $assignment->setDateDebut(new \DateTime());
            $assignment->setCommentaire('Affectation initiale lors de la création du bien.');
            $this->setAssignmentUserAndService($assignment, $userId, $serviceId);
            
            // ✅ Définir detenteur à true pour la première affectation
            $assignment->setDetenteur(true);

            // ✅ Définir le créateur de l'affectation
            if ($currentUser !== null) {
                $assignment->setCreatedBy($currentUser);
            }
            
            $this->entityManager->persist($assignment);
            $this->entityManager->flush();
            return;
        }

        // Mettre à jour l'affectation existante
        $this->setAssignmentUserAndService($lastAssignment, $userId, $serviceId);
        $lastAssignment->setUpdatedAt(new \DateTime());
        
        // ✅ Assurer que le détenteur est true (même si on met à jour)
        $lastAssignment->setDetenteur(true);
        
        $this->entityManager->persist($lastAssignment);
        $this->entityManager->flush();
    }

    /**
     * ✅ Définit l'utilisateur et le service d'une affectation selon les règles
     * @param \App\Entity\AssetAssignment $assignment
     * @param mixed $userId
     * @param mixed $serviceId
     */
    private function setAssignmentUserAndService(\App\Entity\AssetAssignment $assignment, mixed $userId, mixed $serviceId): void
    {
        // Priorité : user_id d'abord
        if (null !== $userId && '' !== $userId) {
            $user = $this->userRepository->find((int) $userId);
            if ($user) {
                $assignment->setUser($user);
                $assignment->setService($user->getService());
                return;
            }
        }

        // Sinon : service_id
        if (null !== $serviceId && '' !== $serviceId) {
            $service = $this->serviceRepository->getServiceById((int) $serviceId);
            if ($service) {
                $assignment->setService($service);
                // Si on a un service mais pas d'utilisateur, vider l'utilisateur
                $assignment->setUser(null);
                // Récupérer l'utilisateur actif lié à ce service
                $activeUser = $this->userRepository->findActiveByServiceId((int) $serviceId);
                if ($activeUser) {
                    $assignment->setUser($activeUser);
                }
                return;
            }
        }

        // Si les deux sont fournis
        if (null !== $userId && null !== $serviceId) {
            $user = $this->userRepository->find((int) $userId);
            $service = $this->serviceRepository->getServiceById((int) $serviceId);
            if ($user) {
                $assignment->setUser($user);
            }
            if ($service) {
                $assignment->setService($service);
            }
        }
    }

    /**
     * ✅ Crée automatiquement une sécurisation si securityMode est fourni lors de la création
     * Cette méthode ne doit être appelée que lors de la création d'un bien
     *
     * @param Asset $asset
     * @param array<string, mixed> $payload
     */
    private function createSecurityIfProvided(Asset $asset, array $payload): void
    {
        $securityMode = $payload['securityMode'] ?? null;
        
        // Ne créer une sécurisation que si securityMode est fourni et non vide
        if (null === $securityMode || '' === trim($securityMode)) {
            return;
        }

        // Créer l'entité Security
        $security = new Security();
        $security->setSecurityMode(trim($securityMode));
        $security->setDateSecurisation(new \DateTimeImmutable()); // Date du jour
        
        // Créer la liaison AssetSecurity
        $assetSecurity = new AssetSecurity();
        $assetSecurity->setAsset($asset);
        $assetSecurity->setSecurity($security);
        
        // Persister les entités
        $this->entityManager->persist($security);
        $this->entityManager->persist($assetSecurity);
    }
}
