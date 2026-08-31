<?php
// src/DTO/StatisticsFilter.php

namespace App\DTO;

/**
 * DTO pour les filtres de statistiques, communs à tous les écrans (patrimoine, véhicules,
 * terrains, bâtiments, structures, suivi/évolution).
 *
 * Supporte les formats :
 * - Tableau : ?services_id[]=1&services_id[]=2
 * - CSV : ?services_id=1,2,3
 * - Valeur simple : ?services_id=1
 */
final readonly class StatisticsFilter
{
    /**
     * @param array<int>|null $servicesId IDs de structures. Chaque ID donné inclut aussi tous ses
     *                 descendants d'organigramme (fusion des anciens filtres `service_ids` et
     *                 `organigramme_service_id` : un ID feuille se comporte comme avant, un ID de
     *                 direction inclut automatiquement les services rattachés).
     * @param array<int>|null $categorieId IDs des catégories de biens (Matériel Roulant, Terrain,
     *                 Bâtiment...). Sur /dashboard, une seule catégorie reconnue bascule vers son
     *                 domaine dédié ; plusieurs catégories retombent sur la section "patrimoine"
     *                 générale, filtrée par ces catégories (cf. StatisticsService::getDashboard()).
     * @param array<int>|null $assetTypeIds IDs des types de biens
     * @param array<int>|null $assetSubTypeIds IDs des sous-types de biens (ex. "Pick-up" dans "Véhicule")
     * @param array<int>|null $projectIds IDs des projets (bailleurs)
     * @param array<string>|null $statuts Statuts de gestion des biens (ACTIF / EN MAINTENANCE / SORTIS)
     * @param array<int>|null $etatBienIds IDs des états de biens
     * @param array<int>|null $regionIds IDs des régions
     * @param array<int>|null $departementIds IDs des départements
     * @param array<int>|null $arrondissementIds IDs des arrondissements
     * @param int|null $exercice Année d'exercice sur laquelle filtrer `dateAcquisition`. Si absent,
     *                 la couche service retombe sur l'année en cours (cf. StatisticsService).
     */
    public function __construct(
        public readonly ?array $servicesId = null,
        public readonly ?array $categorieId = null,
        public readonly ?array $assetTypeIds = null,
        public readonly ?array $assetSubTypeIds = null,
        public readonly ?array $projectIds = null,
        public readonly ?array $statuts = null,
        public readonly ?array $etatBienIds = null,
        public readonly ?array $regionIds = null,
        public readonly ?array $departementIds = null,
        public readonly ?array $arrondissementIds = null,
        public readonly ?int $exercice = null,
    ) {
    }

    /**
     * Crée un filtre à partir des paramètres de requête.
     */
    public static function fromRequest(array $params): self
    {
        return new self(
            servicesId: self::parseArrayParam($params['services_id'] ?? null),
            categorieId: self::parseIntArrayParam($params['categorie_id'] ?? null),
            assetTypeIds: self::parseArrayParam($params['type_bien_ids'] ?? null),
            assetSubTypeIds: self::parseArrayParam($params['sous_type_ids'] ?? null),
            projectIds: self::parseArrayParam($params['projet_ids'] ?? null),
            statuts: self::parseArrayParam($params['statuts'] ?? null),
            etatBienIds: self::parseArrayParam($params['etat_bien_ids'] ?? null),
            regionIds: self::parseArrayParam($params['region_ids'] ?? null),
            departementIds: self::parseArrayParam($params['departement_ids'] ?? null),
            arrondissementIds: self::parseArrayParam($params['arrondissement_ids'] ?? null),
            exercice: self::parseIntParam($params['exercice'] ?? null),
        );
    }

    /**
     * Parse un paramètre qui peut être :
     * - un tableau : [1, 2, 3]
     * - une chaîne CSV : "1,2,3"
     * - une valeur simple : "1"
     */
    private static function parseArrayParam(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            $result = array_filter($value, fn($v) => $v !== null && $v !== '');
            return empty($result) ? null : $result;
        }

        if (is_string($value) && str_contains($value, ',')) {
            $result = array_map('trim', explode(',', $value));
            $result = array_filter($result, fn($v) => $v !== '');
            return empty($result) ? null : $result;
        }

        return [$value];
    }

    private static function parseIntParam(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * Comme parseArrayParam, mais convertit chaque élément en entier.
     *
     * @return array<int>|null
     */
    private static function parseIntArrayParam(mixed $value): ?array
    {
        $parsed = self::parseArrayParam($value);

        return $parsed === null ? null : array_values(array_map('intval', $parsed));
    }

    /**
     * Vérifie si au moins un filtre est actif.
     */
    public function hasFilters(): bool
    {
        return $this->servicesId !== null
            || $this->categorieId !== null
            || $this->assetTypeIds !== null
            || $this->assetSubTypeIds !== null
            || $this->projectIds !== null
            || $this->statuts !== null
            || $this->etatBienIds !== null
            || $this->regionIds !== null
            || $this->departementIds !== null
            || $this->arrondissementIds !== null
            || $this->exercice !== null;
    }

    /**
     * Retourne les filtres sous forme de tableau.
     */
    public function toArray(): array
    {
        return array_filter([
            'services_id' => $this->servicesId,
            'categorie_id' => $this->categorieId,
            'type_bien_ids' => $this->assetTypeIds,
            'sous_type_ids' => $this->assetSubTypeIds,
            'projet_ids' => $this->projectIds,
            'statuts' => $this->statuts,
            'etat_bien_ids' => $this->etatBienIds,
            'region_ids' => $this->regionIds,
            'departement_ids' => $this->departementIds,
            'arrondissement_ids' => $this->arrondissementIds,
            'exercice' => $this->exercice,
        ], fn($v) => $v !== null);
    }
}
