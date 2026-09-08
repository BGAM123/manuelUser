<?php

namespace App\Service;

use App\Entity\Asset;
use App\Entity\PieceJointe;
use App\Entity\Service;
use App\Entity\User;
use App\Entity\Project;
use App\Repository\AssetLocationRepository;
use App\Repository\AssetMaintenanceRepository;
use App\Repository\UserRepository;
use App\Repository\SecurityRepository;
use App\Service\AssetDepreciation\AssetDepreciationCalculator;
use Doctrine\ORM\EntityNotFoundException;

/**
 * Formate les réponses Asset selon le contrat API métier.
 */
final class AssetResponseBuilder
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly SecurityRepository $securityRepository,
        private readonly ProjectExerciseService $exerciseService,
        private readonly AssetDepreciationCalculator $depreciationCalculator,
        private readonly AssetLocationRepository $assetLocationRepository,
        private readonly AssetMaintenanceRepository $assetMaintenanceRepository,
    ) {
    }

    /**
     * Maintenance ouverte la plus récente d'un bien, ou null. Même contenu que le bloc
     * 'maintenance' de ListAssetMaintenancesController (src/Controller/Assets/).
     *
     * @return array{id: int, etatBien: array|null, motif: ?string, cout: ?string, dateIntervention: ?string, observations: ?string}|null
     */
    private function resolveOpenMaintenance(Asset $asset): ?array
    {
        $maintenance = $this->assetMaintenanceRepository->findOpenForAsset($asset);
        if (!$maintenance) {
            return null;
        }

        $etatBien = $maintenance->getEtatBien();

        return [
            'id' => $maintenance->getId(),
            'etatBien' => $etatBien ? ['id' => $etatBien->getId(), 'nom' => $etatBien->getNom()] : null,
            'motif' => $maintenance->getMotif(),
            'cout' => $maintenance->getCout(),
            'dateIntervention' => $maintenance->getDateIntervention()?->format('Y-m-d'),
            'observations' => $maintenance->getObservations(),
        ];
    }

    /**
     * Dernière localisation connue d'un bien, même forme que
     * GetAssetCurrentLocationController::__invoke().
     *
     * @return array{id: int, latitude: mixed, longitude: mixed, geometry_type: mixed, geometry: mixed}|null
     */
    private function resolveLocation(Asset $asset): ?array
    {
        $assetLocation = $this->assetLocationRepository->findLatestByAsset($asset->getId());
        if (!$assetLocation) {
            return null;
        }

        $location = $assetLocation->getLocation();

        return [
            'id' => $location->getId(),
            'latitude' => $location->getLatitude(),
            'longitude' => $location->getLongitude(),
            'geometry_type' => $location->getGeometryType(),
            'geometry' => $location->getGeometry(),
        ];
    }

    /**
     * Résout le détenteur actuel (service + utilisateur) depuis la dernière
     * affectation du bien. Les relations user/service de AssetAssignment sont
     * lazy-loaded par Doctrine : si l'une pointe vers une ligne supprimée en
     * base hors ORM (ex: SQL manuel), l'initialisation du proxy lève
     * EntityNotFoundException. On l'intercepte pour ignorer l'affectation
     * plutôt que de faire échouer tout l'appel (liste ou détail).
     *
     * @return array{0: ?Service, 1: ?User}
     */
    private function resolveCurrentHolder(Asset $asset): array
    {
        $lastAssignment = $asset->getAssignments()->last();
        if (!$lastAssignment) {
            return [null, null];
        }

        try {
            $service = null;
            $user = null;

            // Priorité : utilisateur d'abord
            if ($lastAssignment->getUser()) {
                $user = $lastAssignment->getUser();
                $service = $user->getService();
            }
            // Sinon : service
            elseif ($lastAssignment->getService()) {
                $service = $lastAssignment->getService();
                // Récupérer l'utilisateur actif lié à ce service
                $user = $this->userRepository->findActiveByServiceId((int) $service->getId());
            }

            return [$service, $user];
        } catch (EntityNotFoundException) {
            return [null, null];
        }
    }

    /**
     * ✅ Résout le statut d'accusé de réception du détenteur actuel du bien
     * Ce champ est dynamique et basé uniquement sur l'affectation actuelle (detenteur = true)
     *
     * @return bool|null
     */
    private function resolveCurrentReceived(Asset $asset): ?bool
    {
        $lastAssignment = $asset->getAssignments()->last();
        if (!$lastAssignment) {
            return null;
        }

        // Vérifier que c'est bien l'affectation du détenteur actuel
        if (!$lastAssignment->isDetenteur()) {
            return null;
        }

        return $lastAssignment->isReceived();
    }

    /**
     * ✅ Détermine si un bien est actuellement restitué
     * Un bien est restitué si sa dernière affectation est de type RESTITUTION, detenteur = true, et dateFin IS NULL
     *
     * @return bool
     */
    private function isAssetRestitue(Asset $asset): bool
    {
        $lastAssignment = $asset->getAssignments()->last();
        if (!$lastAssignment) {
            return false;
        }

        return $lastAssignment->getTypeAffectation() === 'RESTITUTION'
            && $lastAssignment->isDetenteur()
            && $lastAssignment->getDateFin() === null;
    }

    /**
     * Listing allégé (sans photos, PJ, fournisseur).
     *
     * @return array<string, mixed>
     */
    public function buildListItem(Asset $asset): array
    {
        // ✅ Récupérer l'affectation actuelle depuis asset_assignment
        [$service, $user] = $this->resolveCurrentHolder($asset);

        $project = $asset->getProjects()->first() ?: null;
        $category = $asset->getCategories()->first() ?: null;
        $type = $asset->getAssetTypes()->first() ?: null;
        $etat = $asset->getEtatBiens()->first() ?: null;

        $firstProject = $asset->getProjects()->first() ?: null;
         // ✅ Vérifier si le bien est sécurisé
        $isSecurised = $this->isAssetSecurised($asset);

        // ✅ Résoudre le statut d'accusé de réception du détenteur actuel
        $received = $this->resolveCurrentReceived($asset);

        // ✅ Déterminer si le bien est restitué
        $isRestitue = $this->isAssetRestitue($asset);

        // ✅ Déterminer si le bien doit être restitué (basé sur serviceRestitution)
        $doitEtreRestitue = $asset->getServiceRestitution() !== null;

        // ✅ Calculer l'exercice à partir du projet
        $exercice = null;
        if ($firstProject && !$firstProject->isDelete()) {
            $exercice = $this->exerciseService->getExerciseForApi($firstProject);
        }
        // Si aucun projet n'est associé, l'exercice reste null

        return [
            'id' => $asset->getId(),
            'reference' => $asset->getReference(),
            'nom' => $asset->getNom(),
            'projet' => $project && !$project->isDelete() ? [
                'id' => $project->getId(),
                'nom' => $project->getNom(),
            ] : null,
            'categorie' => $category ? [
                'id' => (int) $category->getId(),
                'nom' => (string) $category->getNom(),
            ] : null,
            'typeBien' => $type ? [
                'id' => (int) $type->getId(),
                'nom' => (string) $type->getNom(),
            ] : null,
            'structure' => $service ? [
                'id' => (int) $service->getId(),
                'nom' => (string) $service->getNom(),
            ] : null,
            'responsable' => $this->normalizeResponsable($user),
            'statut' => $asset->getStatut(),
            // 'quantiteStock' => $asset->getQuantiteStock(),
            // 'sourceFinancement' => $asset->getSourceFinancement(),
            'sourceFinancement' => $firstProject && !$firstProject->isDelete() ? $firstProject->getNom() : null,
            'exercice' => $exercice,
            'valeur' => $this->normalizeValeur($asset->getValeur()),
            'unite_mesure' => $this->normalizeValeur($asset->getUnite_mesure()),
            'valeurInitiale' => $asset->getValeurInitiale(),
            'activeAmortissement' => $asset->isActiveAmortissement(),
            'activeReevaluation' => $asset->isActiveReevaluation(),
            // 'seuil' => $asset->getSeuil(),
            'dateAcquisition' => $asset->getDateAcquisition()?->format('Y-m-d'),
            'etatBien' => $etat ? [
                'id' => (int) $etat->getId(),
                'nom' => (string) $etat->getNom(),
            ] : null,
            'champs' => $this->buildChampslist($asset),
            // ✅ Ajout du champ securise
            'securise' => $isSecurised,
            // ✅ Ajout du champ received (dynamique, basé sur détenteur actuel)
            'received' => $received,
            // ✅ Ajout du champ isRestitue (dynamique, basé sur l'affectation actuelle)
            'isRestitue' => $isRestitue,
            // ✅ Ajout du champ doitEtreRestitue (basé sur serviceRestitution)
            'doitEtreRestitue' => $doitEtreRestitue,
            'location' => $this->resolveLocation($asset),
            'maintenanceEnCours' => $this->resolveOpenMaintenance($asset),
            // 'coutTotalMaintenance' => $this->assetMaintenanceRepository->getTotalMaintenanceCostForAsset($asset),
            'createdBy' => $this->normalizeAuditUser($asset->getCreatedBy()),
        ];
    }

    public function buildListUserItem(Asset $asset): array
    {
        // ✅ Récupérer l'affectation actuelle depuis asset_assignment
        [$service, $user] = $this->resolveCurrentHolder($asset);

        $project = $asset->getProjects()->first() ?: null;
        $category = $asset->getCategories()->first() ?: null;
        $type = $asset->getAssetTypes()->first() ?: null;
        $etat = $asset->getEtatBiens()->first() ?: null;

        $firstProject = $asset->getProjects()->first() ?: null;
         // ✅ Vérifier si le bien est sécurisé
        $isSecurised = $this->isAssetSecurised($asset);

        // ✅ Calculer l'exercice à partir du projet
        $exercice = null;
        if ($firstProject && !$firstProject->isDelete()) {
            $exercice = $this->exerciseService->getExerciseForApi($firstProject);
        }
        // Si aucun projet n'est associé, l'exercice reste null

        return [
            'id' => $asset->getId(),
            'reference' => $asset->getReference(),
            'nom' => $asset->getNom(),
            'categorie' => $category ? [
                'id' => (int) $category->getId(),
                'nom' => (string) $category->getNom(),
            ] : null,
            'typeBien' => $type ? [
                'id' => (int) $type->getId(),
                'nom' => (string) $type->getNom(),
            ] : null,
            'subtypeBien' => $etat ? [
                'id' => (int) $etat->getId(),
                'nom' => (string) $etat->getNom(),
            ] : null,
            'structure' => $service ? [
                'id' => (int) $service->getId(),
                'nom' => (string) $service->getNom(),
            ] : null,
            'responsable' => $this->normalizeResponsable($user),
            'statut' => $asset->getStatut(),
            'sourceFinancement' => $firstProject && !$firstProject->isDelete() ? $firstProject->getNom() : null,
            'exercice' => $exercice,
            'valeur' => $this->normalizeValeur($asset->getValeur()),
            'valeurInitiale' => $asset->getValeurInitiale(),
            'dateAcquisition' => $asset->getDateAcquisition()?->format('Y-m-d'),
            'etatBien' => $etat ? [
                'id' => (int) $etat->getId(),
                'nom' => (string) $etat->getNom(),
            ] : null,
            'securise' => $isSecurised,
            'activeAmortissement' => $asset->isActiveAmortissement(),
            'activeReevaluation' => $asset->isActiveReevaluation(),
            // 'seuil' => $asset->getSeuil(),
            'location' => $this->resolveLocation($asset),
            'createdBy' => $this->normalizeAuditUser($asset->getCreatedBy()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDetail(Asset $asset): array
    {
        // ✅ Récupérer l'affectation actuelle depuis asset_assignment
        [$service, $user] = $this->resolveCurrentHolder($asset);

        // ✅ Résoudre le statut d'accusé de réception du détenteur actuel
        $received = $this->resolveCurrentReceived($asset);

        // ✅ Déterminer si le bien est restitué
        $isRestitue = $this->isAssetRestitue($asset);

        // ✅ Déterminer si le bien doit être restitué (basé sur serviceRestitution)
        $doitEtreRestitue = $asset->getServiceRestitution() !== null;

        $photos = [];
        $documents = [];
        foreach ($asset->getPiecesJointes() as $piece) {
            $item = $this->normalizePieceJointe($piece);
            if ($piece->isPhoto()) {
                $photos[] = $item;
            } else {
                $documents[] = $item;
            }
        }

        $projects = [];
        foreach ($asset->getProjects() as $project) {
            if ($project->isDelete()) {
                continue;
            }
            $projects[] = [
                'id' => $project->getId(),
                'nom' => $project->getNom(),
            ];
        }

        $category = $asset->getCategories()->first() ?: null;
        $type = $asset->getAssetTypes()->first() ?: null;
        $subType = $asset->getAssetSubTypes()->first() ?: null;
        $etat = $asset->getEtatBiens()->first() ?: null;

        // ✅ Récupérer les sécurisations du bien
        // $securities = $this->getSecuritiesForAsset($asset);
        $lastSecurity = $this->getLastSecurityForAsset($asset);
        // ✅ Récupérer le premier projet comme source de financement (nom uniquement)
        $firstProject = $asset->getProjects()->first() ?: null;

        // ✅ Calculer l'exercice à partir du projet
        $exercice = null;
        if ($firstProject && !$firstProject->isDelete()) {
            $exercice = $this->exerciseService->getExerciseForApi($firstProject);
        }
        // Si aucun projet n'est associé, l'exercice reste null

        // ✅ Calculer l'amortissement
        $depreciationResult = $this->depreciationCalculator->calculate($asset);

        $data = [
            'id' => $asset->getId(),
            'reference' => $asset->getReference(),
            'nom' => $asset->getNom(),
            'numeroSerie' => $asset->getNumeroSerie(),
            'description' => $asset->getDescription(),
            'code' => $asset->getCode(),
            'prixMercurial' => $this->normalizeValeur($asset->getPrixMercurial()),
            'statut' => $asset->getStatut(),
            'quantiteStock' => $asset->getQuantiteStock(),
            'valeur' => $this->normalizeValeur($asset->getValeur()),
            'unite_mesure' => $this->normalizeValeur($asset->getUnite_mesure()),
            'valeurInitiale' => $asset->getValeurInitiale(),
            'dateAcquisition' => $asset->getDateAcquisition()?->format('Y-m-d'),
            'exercice' => $exercice,
            'sourceFinancement' => $firstProject && !$firstProject->isDelete() ? $firstProject->getNom() : null,
            'modeAcquisition' => $asset->getModeAcquisition(),
            'champs' => $this->buildChamps($asset), // ✅ Ajouter cette ligne
            'activeAmortissement' => $asset->isActiveAmortissement(),
            'activeReevaluation' => $asset->isActiveReevaluation(),
            'activeDepreciation' => $asset->isActiveDepreciation(),
            'serviceRestitution' => $asset->getServiceRestitution() ? [
                'id' => $asset->getServiceRestitution()->getId(),
                'nom' => $asset->getServiceRestitution()->getNom(),
            ] : null,
            // 'seuil' => $asset->getSeuil(),
            // 'amortissement' => [
            //     'valeurAcquisition' => $depreciationResult->valeurAcquisition,
            //     'valeurActuelle' => $depreciationResult->valeurActuelle,
            //     'amortissementAnnuel' => $depreciationResult->amortissementAnnuel,
            //     'amortissementCumule' => $depreciationResult->amortissementCumule,
            //     'dureeVie' => $depreciationResult->dureeVie,
            //     'taux' => $depreciationResult->taux,
            //     'anneesEcoulees' => $depreciationResult->anneesEcoulees,
            //     'dateAcquisition' => $depreciationResult->dateAcquisition,
            // ],
            'categorie' => $category ? [
                'id' => (int) $category->getId(),
                'nom' => (string) $category->getNom(),
            ] : null,
            'typeBien' => $type ? [
                'id' => (int) $type->getId(),
                'nom' => (string) $type->getNom(),
                'dureeVie' => $type->getDureeVie(),
                'taux' => $this->normalizeValeur($type->getTaux()),
            ] : null,
            'assetSubType' => $subType ? [
                'id' => (int) $subType->getId(),
                'nom' => (string) $subType->getNom(),
            ] : null,
            'etatBien' => $etat ? [
                'id' => (int) $etat->getId(),
                'nom' => (string) $etat->getNom(),
            ] : null,
            'projets' => $projects,
            'service' => $this->normalizeServiceDetail($service),
            'utilisateur' => $this->normalizeUtilisateurDetail($user),
            'fournisseur' => [
                'type' => $asset->getTypeFournisseur(),
                'nom' => $asset->getFournisseurNom(),
                'email' => $asset->getFournisseurEmail(),
                'telephone' => $asset->getFournisseurTelephone(),
                'adresse' => $asset->getFournisseurAdresse(),
                'ville' => $asset->getFournisseurVille(),
                'pays' => $asset->getFournisseurPays(),
            ],
            'photos' => $photos,
            'piecesJointes' => $documents,
            'affectations' => $this->buildAffectations($asset),
            'maintenances' => $this->buildMaintenances($asset),
            'reevaluations' => $this->buildReevaluations($asset),
            'depreciations' => $this->buildDepreciations($asset),
            'sortie' => $this->buildSortie($asset),
             // ✅ Ajouter le bloc sécurisation
            'securisations' => $lastSecurity,
            // ✅ Ajout du champ received (dynamique, basé sur détenteur actuel)
            'received' => $received,
            // ✅ Ajout du champ isRestitue (dynamique, basé sur l'affectation actuelle)
            'isRestitue' => $isRestitue,
            // ✅ Ajout du champ doitEtreRestitue (basé sur serviceRestitution)
            'doitEtreRestitue' => $doitEtreRestitue,
            'coutTotalMaintenance' => $this->assetMaintenanceRepository->getTotalMaintenanceCostForAsset($asset),
            'createdAt' => $asset->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $asset->getUpdatedAt()?->format('Y-m-d H:i:s'),
            // 'createdBy' => $this->normalizeAuditUser($asset->getCreatedBy()),
            // 'updatedBy' => $this->normalizeAuditUser($asset->getUpdatedBy()),
        ];


        // ✅ Ajouter le bloc amortissement UNIQUEMENT si activeAmortissement est true
    if ($asset->isActiveAmortissement()) {
        $depreciationResult = $this->depreciationCalculator->calculate($asset);
        $data['amortissement'] = [
                'valeurAcquisition' => $depreciationResult->valeurAcquisition,
                'valeurActuelle' => $depreciationResult->valeurActuelle,
                'amortissementAnnuel' => $depreciationResult->amortissementAnnuel,
                'amortissementCumule' => $depreciationResult->amortissementCumule,
                'dureeVie' => $depreciationResult->dureeVie,
                'taux' => $depreciationResult->taux,
                'anneesEcoulees' => $depreciationResult->anneesEcoulees,
                'dateAcquisition' => $depreciationResult->dateAcquisition,
        ];
    } else {
        $data['amortissement'] = null; // Optionnel : définir à null pour une réponse cohérente
    }

    return $data;
    }


    /**
     * ✅ Récupère la DERNIÈRE sécurisation d'un bien
     * @param Asset $asset
     * @return array<string, mixed>|null
     */
    private function getLastSecurityForAsset(Asset $asset): ?array
    {
        // Récupérer la dernière sécurisation active pour ce bien
        $security = $this->securityRepository->findLastActiveByAssetId($asset->getId());

        if (!$security) {
            return null;
        }

        return $this->normalizeSecurity($security);
    }

    /**
     * ✅ Vérifie si un bien est sécurisé
     * @param Asset $asset
     * @return bool
     */
    private function isAssetSecurised(Asset $asset): bool
    {
        // Vérifier si le bien a au moins une sécurisation active
        $security = $this->securityRepository->findLastActiveByAssetId($asset->getId());
        return $security !== null;
    }

     /**
     * ✅ Construit les champs personnalisés avec leurs valeurs
     * @param Asset $asset
     * @return array<int, array<string, mixed>>
     */
    private function buildChamps(Asset $asset): array
    {
        $result = [];

        // ✅ Récupérer les champs du bien
        foreach ($asset->getChamps() as $champ) {
            if ($champ->isDelete()) {
                continue;
            }

            // ✅ Récupérer les inputs du champ
            $inputs = [];
            foreach ($champ->getInputs() as $input) {
                if (!$input->isDelete()) {
                    $inputs[] = [
                        'id' => $input->getId(),
                        'valeur' => $input->getValeur(),
                        // 'createdAt' => $input->getCreatedAt()?->format('Y-m-d H:i:s'),
                        // 'updatedAt' => $input->getUpdatedAt()?->format('Y-m-d H:i:s'),
                    ];
                }
            }

            $result[] = [
                'id' => $champ->getId(),
                'nom' => $champ->getNom(),
                'type' => $champ->getType(),
                'sousType' => $champ->getSubtype(),
                'option' => $champ->getOption(),
                'inputs' => $inputs,
            ];
        }

        return $result;
    }
    private function buildChampslist(Asset $asset): array
    {
        $result = [];

        // ✅ Récupérer les champs du bien
        foreach ($asset->getChamps() as $champ) {
            if ($champ->isDelete()) {
                continue;
            }

            // ✅ Récupérer les inputs du champ
            $inputs = [];
            foreach ($champ->getInputs() as $input) {
                if (!$input->isDelete()) {
                    $inputs[] = [
                        'id' => $input->getId(),
                        'valeur' => $input->getValeur(),
                        // 'createdAt' => $input->getCreatedAt()?->format('Y-m-d H:i:s'),
                        // 'updatedAt' => $input->getUpdatedAt()?->format('Y-m-d H:i:s'),
                    ];
                }
            }

            $result[] = [
                'id' => $champ->getId(),
                'nom' => $champ->getNom(),
                'type' => $champ->getType(),
                'sousType' => $champ->getSubtype(),
                'option' => $champ->getOption(),
                // 'inputs' => $inputs,
            ];
        }

        return $result;
    }


    /**
     * ✅ Récupère toutes les sécurisations d'un bien
     * @return array<int, array<string, mixed>>
     */
    // private function getSecuritiesForAsset(Asset $asset): array
    // {
    //     // Récupérer toutes les sécurisations actives pour ce bien
    //     $securities = $this->securityRepository->findActiveByAssetId($asset->getId());

    //     $result = [];
    //     foreach ($securities as $security) {
    //         $result[] = $this->normalizeSecurity($security);
    //     }

    //     return $result;
    // }

    /**
     * ✅ Normalise une sécurisation
     * @return array<string, mixed>
     */
    private function normalizeSecurity($security): array
    {
        // Récupérer la localisation
        $location = null;
        foreach ($security->getAssetSecurities() as $assetSecurity) {
            if (!$assetSecurity->isDelete()) {
                $asset = $assetSecurity->getAsset();
                if ($asset) {
                    $assetLocation = $asset->getAssetLocations()->first();
                    if ($assetLocation && $assetLocation->getLocation()) {
                        $loc = $assetLocation->getLocation();
                        $location = [
                            'id' => $loc->getId(),
                            'latitude' => $loc->getLatitude(),
                            'longitude' => $loc->getLongitude(),
                        ];
                        break;
                    }
                }
            }
        }

        // Récupérer les documents de la sécurisation
        $documents = [];
        foreach ($security->getSecurityDocuments() as $securityDocument) {
            if (!$securityDocument->isDelete()) {
                $piece = $securityDocument->getPieceJointe();
                if ($piece) {
                    $documents[] = [
                        'id' => $piece->getId(),
                        'nom' => $piece->getNom(),
                        'chemin' => $piece->getChemin(),
                        // 'isPhoto' => $piece->isPhoto(),
                    ];
                }
            }
        }

        return [
            'id' => $security->getId(),
            'securityMode' => $security->getSecurityMode(), // ✅ Directement le texte
            'dateSecurisation' => $security->getDateSecurisation()?->format('Y-m-d'),
            'location' => $location,
            'documents' => $documents,
            // 'createdAt' => $security->getCreatedAt()?->format('Y-m-d H:i:s'),
            // 'updatedAt' => $security->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }


    /**
     * @return array{id: int, nom: string, prenom: string}|null
     */
    private function normalizeResponsable(?User $user): ?array
    {
        if (null === $user) {
            return null;
        }

        return [
            'id' => (int) $user->getId(),
            'nom' => (string) $user->getLastName(),
            'prenom' => (string) $user->getFirstName(),
            'matricule' => $user->getMatricule(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeServiceDetail(?\App\Entity\Service $service): ?array
    {
        if (null === $service) {
            return null;
        }

        return [
            'id' => (int) $service->getId(),
            'nom' => (string) $service->getNom(),
            'sigle' => $service->getSigle(),
            'typeService' => $service->getTypeService(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeUtilisateurDetail(?User $user): ?array
    {
        if (null === $user) {
            return null;
        }

        $assignedRoles = [];
        foreach ($user->getAssignedRoles() as $role) {
            if ($role->isDelete()) {
                continue;
            }
            $assignedRoles[] = [
                'id' => $role->getId(),
                'nom' => $role->getNom(),
            ];
        }

        $service = $user->getService();

        return [
            'id' => (int) $user->getId(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'email' => $user->getEmail(),
            // 'assignedRoles' => $assignedRoles,
            'service' => $service ? [
                'id' => (int) $service->getId(),
                'nom' => (string) $service->getNom(),
                // 'typeService' => $service->getTypeService(),
            ] : null,
        ];
    }

    /**
     * @return array{id: ?int, nom: ?string, chemin: ?string}
     */
    private function normalizePieceJointe(PieceJointe $piece): array
    {
        return [
            'id' => $piece->getId(),
            'nom' => $piece->getNom(),
            'chemin' => $piece->getChemin(),
        ];
    }

    /**
     * Normalise un utilisateur pour l'audit (createdBy/updatedBy)
     * Retourne une représentation simple sans informations sensibles
     * @return array{id: int, name: string}|null
     */
    private function normalizeAuditUser(?User $user): ?array
    {
        if (null === $user) {
            return null;
        }

        return [
            'id' => (int) $user->getId(),
            'name' => trim($user->getFirstName() . ' ' . $user->getLastName()),
            'service' => $user->getService() ? [
                'id' => (int) $user->getService()->getId(),
                'nom' => (string) $user->getService()->getNom(),
            ] : null,
        ];
    }

    private function normalizeValeur(?string $valeur): int|float|string|null
    {
        if (null === $valeur || '' === $valeur) {
            return null;
        }
        if (is_numeric($valeur)) {
            return (float) $valeur;
        }
        return $valeur;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildAffectations(Asset $asset): array
    {
        $result = [];
        foreach ($asset->getAssignments() as $assignment) {
            // ✅ Récupérer le service du détenteur (service de l'utilisateur si existe, sinon service de l'affectation)
            $service = null;
            if ($assignment->getUser() && $assignment->getUser()->getService()) {
                $service = $assignment->getUser()->getService();
            } elseif ($assignment->getService()) {
                $service = $assignment->getService();
            }

            // ✅ Récupérer l'origine (créateur de l'affectation) et son service
            $origine = null;
            if ($assignment->getCreatedBy()) {
                $origine = [
                    'id' => $assignment->getCreatedBy()->getId(),
                    'firstName' => $assignment->getCreatedBy()->getFirstName(),
                    'lastName' => $assignment->getCreatedBy()->getLastName(),
                    'service' => $assignment->getCreatedBy()->getService() ? [
                        'id' => $assignment->getCreatedBy()->getService()->getId(),
                        'nom' => $assignment->getCreatedBy()->getService()->getNom(),
                    ] : null,
                ];
            }

            $result[] = [
                'id' => $assignment->getId(),
                'typeAffectation' => $assignment->getTypeAffectation(),
                'dateDebut' => $assignment->getDateDebut()?->format('Y-m-d'),
                'dateFin' => $assignment->getDateFin()?->format('Y-m-d'),
                'commentaire' => $assignment->getCommentaire(),
                'service' => $service ? [
                    'id' => $service->getId(),
                    'nom' => $service->getNom(),
                ] : null,
                'utilisateur' => $assignment->getUser() ? [
                    'id' => $assignment->getUser()->getId(),
                    'firstName' => $assignment->getUser()->getFirstName(),
                    'lastName' => $assignment->getUser()->getLastName(),
                ] : null,
                'origine' => $origine,
                'createdAt' => $assignment->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        // ✅ Trier les affectations par ordre décroissant de création (les plus récentes en premier)
        usort($result, function ($a, $b) {
            return strtotime($b['createdAt'] ?? '0') - strtotime($a['createdAt'] ?? '0');
        });

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildMaintenances(Asset $asset): array
    {
        $result = [];
        foreach ($asset->getMaintenances() as $maintenance) {
            $result[] = [
                'id' => $maintenance->getId(),
                'etatBien' => $maintenance->getEtatBien() ? [
                    'id' => $maintenance->getEtatBien()->getId(),
                    'nom' => $maintenance->getEtatBien()->getNom(),
                ] : null,
                'motif' => $maintenance->getMotif(),
                'cout' => $this->normalizeValeur($maintenance->getCout()),
                'dateIntervention' => $maintenance->getDateIntervention()?->format('Y-m-d'),
                // Unifiée : réelle si la maintenance est terminée, sinon estimée (si connue).
                'dateRecuperation' => $maintenance->getDateRecuperationUnifiee()?->format('Y-m-d'),
                'dateRecuperationPrevue' => $maintenance->getDateRecuperationPrevue()?->format('Y-m-d'),
                'dateRecuperationReelle' => $maintenance->getDateRecuperation()?->format('Y-m-d'),
                'observations' => $maintenance->getObservations(),
                'statut' => $maintenance->getStatut(),
                'alerteCoutEleve' => $this->isCoutAboveValeurAcquisition($maintenance->getCout(), $this->resolveValeurAcquisition($asset)),
                'createdAt' => $maintenance->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        // ✅ Trier les maintenances par ordre décroissant de création (les plus récentes en premier)
        usort($result, function ($a, $b) {
            return strtotime($b['createdAt'] ?? '0') - strtotime($a['createdAt'] ?? '0');
        });

        return $result;
    }

    /**
     * Le coût d'une maintenance dépasse-t-il la valeur d'acquisition du bien ?
     */
    private function isCoutAboveValeurAcquisition(?string $cout, ?string $valeurAcquisition): bool
    {
        if (!is_numeric($cout) || !is_numeric($valeurAcquisition)) {
            return false;
        }

        return (float) $cout > (float) $valeurAcquisition;
    }

    /**
     * Résout la valeur d'acquisition du bien pour la comparaison des coûts de maintenance.
     * Utilise directement la valeur actuelle du bien.
     */
    private function resolveValeurAcquisition(Asset $asset): ?string
    {
        return $asset->getValeur();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildReevaluations(Asset $asset): array
    {
        $result = [];
        foreach ($asset->getReevaluations() as $reevaluation) {
            $result[] = [
                'id' => $reevaluation->getId(),
                // 'valeurActuelle' => $this->normalizeValeur($reevaluation->getValeurActuelle()),
                'nouvelleValeur' => $this->normalizeValeur($reevaluation->getNouvelleValeur()),
                'methodeEvaluation' => $reevaluation->getMethodeEvaluation(),
                'service' => $reevaluation->getService() ? [
                    'id' => $reevaluation->getService()->getId(),
                    'nom' => $reevaluation->getService()->getNom(),
                ] : null,
                'dateReevaluation' => $reevaluation->getDateReevaluation()?->format('Y-m-d'),
                'motif' => $reevaluation->getMotif(),
                'observations' => $reevaluation->getObservations(),
                'createdAt' => $reevaluation->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }
        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildDepreciations(Asset $asset): array
    {
        $result = [];
        foreach ($asset->getDepreciations() as $depreciation) {
            $result[] = [
                'id' => $depreciation->getId(),
                'typeDepreciation' => $depreciation->getTypeDepreciation(),
                'methodeAmortissement' => $depreciation->getMethodeAmortissement(),
                'dureeVie' => $depreciation->getDureeVie(),
                // 'valeurActuelle' => $this->normalizeValeur($depreciation->getValeurActuelle()),
                'tauxDepreciation' => $this->normalizeValeur($depreciation->getTauxDepreciation()),
                'montantDepreciation' => $this->normalizeValeur($depreciation->getMontantDepreciation()),
                'dateDepreciation' => $depreciation->getDateDepreciation()?->format('Y-m-d'),
                'motif' => $depreciation->getMotif(),
                'observations' => $depreciation->getObservations(),
                'createdAt' => $depreciation->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }
        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildSortie(Asset $asset): ?array
    {
        $sortie = $asset->getSortie();
        if (!$sortie) {
            return null;
        }

        // Récupérer les pièces jointes de la sortie
        $piecesJointes = [];
        foreach ($sortie->getPieceJointes() as $piece) {
            $piecesJointes[] = [
                'id' => $piece->getId(),
                'nom' => $piece->getNom(),
                'chemin' => $piece->getChemin(),
            ];
        }

        return [
            'id' => $sortie->getId(),
            'dateSortie' => $sortie->getDateSortie()?->format('Y-m-d'),
            'motif' => $sortie->getMotifSortie(),
            'observations' => $sortie->getObservations(),
            'service' => $sortie->getService() ? [
                'id' => $sortie->getService()->getId(),
                'nom' => $sortie->getService()->getNom(),
            ] : null,
            'exitType' => $sortie->getExitType() ? [
                'id' => $sortie->getExitType()->getId(),
                'nom' => $sortie->getExitType()->getNom(),
            ] : null,
            'piecesJointes' => $piecesJointes,
            'createdAt' => $sortie->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
