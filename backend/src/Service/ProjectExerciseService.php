<?php

namespace App\Service;

use App\Entity\Project;

/**
 * Service de calcul de l'exercice retourné pour les projets.
 * 
 * Ce service applique la règle métier suivante :
 * - Si le projet est EN COURS et l'exercice enregistré < année actuelle → retourner année actuelle
 * - Sinon → retourner l'exercice enregistré
 * 
 * La valeur en base de données n'est JAMAIS modifiée automatiquement.
 */
final class ProjectExerciseService
{
    /**
     * Calcule l'exercice à retourner pour un projet selon la règle métier.
     * 
     * @param Project $project Le projet concerné
     * @return int L'exercice à retourner dans l'API
     */
    public function getExerciseForApi(Project $project): int
    {
        $currentYear = (int) date('Y');
        $recordedExercise = $project->getExercice();
        $statut = $project->getStatut();
        
        // Règle : si projet EN COURS et exercice dépassé → retourner année actuelle
        if ($statut === 'EN COURS' && $recordedExercise !== null && $recordedExercise < $currentYear) {
            return $currentYear;
        }
        
        // Sinon, retourner l'exercice enregistré (ou année actuelle si null)
        return $recordedExercise ?? $currentYear;
    }
    
    /**
     * Vérifie si un projet est considéré comme "en cours".
     * 
     * @param Project $project Le projet concerné
     * @return bool true si le projet est en cours
     */
    public function isProjectInProgress(Project $project): bool
    {
        return $project->getStatut() === 'EN COURS';
    }
    
    /**
     * Retourne l'année actuelle du système.
     * 
     * @return int L'année actuelle
     */
    public function getCurrentYear(): int
    {
        return (int) date('Y');
    }
}
