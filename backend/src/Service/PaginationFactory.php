<?php

/**
 * PaginationFactory - Factory pour construire des réponses paginées uniformes.
 *
 * Fournit une méthode standardisée pour créer des réponses paginées
 * avec une structure cohérente : success, data (array), pagination (meta).
 */

namespace App\Service;

/**
 * Crée des structures de pagination uniformes pour toutes les listes paginées.
 */
final class PaginationFactory
{
    /**
     * Construit une structure de pagination uniforme.
     *
     * @param array<int|string, mixed> $items Tableau des éléments à paginer
     * @param int $page Numéro de la page actuelle (1-based)
     * @param int $limit Nombre d'éléments par page
     * @param int $total Nombre total d'éléments
     *
     * @return array<string, mixed> Structure paginée : { "data": [], "pagination": { "page", "limit", "total", "pages" } }
     */
    public function createPaginatedResponse(array $items, int $page, int $limit, int $total): array
    {
        return [
            'data' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int) ceil($total > 0 ? $total / $limit : 0),
            ],
        ];
    }
}
