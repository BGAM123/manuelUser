<?php

namespace App\Service\AssetDepreciation;

use App\Entity\Asset;
use App\Entity\AssetType;
use App\Entity\AssetDepreciation;
use App\Entity\AssetReevaluation;

final class AssetDepreciationCalculator
{
    private const TYPE_AMORTISSEMENT_SIMPLE = 'AMORTISSEMENT_SIMPLE';
    private const TYPE_REEVALUATION = 'REEVALUATION';
    private const TYPE_DEPRECIATION = 'DEPRECIATION';
    private const TYPE_REEVALUATION_APRES_DEPRECIATION = 'REEVALUATION_APRES_DEPRECIATION';
    private const TYPE_DEPRECIATION_APRES_REEVALUATION = 'DEPRECIATION_APRES_REEVALUATION';

    public function calculate(Asset $asset): AssetDepreciationResult
    {
        $valeur = $this->parseValeur($asset->getValeur());
        $dateAcquisition = $asset->getDateAcquisition();
        $typeBien = $asset->getAssetTypes()->first();


        // ✅ Déterminer la source de la durée de vie et du taux
        // $dureeVie = null;
        // $taux = null;
        // $dateDepart = $dateAcquisition;
        // $typeCalcul = 'AMORTISSEMENT_SIMPLE';
        // $sourceDureeVie = null;
        // $sourceTaux = null;
        // $dureeVieUtilisee = null;
        // $tauxUtilise = null;

        // ✅ Récupérer la dernière dépréciation (triée par dateDepreciation DESC)
        $lastDepreciation = $this->getLastDepreciation($asset);
        
        // ✅ Récupérer la dernière réévaluation (triée par dateReevaluation DESC)
        $lastReevaluation = $this->getLastReevaluation($asset);

        // ✅ Cas 1 : Amortissement simple (ni dépréciation ni réévaluation)
        if (!$lastDepreciation && !$lastReevaluation) {
            return $this->calculateSimpleAmortissement($asset, $valeur, $dateAcquisition, $typeBien);
        }

        // ✅ Cas 2 : Réévaluation uniquement
        if (!$lastDepreciation && $lastReevaluation) {
            return $this->calculateWithReevaluation($asset, $lastReevaluation, $valeur, $dateAcquisition, $typeBien);
        }

        // ✅ Cas 3 : Dépréciation uniquement
        if ($lastDepreciation && !$lastReevaluation) {
            return $this->calculateWithDepreciation($asset, $lastDepreciation);
        }

        // ✅ Cas 4 et 5 : Comparer les dates de la dernière dépréciation et de la dernière réévaluation
        $depreciationDate = $lastDepreciation->getDateDepreciation();
        $reevaluationDate = $lastReevaluation->getDateReevaluation();

        if (!$depreciationDate || !$reevaluationDate) {
            // Si l'une des dates est manquante, utiliser la dépréciation par défaut
            return $this->calculateWithDepreciation($asset, $lastDepreciation);
        }

        // ✅ Cas des dates identiques
        if ($depreciationDate == $reevaluationDate) {
            // Erreur : impossible de déterminer l'ordre
            return new AssetDepreciationResult(
                valeurAcquisition: $this->parseValeur($asset->getValeur()),
                valeurActuelle: null,
                amortissementAnnuel: null,
                amortissementCumule: null,
                dureeVie: null,
                taux: null,
                anneesEcoulees: null,
                dateAcquisition: null,
                dureeVieRestante: null,
                moisEcoules: null,
                typeCalcul: 'ERREUR_DATES_IDENTIQUES',
            );
        }

        // ✅ Cas 4 : Réévaluation après dépréciation
        if ($reevaluationDate > $depreciationDate) {
            return $this->calculateReevaluationAfterDepreciation($asset, $lastDepreciation, $lastReevaluation);
        }

        // ✅ Cas 5 : Dépréciation après réévaluation
        if ($depreciationDate > $reevaluationDate) {
            return $this->calculateDepreciationAfterReevaluation($asset, $lastDepreciation, $lastReevaluation);
        }

        // Fallback : amortissement simple
        return $this->calculateSimpleAmortissement($asset, $valeur, $dateAcquisition, $typeBien);
    }

