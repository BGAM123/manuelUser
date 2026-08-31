<?php

namespace App\Service\AssetDepreciation;

readonly class AssetDepreciationResult
{
    public function __construct(
        public ?float $valeurAcquisition,
        public ?float $valeurActuelle,
        public ?float $amortissementAnnuel,
        public ?float $amortissementCumule,
        public ?int $dureeVie,
        public ?float $taux,
        public ?int $anneesEcoulees,
        public ?string $dateAcquisition,
        public ?int $dureeVieRestante = null,
        public ?int $moisEcoules = null,
        public ?string $typeCalcul = null,
        public ?int $dureeVieUtilisee = null,  // ✅ Nouveau champ
        public ?float $tauxUtilise = null,     // ✅ Nouveau champ
        public ?string $sourceDureeVie = null, // ✅ Nouveau champ (source de la durée de vie)
        public ?string $sourceTaux = null,
    ) {
    }

    public function toArray(): ?array
    {
        if (null === $this->valeurAcquisition) {
            return null;
        }

        return [
            'valeur' => $this->valeurAcquisition,
            'valeurActuelle' => $this->valeurActuelle,
            'amortissementAnnuel' => $this->amortissementAnnuel,
            'amortissementCumule' => $this->amortissementCumule,
            'dureeVie' => $this->dureeVie,
            'taux' => $this->taux,
            'anneesEcoulees' => $this->anneesEcoulees,
            'dateAcquisition' => $this->dateAcquisition,
            'dureeVieRestante' => $this->dureeVieRestante,
            'moisEcoules' => $this->moisEcoules,
            'typeCalcul' => $this->typeCalcul,
            // 'dureeVieUtilisee' => $this->dureeVieUtilisee,
            // 'tauxUtilise' => $this->tauxUtilise,
            // 'sourceDureeVie' => $this->sourceDureeVie,
            // 'sourceTaux' => $this->sourceTaux,
        ];
    }
}
