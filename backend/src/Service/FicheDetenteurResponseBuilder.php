<?php

namespace App\Service;

use App\Entity\Asset;
use App\Entity\AssetAssignment;
use App\Entity\User;
use App\Repository\AssetRepository;
use App\Repository\ConsumableRepository;
use App\Repository\ConsumableTransferRepository;

/**
 * Construit les blocs BIENS / CONSOMMABLES d'une fiche de détenteur : liste des biens
 * et consomptibles réellement détenus par un utilisateur, au format attendu par le
 * document (numéro, désignation, description, date d'acquisition, quantité, prix
 * unitaire, valeur, date d'affectation, lieu d'affectation, observation).
 */
final class FicheDetenteurResponseBuilder
{
    public function __construct(
        private readonly AssetRepository $assetRepository,
        private readonly ConsumableRepository $consumableRepository,
        private readonly ConsumableTransferRepository $consumableTransferRepository,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildBiens(User $detenteur): array
    {
        $assets = $this->assetRepository->findActiveAssetsByUser((int) $detenteur->getId());

        $numero = 0;
        $result = [];
        foreach ($assets as $asset) {
            $assignment = $this->resolveActiveAssignment($asset, $detenteur);

            $quantite = $asset->getQuantiteStock() ?? 1;
            $valeur = $this->toFloat($asset->getValeur());
            $prixUnitaire = $quantite > 0 ? round($valeur / $quantite, 2) : $valeur;

            $lieu = $detenteur->getService()?->getNom();
            $dateAffectation = null;
            if ($assignment) {
                $lieu = ($assignment->getService() ?? $detenteur->getService())?->getNom();
                $dateAffectation = $assignment->getDateDebut()?->format('Y-m-d');
            }

            $result[] = [
                'numero' => ++$numero,
                'designation' => $asset->getNom(),
                'description' => $asset->getDescription(),
                'dateAcquisition' => $asset->getDateAcquisition()?->format('Y-m-d'),
                'quantite' => $quantite,
                'prixUnitaire' => $prixUnitaire,
                'valeur' => $valeur,
                'dateAffectation' => $dateAffectation,
                'lieuAffectation' => $lieu,
                'observation' => '',
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildConsommables(User $detenteur): array
    {
        $service = $detenteur->getService();
        if (!$service) {
            return [];
        }

        $consumables = $this->consumableRepository->createQueryBuilder('c')
            ->where('c.isDelete = false')
            ->andWhere('c.service = :serviceId')
            ->setParameter('serviceId', $service->getId())
            ->orderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();

        $numero = 0;
        $result = [];
        foreach ($consumables as $consumable) {
            $quantite = $this->toFloat($consumable->getStockActuel() ?? $consumable->getQuantite());
            $prixUnitaire = $this->toFloat($consumable->getPrixInitial());
            $valeur = $this->toFloat($consumable->getPrixTotal());

            $lastTransfer = $this->consumableTransferRepository->findLastByConsumableAndService(
                (int) $consumable->getId(),
                (int) $service->getId()
            );

            $dateAffectation = $lastTransfer?->getDateTransfert()?->format('Y-m-d')
                ?? $consumable->getCreatedAt()?->format('Y-m-d');

            $result[] = [
                'numero' => ++$numero,
                'designation' => $consumable->getNom(),
                'description' => $consumable->getDescription(),
                'dateAcquisition' => $consumable->getCreatedAt()?->format('Y-m-d'),
                'quantite' => $quantite,
                'prixUnitaire' => $prixUnitaire,
                'valeur' => $valeur,
                'dateAffectation' => $dateAffectation,
                'lieuAffectation' => $service->getNom(),
                'observation' => '',
            ];
        }

        return $result;
    }

    /**
     * Affectation active (dateFin null) de ce bien pour ce détenteur précis. Correspond
     * au périmètre déjà filtré par AssetRepository::findActiveAssetsByUser().
     */
    private function resolveActiveAssignment(Asset $asset, User $detenteur): ?AssetAssignment
    {
        foreach ($asset->getAssignments() as $assignment) {
            if ($assignment->getUser()?->getId() === $detenteur->getId() && null === $assignment->getDateFin()) {
                return $assignment;
            }
        }

        return null;
    }

    private function toFloat(?string $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