    /**
     * Récupère la dernière dépréciation par dateDepreciation DESC
     */
    private function getLastDepreciation(Asset $asset): ?AssetDepreciation
    {
        $depreciations = $asset->getDepreciations()->toArray();
        if (empty($depreciations)) {
            return null;
        }

        // Trier par dateDepreciation DESC
        usort($depreciations, function ($a, $b) {
            $dateA = $a->getDateDepreciation();
            $dateB = $b->getDateDepreciation();
            
            if (!$dateA && !$dateB) {
                return 0;
            }
            if (!$dateA) {
                return 1;
            }
            if (!$dateB) {
                return -1;
            }
            
            return $dateB <=> $dateA;
        });

        return $depreciations[0];
    }

    /**
     * Récupère la dernière réévaluation par dateReevaluation DESC
     */
    private function getLastReevaluation(Asset $asset): ?AssetReevaluation
    {
        $reevaluations = $asset->getReevaluations()->toArray();
        if (empty($reevaluations)) {
            return null;
        }

        // Trier par dateReevaluation DESC
        usort($reevaluations, function ($a, $b) {
            $dateA = $a->getDateReevaluation();
            $dateB = $b->getDateReevaluation();
            
            if (!$dateA && !$dateB) {
                return 0;
            }
            if (!$dateA) {
                return 1;
            }
            if (!$dateB) {
                return -1;
            }
            
            return $dateB <=> $dateA;
        });

        return $reevaluations[0];
    }

    /**
     * Cas 1 : Amortissement simple
     */
    private function calculateSimpleAmortissement(Asset $asset, ?float $valeur, ?\DateTimeInterface $dateAcquisition, $typeBien): AssetDepreciationResult
    {
        if (null === $valeur || null === $dateAcquisition || false === $typeBien) {
            return new AssetDepreciationResult(null, null, null, null, null, null, null, null, null, null, self::TYPE_AMORTISSEMENT_SIMPLE);
        }

        $dureeVie = $typeBien->getDureeVie();
        $taux = $this->parseValeur($typeBien->getTaux());

        if (null === $dureeVie || $dureeVie <= 0) {
            return new AssetDepreciationResult(null, null, null, null, null, null, null, null, null, null, self::TYPE_AMORTISSEMENT_SIMPLE);
        }

        $moisEcoules = $this->calculateMonthsElapsed($dateAcquisition);
        $anneesEcoulees = (int) floor($moisEcoules / 12);
        $dureeVieRestante = max(0, $dureeVie - $anneesEcoulees);

        // Calcul de l'amortissement annuel (linéaire)
        $amortissementAnnuel = $valeur / $dureeVie;
        $amortissementMensuel = $amortissementAnnuel / 12;

        // Calcul de l'amortissement cumulé (prorata mensuel)
        $amortissementCumule = $amortissementMensuel * $moisEcoules;

        // Plafonner à la valeur totale
        if ($amortissementCumule > $valeur) {
            $amortissementCumule = $valeur;
        }

        // Calcul de la valeur actuelle
        $valeurActuelle = $valeur - $amortissementCumule;

        if ($valeurActuelle < 0) {
            $valeurActuelle = 0;
        }

        return new AssetDepreciationResult(
            valeurAcquisition: $valeur,
            valeurActuelle: $valeurActuelle,
            amortissementAnnuel: $amortissementAnnuel,
            amortissementCumule: $amortissementCumule,
            dureeVie: $dureeVie,
            taux: $taux,
            anneesEcoulees: $anneesEcoulees,
            dateAcquisition: $dateAcquisition->format('Y-m-d'),
            dureeVieRestante: $dureeVieRestante,
            moisEcoules: $moisEcoules,
            typeCalcul: self::TYPE_AMORTISSEMENT_SIMPLE,
        );
    }

