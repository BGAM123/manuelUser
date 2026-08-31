<?php

namespace App\Service;

use App\Entity\AssetAssignment;
use App\Entity\Service;
use App\Entity\User;
use App\Entity\Project;
use App\Repository\AssetLocationRepository;
use App\Repository\AssetMaintenanceRepository;
use App\Repository\SecurityRepository;
use App\Repository\UserRepository;
use App\Service\AssetDepreciation\AssetDepreciationCalculator;
use Doctrine\ORM\EntityNotFoundException;

/**
 * Formate les réponses AssetAssignment pour le listing avec la même structure que les biens
 */
final class AssetAssignmentListResponseBuilder
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
     * Listing des affectations avec la même structure que le listing des biens
     * @return array<string, mixed>
     */
    public function buildListItem(AssetAssignment $assignment, ?User $currentUser = null): array
    {
        $asset = $assignment->getAsset();
        if (!$asset) {
            return [];
        }

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

        // ✅ Vérifier si l'affectation est accusée réception (champ received)
        $isReceived = $assignment->isReceived();

        // ✅ Vérifier si l'utilisateur connecté est le détenteur actuel
        $isCurrentUserDetenteur = false;
        if ($currentUser && $assignment->isDetenteur()) {
            $recipient = $this->resolveRecipient($assignment);
            if ($recipient && $recipient->getId() === $currentUser->getId()) {
                $isCurrentUserDetenteur = true;
            }
        }


        // ✅ Récupérer l'origine (créateur de l'affectation) et son service
        $origine = null;
        try {
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
        } catch (EntityNotFoundException) {
            // Le créateur de l'affectation a été supprimé
            $origine = null;
        }

        return [
            // ✅ ID de l'affectation au début
            'assignmentId' => $assignment->getId(),
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
            'sourceFinancement' => $firstProject && !$firstProject->isDelete() ? $firstProject->getNom() : null,
            'exercice' => $exercice,
            'valeur' => $this->normalizeValeur($asset->getValeur()),
            'valeurInitiale' => $asset->getValeurInitiale(),
            'activeAmortissement' => $asset->isActiveAmortissement(),
            'activeReevaluation' => $asset->isActiveReevaluation(),
            'dateAcquisition' => $asset->getDateAcquisition()?->format('Y-m-d'),
            'etatBien' => $etat ? [
                'id' => (int) $etat->getId(),
                'nom' => (string) $etat->getNom(),
            ] : null,
            // 'champs' => $this->buildChampslist($asset),
            'securise' => $isSecurised,
            'location' => $this->resolveLocation($asset),
            'maintenanceEnCours' => $this->resolveOpenMaintenance($asset),
            // ✅ Champs spécifiques à l'affectation
            // 'dateDebut' => $assignment->getDateDebut()?->format('Y-m-d'),
            // 'dateFin' => $assignment->getDateFin()?->format('Y-m-d'),
            // 'typeAffectation' => $assignment->getTypeAffectation(),
            // 'commentaire' => $assignment->getCommentaire(),
            'detenteur' => $assignment->isDetenteur(),
            'received' => $isReceived,
            // 'origine' => $origine,

            // 'isCurrentUserDetenteur' => $isCurrentUserDetenteur,
            'createdAt' => $assignment->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Maintenance ouverte la plus récente d'un bien, ou null
     * @return array{id: int, etatBien: array|null, motif: ?string, cout: ?string, dateIntervention: ?string, observations: ?string}|null
     */
    private function resolveOpenMaintenance($asset): ?array
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
     * Dernière localisation connue d'un bien
     * @return array{id: int, latitude: mixed, longitude: mixed, geometry_type: mixed, geometry: mixed}|null
     */
    private function resolveLocation($asset): ?array
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
     * affectation du bien
     * @return array{0: ?Service, 1: ?User}
     */
    private function resolveCurrentHolder($asset): array
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
     * Vérifie si un bien est sécurisé
     * @return bool
     */
    private function isAssetSecurised($asset): bool
    {
        $security = $this->securityRepository->findLastActiveByAssetId($asset->getId());
        return $security !== null;
    }

    /**
     * Construit les champs personnalisés avec leurs valeurs (version listing)
     * @return array<int, array<string, mixed>>
     */
    private function buildChampslist($asset): array
    {
        $result = [];

        foreach ($asset->getChamps() as $champ) {
            if ($champ->isDelete()) {
                continue;
            }

            $result[] = [
                'id' => $champ->getId(),
                'nom' => $champ->getNom(),
                'type' => $champ->getType(),
                'sousType' => $champ->getSubtype(),
                'option' => $champ->getOption(),
            ];
        }

        return $result;
    }

    /**
     * Destinataire réel de l'affectation
     */
    private function resolveRecipient(AssetAssignment $assignment): ?User
    {
        if ($assignment->getUser()) {
            return $assignment->getUser();
        }

        if ($assignment->getService()) {
            return $this->userRepository->findActiveByServiceId($assignment->getService()->getId());
        }

        return null;
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
}
