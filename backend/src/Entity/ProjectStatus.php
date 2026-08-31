<?php

// src/Entity/ProjectStatus.php

namespace App\Entity;

/**
 * Statuts possibles d'un Projet, sous forme d'enum PHP natif (backé par une chaîne).
 * Utiliser un enum plutôt qu'une simple chaîne libre empêche d'enregistrer une valeur
 * de statut invalide en base (ex. une faute de frappe) : Doctrine et le validateur
 * rejettent toute valeur qui ne correspond pas à l'un des cas ci-dessous.
 */
enum ProjectStatus: string
{
    case PLANIFIE = 'PLANIFIE';
    case EN_COURS = 'EN COURS';
    case TERMINE = 'TERMINE';
}