    /**
     * Cas 2 : Réévaluation uniquement
     */
    private function calculateWithReevaluation(Asset $asset, AssetReevaluation $reevaluation, ?float $valeurInitiale, ?\DateTimeInterface $dateAcquisition, $typeBien): AssetDepreciationResult
    {
        // $nouvelleValeur = $this->parseValeur($reevaluation->getNouvelleValeur());
        $nouvelleValeur = $this->parseValeur($asset->getValeur());
        $dateReevaluation = $reevaluation->getDateReevaluation();

        if (null === $nouvelleValeur || null === $dateReevaluation) {
            return new AssetDepreciationResult(null, null, null, null, null, null, null, null, null, null, self::TYPE_REEVALUATION);
        }

        // Utiliser les données du type de bien pour la durée de vie
        if (false === $typeBien) {
            return new AssetDepreciationResult(null, null, null, null, null, null, null, null, null, null, self::TYPE_REEVALUATION);
        }

        $dureeVie = $typeBien->getDureeVie();
        $taux = $this->parseValeur($typeBien->getTaux());

        if (null === $dureeVie || $dureeVie <= 0) {
            return new AssetDepreciationResult(null, null, null, null, null, null, null, null, null, null, self::TYPE_REEVALUATION);
        }

        // Calculer les années écoulées depuis l'acquisition initiale
        $moisEcoulesAcquisition = $this->calculateMonthsElapsed($dateAcquisition);
        $anneesEcoulesAcquisition = (int) floor($moisEcoulesAcquisition / 12);

        // Calculer les années écoulées depuis la réévaluation
        $moisEcoulesReevaluation = $this->calculateMonthsElapsed($dateReevaluation);
        $anneesEcoulesReevaluation = (int) floor($moisEcoulesReevaluation / 12);
        $dureeVieRestante = max(0, $dureeVie - $anneesEcoulesAcquisition);

        // Calcul de l'amortissement annuel sur la nouvelle valeur
        $amortissementAnnuel = $nouvelleValeur / $dureeVieRestante;
        $amortissementMensuel = $amortissementAnnuel / 12;

        // Calcul de l'amortissement cumulé depuis la réévaluation
        $amortissementCumule = $amortissementMensuel * $moisEcoulesReevaluation;

        // Plafonner à la nouvelle valeur
        if ($amortissementCumule > $nouvelleValeur) {
            $amortissementCumule = $nouvelleValeur;
        }

        // Calcul de la valeur actuelle
        $valeurActuelle = $nouvelleValeur - $amortissementCumule;

        if ($valeurActuelle < 0) {
            $valeurActuelle = 0;
        }

        return new AssetDepreciationResult(
            valeurAcquisition: $valeurInitiale,
            valeurActuelle: $valeurActuelle,
            amortissementAnnuel: $amortissementAnnuel,
            amortissementCumule: $amortissementCumule,
            dureeVie: $dureeVie,
            taux: $taux,
            anneesEcoulees: $anneesEcoulesReevaluation,
            dateAcquisition: $dateReevaluation->format('Y-m-d'),
            dureeVieRestante: $dureeVieRestante,
            moisEcoules: $moisEcoulesReevaluation,
            typeCalcul: self::TYPE_REEVALUATION,
        );
    }

