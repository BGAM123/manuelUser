<?php

namespace App\Service;

use App\Entity\Arrondissement;
use App\Entity\Departement;
use App\Entity\Region;
use App\Repository\RegionRepository;

/**
 * Construit l'arbre territorial Région → Département → Arrondissement
 * (uniquement les éléments non soft-supprimés).
 */
final class TerritoryHierarchyBuilder
{
    public function __construct(
        private readonly RegionRepository $regionRepository,
    ) {
    }

    /**
     * @return list<array{id: int, nom: string, departements: list<array{id: int, nom: string, arrondissements: list<array{id: int, nom: string}>}>}>
     */
    public function build(?string $search = null): array
    {
        $regions = $this->regionRepository->findAllActiveWithHierarchy();
        $needle = null !== $search && '' !== trim($search) ? mb_strtolower(trim($search)) : null;

        $tree = [];
        foreach ($regions as $region) {
            $normalized = $this->normalizeRegion($region, $needle);
            if (null !== $normalized) {
                $tree[] = $normalized;
            }
        }

        return $tree;
    }

    /**
     * @return array{id: int, nom: string, departements: list<array{id: int, nom: string, arrondissements: list<array{id: int, nom: string}>}>}|null
     */
    private function normalizeRegion(Region $region, ?string $needle): ?array
    {
        $regionMatches = null === $needle || $this->matches($region->getNom(), $needle);

        $departements = [];
        foreach ($region->getDepartements() as $departement) {
            if ($departement->isDelete()) {
                continue;
            }
            $normalized = $this->normalizeDepartement($departement, $needle, $regionMatches);
            if (null !== $normalized) {
                $departements[] = $normalized;
            }
        }

        usort($departements, static fn (array $a, array $b): int => strcmp($a['nom'], $b['nom']));

        if (!$regionMatches && [] === $departements) {
            return null;
        }

        return [
            'id' => (int) $region->getId(),
            'nom' => (string) $region->getNom(),
            'departements' => $departements,
        ];
    }

    /**
     * @return array{id: int, nom: string, arrondissements: list<array{id: int, nom: string}>}|null
     */
    private function normalizeDepartement(Departement $departement, ?string $needle, bool $ancestorMatches): ?array
    {
        $deptMatches = $ancestorMatches || null === $needle || $this->matches($departement->getNom(), $needle);

        $arrondissements = [];
        foreach ($departement->getArrondissements() as $arrondissement) {
            if ($arrondissement->isDelete()) {
                continue;
            }
            if ($deptMatches || null === $needle || $this->matches($arrondissement->getNom(), $needle)) {
                $arrondissements[] = $this->normalizeArrondissement($arrondissement);
            }
        }

        usort($arrondissements, static fn (array $a, array $b): int => strcmp($a['nom'], $b['nom']));

        if (!$deptMatches && [] === $arrondissements) {
            return null;
        }

        return [
            'id' => (int) $departement->getId(),
            'nom' => (string) $departement->getNom(),
            'arrondissements' => $arrondissements,
        ];
    }

    /**
     * @return array{id: int, nom: string}
     */
    private function normalizeArrondissement(Arrondissement $arrondissement): array
    {
        return [
            'id' => (int) $arrondissement->getId(),
            'nom' => (string) $arrondissement->getNom(),
        ];
    }

    private function matches(?string $value, string $needle): bool
    {
        if (null === $value || '' === $value) {
            return false;
        }

        return str_contains(mb_strtolower($value), $needle);
    }
}