    /**
     * Cas 3 : Dépréciation uniquement
     */
    private function calculateWithDepreciation(Asset $asset, AssetDepreciation $depreciation): AssetDepreciationResult
    {
        // $valeurDepart = $this->parseValeur($depreciation->getValeurActuelle());
        $valeurDepart = $this->parseValeur($asset->getValeur());
        $depreciationDate = $depreciation->getDateDepreciation();
        $depreciationDureeVie = $depreciation->getDureeVie();
        $depreciationTaux = $this->parseValeur($depreciation->getTauxDepreciation());

        if (null === $valeurDepart || null === $depreciationDate || null === $depreciationDureeVie || $depreciationDureeVie <= 0) {
            return new AssetDepreciationResult(null, null, null, null, null, null, null, null, null, null, self::TYPE_DEPRECIATION);
        }

        $moisEcoules = $this->calculateMonthsElapsed($depreciationDate);
        $anneesEcoulees = (int) floor($moisEcoules / 12);
        $dureeVieRestante = max(0, $depreciationDureeVie - $anneesEcoulees);

        $amortissementAnnuel = $valeurDepart / $depreciationDureeVie;
        $amortissementMensuel = $amortissementAnnuel / 12;
        $amortissementCumule = $amortissementMensuel * $moisEcoules;

        if ($amortissementCumule > $valeurDepart) {
            $amortissementCumule = $valeurDepart;
        }

        $valeurActuelle = $valeurDepart - $amortissementCumule;

        if ($valeurActuelle < 0) {
            $valeurActuelle = 0;
        }

        return new AssetDepreciationResult(
            valeurAcquisition: $this->parseValeur($asset->getValeur()),
            valeurActuelle: $valeurActuelle,
            amortissementAnnuel: $amortissementAnnuel,
            amortissementCumule: $amortissementCumule,
            dureeVie: $depreciationDureeVie,
            taux: $depreciationTaux,
            anneesEcoulees: $anneesEcoulees,
            dateAcquisition: $depreciationDate->format('Y-m-d'),
            dureeVieRestante: $dureeVieRestante,
            moisEcoules: $moisEcoules,
            typeCalcul: self::TYPE_DEPRECIATION,
        );
    }

    /**
     * Cas 4 : Réévaluation après dépréciation
     */
    private function calculateReevaluationAfterDepreciation(Asset $asset, AssetDepreciation $depreciation, AssetReevaluation $reevaluation): AssetDepreciationResult
    {
        // $nouvelleValeur = $this->parseValeur($reevaluation->getNouvelleValeur());
        $nouvelleValeur = $this->parseValeur($asset->getValeur());
        $dateReevaluation = $reevaluation->getDateReevaluation();
        $depreciationDureeVie = $depreciation->getDureeVie();
        $depreciationTaux = $this->parseValeur($depreciation->getTauxDepreciation());

        if (null === $nouvelleValeur || null === $dateReevaluation || null === $depreciationDureeVie || $depreciationDureeVie <= 0) {
            return new AssetDepreciationResult(null, null, null, null, null, null, null, null, null, null, self::TYPE_REEVALUATION_APRES_DEPRECIATION);
        }

        // Calculer les années écoulées depuis la dépréciation
        $depreciationDate = $depreciation->getDateDepreciation();
        $moisEcoulesDepreciation = $this->calculateMonthsElapsed($depreciationDate);
        $anneesEcoulesDepreciation = (int) floor($moisEcoulesDepreciation / 12);

        // Calculer les années écoulées depuis la réévaluation
        $moisEcoulesReevaluation = $this->calculateMonthsElapsed($dateReevaluation);
        $anneesEcoulesReevaluation = (int) floor($moisEcoulesReevaluation / 12);
        $dureeVieRestante = max(0, $depreciationDureeVie - $anneesEcoulesDepreciation);

        $amortissementAnnuel = $nouvelleValeur / $dureeVieRestante;
        $amortissementMensuel = $amortissementAnnuel / 12;
        $amortissementCumule = $amortissementMensuel * $moisEcoulesReevaluation;

        if ($amortissementCumule > $nouvelleValeur) {
            $amortissementCumule = $nouvelleValeur;
        }

        $valeurActuelle = $nouvelleValeur - $amortissementCumule;

        if ($valeurActuelle < 0) {
            $valeurActuelle = 0;
        }

        return new AssetDepreciationResult(
            valeurAcquisition: $this->parseValeur($asset->getValeur()),
            valeurActuelle: $valeurActuelle,
            amortissementAnnuel: $amortissementAnnuel,
            amortissementCumule: $amortissementCumule,
            dureeVie: $depreciationDureeVie,
            taux: $depreciationTaux,
            anneesEcoulees: $anneesEcoulesReevaluation,
            dateAcquisition: $dateReevaluation->format('Y-m-d'),
            dureeVieRestante: $dureeVieRestante,
            moisEcoules: $moisEcoulesReevaluation,
            typeCalcul: self::TYPE_REEVALUATION_APRES_DEPRECIATION,
        );
    }

    /**
     * Cas 5 : Dépréciation après réévaluation
     */
    private function calculateDepreciationAfterReevaluation(Asset $asset, AssetDepreciation $depreciation, AssetReevaluation $reevaluation): AssetDepreciationResult
    {
        // $valeurDepart = $this->parseValeur($depreciation->getValeurActuelle());
        $valeurDepart = $this->parseValeur($asset->getValeur());
        $depreciationDate = $depreciation->getDateDepreciation();
        $depreciationDureeVie = $depreciation->getDureeVie();
        $depreciationTaux = $this->parseValeur($depreciation->getTauxDepreciation());

        if (null === $valeurDepart || null === $depreciationDate || null === $depreciationDureeVie || $depreciationDureeVie <= 0) {
            return new AssetDepreciationResult(null, null, null, null, null, null, null, null, null, null, self::TYPE_DEPRECIATION_APRES_REEVALUATION);
        }

        $moisEcoules = $this->calculateMonthsElapsed($depreciationDate);
        $anneesEcoulees = (int) floor($moisEcoules / 12);
        $dureeVieRestante = max(0, $depreciationDureeVie - $anneesEcoulees);

        $amortissementAnnuel = $valeurDepart / $depreciationDureeVie;
        $amortissementMensuel = $amortissementAnnuel / 12;
        $amortissementCumule = $amortissementMensuel * $moisEcoules;

        if ($amortissementCumule > $valeurDepart) {
            $amortissementCumule = $valeurDepart;
        }

        $valeurActuelle = $valeurDepart - $amortissementCumule;

        if ($valeurActuelle < 0) {
            $valeurActuelle = 0;
        }

        return new AssetDepreciationResult(
            valeurAcquisition: $this->parseValeur($asset->getValeur()),
            valeurActuelle: $valeurActuelle,
            amortissementAnnuel: $amortissementAnnuel,
            amortissementCumule: $amortissementCumule,
            dureeVie: $depreciationDureeVie,
            taux: $depreciationTaux,
            anneesEcoulees: $anneesEcoulees,
            dateAcquisition: $depreciationDate->format('Y-m-d'),
            dureeVieRestante: $dureeVieRestante,
            moisEcoules: $moisEcoules,
            typeCalcul: self::TYPE_DEPRECIATION_APRES_REEVALUATION,
        );
    }

    private function parseValeur(mixed $valeur): ?float
    {
        if (null === $valeur || '' === $valeur) {
            return null;
        }

        if (is_numeric($valeur)) {
            return (float) $valeur;
        }

        return null;
    }

    /**
     * Calcule le nombre de mois écoulés depuis une date donnée
     */
    private function calculateMonthsElapsed(\DateTimeInterface $dateReference): int
    {
        $today = new \DateTime();
        $interval = $dateReference->diff($today);

        $years = $interval->y;
        $months = $interval->m;
        
        // Calcul total en mois
        $totalMonths = ($years * 12) + $months;

        // Si moins d'un mois, retourner 0
        if ($totalMonths === 0 && $interval->d === 0) {
            return 0;
        }

        return $totalMonths;
    }
}
