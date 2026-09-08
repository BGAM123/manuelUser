<?php

namespace App\Repository;

use App\Entity\Asset;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class StockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Asset::class);
    }

    /**
     * Calcule la situation d'un bien
     * PRIORITÉ: SORTIE → MAINTENANCE → AFFECTE → NON_AFFECTE
     */
    public function calculateAssetSituation(int $assetId): ?string
    {
        $conn = $this->getEntityManager()->getConnection();
        $assetIdEscaped = (int) $assetId;

        // 1. Vérifier si le bien est supprimé
        $sql = "SELECT is_delete, statut FROM asset WHERE id = {$assetIdEscaped}";
        $row = $conn->executeQuery($sql)->fetchAssociative();

        if (!$row || $row['is_delete']) {
            return null;
        }

        // 2. Vérifier si le bien est SORTI (statut SORTIS OU AssetExit OU BSP non retourné)
        $sql = "SELECT COUNT(*) FROM asset a
                WHERE a.id = {$assetIdEscaped}
                AND (
                    a.statut IN ('SORTIS', 'SORTIE')
                    OR EXISTS (SELECT 1 FROM asset_exit ae WHERE ae.asset_id = a.id AND ae.is_delete = 0)
                    OR EXISTS (
                        SELECT 1 FROM asset_exit ae2
                        INNER JOIN bsp b ON ae2.id = b.asset_exit_id
                        WHERE ae2.asset_id = a.id
                        AND ae2.is_delete = 0
                        AND b.is_delete = 0
                        AND b.retour = 0
                    )
                )";
        $isSorti = $conn->executeQuery($sql)->fetchOne();
        if ($isSorti > 0) {
            return 'SORTI_DEFINITIVEMENT';
        }

        // 3. Vérifier maintenance EN COURS
        $sql = "SELECT COUNT(*) FROM asset_maintenance_link aml
                INNER JOIN asset_maintenance am ON aml.maintenance_id = am.id
                WHERE aml.asset_id = {$assetIdEscaped} AND am.date_recuperation IS NULL";
        $inMaintenance = $conn->executeQuery($sql)->fetchOne();
        if ($inMaintenance > 0) {
            return 'MAINTENANCE';
        }

        // 4. Vérifier affectation active (avec user_id OU service_id)
        $sql = "SELECT COUNT(*) FROM asset_assignment aa
                WHERE aa.asset_id = {$assetIdEscaped}
                AND aa.is_delete = 0
                AND aa.date_fin IS NULL
                AND (aa.user_id IS NOT NULL OR aa.service_id IS NOT NULL)";
        $hasAssignment = $conn->executeQuery($sql)->fetchOne();
        if ($hasAssignment > 0) {
            return 'AFFECTE';
        }

        // 5. Par défaut, non affecté
        return 'NON AFFECTE';
    }

    /**
     * Récupère les biens avec leur situation calculée, paginés et filtrés
     * UNIQUEMENT les biens dans le patrimoine (non sortis)
     */
    public function findByAssetsWithSituation(
        int $page,
        int $limit,
        ?array $filters = null
    ): array {
        $conn = $this->getEntityManager()->getConnection();
        $offset = ($page - 1) * $limit;
        $limitEscaped = (int) $limit;
        $offsetEscaped = (int) $offset;

        $whereConditions = [
            "a.is_delete = 0",
        ];

        // Condition par défaut : exclure les biens sortis, SAUF si un filtre statut est fourni
        if (!isset($filters['statut'])) {
            $whereConditions[] = "a.statut NOT IN ('SORTIS', 'SORTIE')";
        }

        $whereConditions[] = "NOT EXISTS (SELECT 1 FROM asset_exit ae WHERE ae.asset_id = a.id AND ae.is_delete = 0)";
        $whereConditions[] = "NOT EXISTS (
                SELECT 1 FROM asset_exit ae2
                INNER JOIN bsp b ON ae2.id = b.asset_exit_id
                WHERE ae2.asset_id = a.id
                AND ae2.is_delete = 0
                AND b.is_delete = 0
                AND b.retour = 0
            )";

        // Appliquer les filtres
        if (isset($filters['category_id'])) {
            $whereConditions[] = "EXISTS (SELECT 1 FROM asset_category ac WHERE ac.asset_id = a.id AND ac.category_id = " . (int) $filters['category_id'] . ")";
        }

        if (isset($filters['asset_type_id'])) {
            $whereConditions[] = "EXISTS (SELECT 1 FROM asset_asset_type aat WHERE aat.asset_id = a.id AND aat.asset_type_id = " . (int) $filters['asset_type_id'] . ")";
        }

        if (isset($filters['etat_bien_id'])) {
            $whereConditions[] = "EXISTS (SELECT 1 FROM asset_etat_bien aeb WHERE aeb.asset_id = a.id AND aeb.etat_bien_id = " . (int) $filters['etat_bien_id'] . ")";
        }

        if (isset($filters['service_id'])) {
            $whereConditions[] = "EXISTS (SELECT 1 FROM asset_service asrv WHERE asrv.asset_id = a.id AND asrv.service_id = " . (int) $filters['service_id'] . ")";
        }

        if (isset($filters['statut']) && !empty($filters['statut'])) {
            $statut = $conn->quote($filters['statut']);
            $whereConditions[] = "a.statut = {$statut}";
        }

        if (isset($filters['exercice']) && !empty($filters['exercice'])) {
            $exercice = (int) $filters['exercice'];
            $whereConditions[] = "EXISTS (SELECT 1 FROM asset_project ap INNER JOIN project p ON ap.project_id = p.id WHERE ap.asset_id = a.id AND p.exercice = {$exercice} AND p.is_delete = 0)";
        }

        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $conn->quote('%' . $filters['search'] . '%');
            $whereConditions[] = "(a.nom LIKE {$search} OR a.reference LIKE {$search} OR a.numero_serie LIKE {$search})";
        }

        if (isset($filters['situation'])) {
            $situation = $filters['situation'];
            if ($situation === 'AFFECTE') {
                $whereConditions[] = "EXISTS (SELECT 1 FROM asset_assignment aa WHERE aa.asset_id = a.id AND aa.is_delete = 0 AND aa.date_fin IS NULL AND (aa.user_id IS NOT NULL OR aa.service_id IS NOT NULL))";
            } elseif ($situation === 'MAINTENANCE') {
                $whereConditions[] = "EXISTS (SELECT 1 FROM asset_maintenance_link aml INNER JOIN asset_maintenance am ON aml.maintenance_id = am.id WHERE aml.asset_id = a.id AND am.date_recuperation IS NULL)";
            } elseif ($situation === 'NON AFFECTE') {
                $whereConditions[] = "NOT EXISTS (SELECT 1 FROM asset_assignment aa WHERE aa.asset_id = a.id AND aa.is_delete = 0 AND aa.date_fin IS NULL AND (aa.user_id IS NOT NULL OR aa.service_id IS NOT NULL))";
                $whereConditions[] = "NOT EXISTS (SELECT 1 FROM asset_maintenance_link aml INNER JOIN asset_maintenance am ON aml.maintenance_id = am.id WHERE aml.asset_id = a.id AND am.date_recuperation IS NULL)";
            }
        }

        $whereClause = implode(' AND ', $whereConditions);

        $sql = "SELECT a.* FROM asset a
                WHERE {$whereClause}
                ORDER BY a.id DESC
                LIMIT {$limitEscaped} OFFSET {$offsetEscaped}";

        $assetsData = $conn->executeQuery($sql)->fetchAllAssociative();

        // Convertir en objets Asset
        $assetObjects = [];
        foreach ($assetsData as $assetData) {
            $asset = $this->getEntityManager()->find(Asset::class, $assetData['id']);
            if ($asset) {
                $assetObjects[] = $asset;
            }
        }

        return $assetObjects;
    }

    /**
     * Compte les biens avec filtres
     * UNIQUEMENT les biens dans le patrimoine (non sortis)
     */
    public function countByAssetsWithSituation(?array $filters = null): int
    {
        $conn = $this->getEntityManager()->getConnection();

        $whereConditions = [
            "a.is_delete = 0",
        ];

        // Condition par défaut : exclure les biens sortis, SAUF si un filtre statut est fourni
        if (!isset($filters['statut'])) {
            $whereConditions[] = "a.statut NOT IN ('SORTIS', 'SORTIE')";
        }

        $whereConditions[] = "NOT EXISTS (SELECT 1 FROM asset_exit ae WHERE ae.asset_id = a.id AND ae.is_delete = 0)";
        $whereConditions[] = "NOT EXISTS (
                SELECT 1 FROM asset_exit ae2
                INNER JOIN bsp b ON ae2.id = b.asset_exit_id
                WHERE ae2.asset_id = a.id
                AND ae2.is_delete = 0
                AND b.is_delete = 0
                AND b.retour = 0
            )";

        // Appliquer les filtres
        if (isset($filters['category_id'])) {
            $whereConditions[] = "EXISTS (SELECT 1 FROM asset_category ac WHERE ac.asset_id = a.id AND ac.category_id = " . (int) $filters['category_id'] . ")";
        }

        if (isset($filters['asset_type_id'])) {
            $whereConditions[] = "EXISTS (SELECT 1 FROM asset_asset_type aat WHERE aat.asset_id = a.id AND aat.asset_type_id = " . (int) $filters['asset_type_id'] . ")";
        }

        if (isset($filters['etat_bien_id'])) {
            $whereConditions[] = "EXISTS (SELECT 1 FROM asset_etat_bien aeb WHERE aeb.asset_id = a.id AND aeb.etat_bien_id = " . (int) $filters['etat_bien_id'] . ")";
        }

        if (isset($filters['service_id'])) {
            $whereConditions[] = "EXISTS (SELECT 1 FROM asset_service asrv WHERE asrv.asset_id = a.id AND asrv.service_id = " . (int) $filters['service_id'] . ")";
        }

        if (isset($filters['statut']) && !empty($filters['statut'])) {
            $statut = $conn->quote($filters['statut']);
            $whereConditions[] = "a.statut = {$statut}";
        }

        if (isset($filters['exercice']) && !empty($filters['exercice'])) {
            $exercice = (int) $filters['exercice'];
            $whereConditions[] = "EXISTS (SELECT 1 FROM asset_project ap INNER JOIN project p ON ap.project_id = p.id WHERE ap.asset_id = a.id AND p.exercice = {$exercice} AND p.is_delete = 0)";
        }

        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $conn->quote('%' . $filters['search'] . '%');
            $whereConditions[] = "(a.nom LIKE {$search} OR a.reference LIKE {$search} OR a.numero_serie LIKE {$search})";
        }

        if (isset($filters['situation'])) {
            $situation = $filters['situation'];
            if ($situation === 'AFFECTE') {
                $whereConditions[] = "EXISTS (SELECT 1 FROM asset_assignment aa WHERE aa.asset_id = a.id AND aa.is_delete = 0 AND aa.date_fin IS NULL AND (aa.user_id IS NOT NULL OR aa.service_id IS NOT NULL))";
            } elseif ($situation === 'MAINTENANCE') {
                $whereConditions[] = "EXISTS (SELECT 1 FROM asset_maintenance_link aml INNER JOIN asset_maintenance am ON aml.maintenance_id = am.id WHERE aml.asset_id = a.id AND am.date_recuperation IS NULL)";
            } elseif ($situation === 'NON AFFECTE') {
                $whereConditions[] = "NOT EXISTS (SELECT 1 FROM asset_assignment aa WHERE aa.asset_id = a.id AND aa.is_delete = 0 AND aa.date_fin IS NULL AND (aa.user_id IS NOT NULL OR aa.service_id IS NOT NULL))";
                $whereConditions[] = "NOT EXISTS (SELECT 1 FROM asset_maintenance_link aml INNER JOIN asset_maintenance am ON aml.maintenance_id = am.id WHERE aml.asset_id = a.id AND am.date_recuperation IS NULL)";
            }
        }

        $whereClause = implode(' AND ', $whereConditions);

        $sql = "SELECT COUNT(DISTINCT a.id) FROM asset a WHERE {$whereClause}";

        return (int) $conn->executeQuery($sql)->fetchOne();
    }

    /**
     * Compte les biens par projet
     */
    public function countByProject(?array $filters = null): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $whereConditions = [
            "p.is_delete = 0",
            "a.is_delete = 0",
        ];

        // Appliquer le filtre exercice si fourni
        if (isset($filters['exercice']) && !empty($filters['exercice'])) {
            $exercice = (int) $filters['exercice'];
            $whereConditions[] = "p.exercice = {$exercice}";
        }

        $whereClause = implode(' AND ', $whereConditions);

        $sql = "SELECT p.id, p.nom, p.exercice, COUNT(DISTINCT a.id) as nombre_biens
                FROM project p
                INNER JOIN asset_project ap ON p.id = ap.project_id
                INNER JOIN asset a ON ap.asset_id = a.id
                WHERE {$whereClause}
                GROUP BY p.id, p.nom, p.exercice
                ORDER BY p.exercice DESC, p.nom ASC";

        return $conn->executeQuery($sql)->fetchAllAssociative();
    }

    /**
     * Compte les biens par situation avec la nouvelle logique
     */
    public function countBySituation(?array $filters = null): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $results = [];

        // Construire la condition de filtre exercice si fourni
        $exerciceFilter = "";
        if (isset($filters['exercice']) && !empty($filters['exercice'])) {
            $exercice = (int) $filters['exercice'];
            $exerciceFilter = "AND EXISTS (SELECT 1 FROM asset_project ap INNER JOIN project p ON ap.project_id = p.id WHERE ap.asset_id = a.id AND p.exercice = {$exercice} AND p.is_delete = 0)";
        }

        // =====================================================
        // 1. TOTAL DES BIENS NON SUPPRIMÉS
        // =====================================================
        $sql = "SELECT COUNT(*) FROM asset a WHERE a.is_delete = 0 {$exerciceFilter}";
        $results['total_biens'] = (int) $conn->executeQuery($sql)->fetchOne();

        // =====================================================
        // 2. BIENS SORTIE (statut SORTIE OU AssetExit OU BSP non retourné)
        // =====================================================
        $sql = "SELECT COUNT(DISTINCT a.id) FROM asset a
                WHERE a.is_delete = 0 {$exerciceFilter}
                AND (
                    a.statut IN ('SORTIS', 'SORTIE')
                    OR EXISTS (SELECT 1 FROM asset_exit ae WHERE ae.asset_id = a.id AND ae.is_delete = 0)
                    OR EXISTS (
                        SELECT 1 FROM asset_exit ae2
                        INNER JOIN bsp b ON ae2.id = b.asset_exit_id
                        WHERE ae2.asset_id = a.id
                        AND ae2.is_delete = 0
                        AND b.is_delete = 0
                        AND b.retour = 0
                    )
                )";
        $results['total_sortis'] = (int) $conn->executeQuery($sql)->fetchOne();

        // Détail des sorties
        // Sorties définitives (statut SORTIE OU AssetExit)
        $sql = "SELECT COUNT(DISTINCT a.id) FROM asset a
                WHERE a.is_delete = 0 {$exerciceFilter}
                AND (
                    a.statut IN ('SORTIS', 'SORTIE')
                    OR EXISTS (SELECT 1 FROM asset_exit ae WHERE ae.asset_id = a.id AND ae.is_delete = 0)
                )";
        $results['sorties_definitives'] = (int) $conn->executeQuery($sql)->fetchOne();

        // BSP non retournés (et pas déjà comptés comme sortis définitifs)
        $sql = "SELECT COUNT(DISTINCT a.id) FROM asset a
                INNER JOIN asset_exit ae ON a.id = ae.asset_id
                INNER JOIN bsp b ON ae.id = b.asset_exit_id
                WHERE a.is_delete = 0
                AND a.statut NOT IN ('SORTIS', 'SORTIE')
                AND ae.is_delete = 0
                AND b.is_delete = 0
                AND b.retour = 0
                AND NOT EXISTS (
                    SELECT 1 FROM asset_exit ae2
                    WHERE ae2.asset_id = a.id AND ae2.is_delete = 0
                )
                {$exerciceFilter}";
        $results['bsp_non_retournes'] = (int) $conn->executeQuery($sql)->fetchOne();

        // =====================================================
        // 3. PATRIMOINE (total - sortis)
        // =====================================================
        $results['total_patrimoine'] = $results['total_biens'] - $results['total_sortis'];

        // =====================================================
        // 4. MAINTENANCE EN COURS (dans le patrimoine)
        // =====================================================
        $sql = "SELECT COUNT(DISTINCT a.id) FROM asset a
                INNER JOIN asset_maintenance_link aml ON a.id = aml.asset_id
                INNER JOIN asset_maintenance am ON aml.maintenance_id = am.id
                WHERE a.is_delete = 0
                AND am.date_recuperation IS NULL
                AND NOT EXISTS (
                    SELECT 1 FROM asset_exit ae WHERE ae.asset_id = a.id AND ae.is_delete = 0
                )
                AND NOT EXISTS (
                    SELECT 1 FROM asset_exit ae2
                    INNER JOIN bsp b ON ae2.id = b.asset_exit_id
                    WHERE ae2.asset_id = a.id
                    AND ae2.is_delete = 0
                    AND b.is_delete = 0
                    AND b.retour = 0
                )
                AND a.statut NOT IN ('SORTIS', 'SORTIE')
                {$exerciceFilter}";
        $results['maintenance'] = (int) $conn->executeQuery($sql)->fetchOne();

        // =====================================================
        // 5. AFFECTÉS (dans le patrimoine, pas en maintenance)
        // =====================================================
        $sql = "SELECT COUNT(DISTINCT a.id) FROM asset a
                INNER JOIN asset_assignment aa ON a.id = aa.asset_id
                WHERE a.is_delete = 0
                AND aa.is_delete = 0
                AND aa.date_fin IS NULL
                AND (aa.user_id IS NOT NULL OR aa.service_id IS NOT NULL)
                AND NOT EXISTS (
                    SELECT 1 FROM asset_maintenance_link aml
                    INNER JOIN asset_maintenance am ON aml.maintenance_id = am.id
                    WHERE aml.asset_id = a.id AND am.date_recuperation IS NULL
                )
                AND NOT EXISTS (
                    SELECT 1 FROM asset_exit ae WHERE ae.asset_id = a.id AND ae.is_delete = 0
                )
                AND NOT EXISTS (
                    SELECT 1 FROM asset_exit ae2
                    INNER JOIN bsp b ON ae2.id = b.asset_exit_id
                    WHERE ae2.asset_id = a.id
                    AND ae2.is_delete = 0
                    AND b.is_delete = 0
                    AND b.retour = 0
                )
                AND a.statut NOT IN ('SORTIS', 'SORTIE')
                {$exerciceFilter}";
        $results['affectes'] = (int) $conn->executeQuery($sql)->fetchOne();

        // =====================================================
        // 6. AFFECTÉS UTILISATEURS (dans le patrimoine)
        // =====================================================
        $sql = "SELECT COUNT(DISTINCT a.id) FROM asset a
                INNER JOIN asset_assignment aa ON a.id = aa.asset_id
                WHERE a.is_delete = 0
                AND aa.is_delete = 0
                AND aa.date_fin IS NULL
                AND aa.user_id IS NOT NULL
                AND NOT EXISTS (
                    SELECT 1 FROM asset_maintenance_link aml
                    INNER JOIN asset_maintenance am ON aml.maintenance_id = am.id
                    WHERE aml.asset_id = a.id AND am.date_recuperation IS NULL
                )
                AND NOT EXISTS (
                    SELECT 1 FROM asset_exit ae WHERE ae.asset_id = a.id AND ae.is_delete = 0
                )
                AND NOT EXISTS (
                    SELECT 1 FROM asset_exit ae2
                    INNER JOIN bsp b ON ae2.id = b.asset_exit_id
                    WHERE ae2.asset_id = a.id
                    AND ae2.is_delete = 0
                    AND b.is_delete = 0
                    AND b.retour = 0
                )
                AND a.statut NOT IN ('SORTIS', 'SORTIE')
                {$exerciceFilter}";
        $results['affectes_utilisateurs'] = (int) $conn->executeQuery($sql)->fetchOne();

        // =====================================================
        // 7. AFFECTÉS SERVICES (dans le patrimoine)
        // =====================================================
        $sql = "SELECT COUNT(DISTINCT a.id) FROM asset a
                INNER JOIN asset_assignment aa ON a.id = aa.asset_id
                WHERE a.is_delete = 0
                AND aa.is_delete = 0
                AND aa.date_fin IS NULL
                AND aa.service_id IS NOT NULL
                AND aa.user_id IS NULL
                AND NOT EXISTS (
                    SELECT 1 FROM asset_maintenance_link aml
                    INNER JOIN asset_maintenance am ON aml.maintenance_id = am.id
                    WHERE aml.asset_id = a.id AND am.date_recuperation IS NULL
                )
                AND NOT EXISTS (
                    SELECT 1 FROM asset_exit ae WHERE ae.asset_id = a.id AND ae.is_delete = 0
                )
                AND NOT EXISTS (
                    SELECT 1 FROM asset_exit ae2
                    INNER JOIN bsp b ON ae2.id = b.asset_exit_id
                    WHERE ae2.asset_id = a.id
                    AND ae2.is_delete = 0
                    AND b.is_delete = 0
                    AND b.retour = 0
                )
                AND a.statut NOT IN ('SORTIS', 'SORTIE')
                {$exerciceFilter}";
        $results['affectes_services'] = (int) $conn->executeQuery($sql)->fetchOne();

        // =====================================================
        // 8. NON AFFECTÉS (dans le patrimoine, pas maintenance, pas affecté)
        // =====================================================
        $results['non_affectes'] = $results['total_patrimoine']
            - $results['maintenance']
            - $results['affectes'];

        // =====================================================
        // 9. ACTIFS / INACTIFS (dans le patrimoine uniquement)
        // =====================================================
        $sql = "SELECT COUNT(DISTINCT a.id) FROM asset a
                WHERE a.is_delete = 0
                AND a.statut = 'ACTIF'
                AND a.statut NOT IN ('SORTIS', 'SORTIE')
                AND NOT EXISTS (
                    SELECT 1 FROM asset_exit ae WHERE ae.asset_id = a.id AND ae.is_delete = 0
                )
                AND NOT EXISTS (
                    SELECT 1 FROM asset_exit ae2
                    INNER JOIN bsp b ON ae2.id = b.asset_exit_id
                    WHERE ae2.asset_id = a.id
                    AND ae2.is_delete = 0
                    AND b.is_delete = 0
                    AND b.retour = 0
                )
                {$exerciceFilter}";
        $results['actifs'] = (int) $conn->executeQuery($sql)->fetchOne();

        // Inactifs = patrimoine - actifs
        $results['inactifs'] = $results['total_patrimoine'] - $results['actifs'];

        // =====================================================
        // 10. COMPATIBILITÉ
        // =====================================================
        $results['patrimoine_total'] = $results['total_patrimoine'];
        $results['SORTI_DEFINITIVEMENT'] = $results['total_sortis'];
        $results['AFFECTE'] = $results['affectes'];
        $results['DISPONIBLE'] = $results['non_affectes'];

        return $results;
    }

    /**
     * Compte les affectations par utilisateur et service
     * UNIQUEMENT dans le patrimoine
     */
    public function countByAssignments(?array $filters = null): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $results = [];

        // Construire la condition de filtre exercice si fourni
        $exerciceFilter = "";
        if (isset($filters['exercice']) && !empty($filters['exercice'])) {
            $exercice = (int) $filters['exercice'];
            $exerciceFilter = "AND EXISTS (SELECT 1 FROM asset_project ap INNER JOIN project p ON ap.project_id = p.id WHERE ap.asset_id = a.id AND p.exercice = {$exercice} AND p.is_delete = 0)";
        }

        // Affectations utilisateurs
        $sql = "SELECT u.id, u.last_name, u.first_name, u.matricule, COUNT(DISTINCT a.id) as nombre_biens
                FROM asset a
                INNER JOIN asset_assignment aa ON a.id = aa.asset_id
                INNER JOIN user u ON aa.user_id = u.id
                WHERE a.is_delete = 0
                AND aa.is_delete = 0
                AND aa.date_fin IS NULL
                AND aa.user_id IS NOT NULL
                AND a.statut NOT IN ('SORTIS', 'SORTIE')
                AND NOT EXISTS (SELECT 1 FROM asset_exit ae WHERE ae.asset_id = a.id AND ae.is_delete = 0)
                AND NOT EXISTS (
                    SELECT 1 FROM asset_exit ae2
                    INNER JOIN bsp b ON ae2.id = b.asset_exit_id
                    WHERE ae2.asset_id = a.id
                    AND ae2.is_delete = 0
                    AND b.is_delete = 0
                    AND b.retour = 0
                )
                AND NOT EXISTS (
                    SELECT 1 FROM asset_maintenance_link aml
                    INNER JOIN asset_maintenance am ON aml.maintenance_id = am.id
                    WHERE aml.asset_id = a.id AND am.date_recuperation IS NULL
                )
                {$exerciceFilter}
                GROUP BY u.id, u.last_name, u.first_name, u.matricule
                ORDER BY nombre_biens DESC";
        $results['utilisateurs'] = $conn->executeQuery($sql)->fetchAllAssociative();
        $results['utilisateurs_total'] = array_sum(array_column($results['utilisateurs'], 'nombre_biens'));

        // Affectations services
        $sql = "SELECT s.id, s.nom, s.sigle, COUNT(DISTINCT a.id) as nombre_biens
                FROM asset a
                INNER JOIN asset_assignment aa ON a.id = aa.asset_id
                INNER JOIN service s ON aa.service_id = s.id
                WHERE a.is_delete = 0
                AND aa.is_delete = 0
                AND aa.date_fin IS NULL
                AND aa.service_id IS NOT NULL
                AND aa.user_id IS NULL
                AND a.statut NOT IN ('SORTIS', 'SORTIE')
                AND NOT EXISTS (SELECT 1 FROM asset_exit ae WHERE ae.asset_id = a.id AND ae.is_delete = 0)
                AND NOT EXISTS (
                    SELECT 1 FROM asset_exit ae2
                    INNER JOIN bsp b ON ae2.id = b.asset_exit_id
                    WHERE ae2.asset_id = a.id
                    AND ae2.is_delete = 0
                    AND b.is_delete = 0
                    AND b.retour = 0
                )
                AND NOT EXISTS (
                    SELECT 1 FROM asset_maintenance_link aml
                    INNER JOIN asset_maintenance am ON aml.maintenance_id = am.id
                    WHERE aml.asset_id = a.id AND am.date_recuperation IS NULL
                )
                {$exerciceFilter}
                GROUP BY s.id, s.nom, s.sigle
                ORDER BY nombre_biens DESC";
        $results['services'] = $conn->executeQuery($sql)->fetchAllAssociative();
        $results['services_total'] = array_sum(array_column($results['services'], 'nombre_biens'));

        // Total affectations = utilisateurs + services
        $results['total'] = $results['utilisateurs_total'] + $results['services_total'];

        return $results;
    }

    /**
     * Compte les biens par statut (actif/inactif)
     */
    public function countByStatut(?array $filters = null): array
    {
        $conn = $this->getEntityManager()->getConnection();

        // Construire la condition de filtre exercice si fourni
        $exerciceFilter = "";
        if (isset($filters['exercice']) && !empty($filters['exercice'])) {
            $exercice = (int) $filters['exercice'];
            $exerciceFilter = "AND EXISTS (SELECT 1 FROM asset_project ap INNER JOIN project p ON ap.project_id = p.id WHERE ap.asset_id = a.id AND p.exercice = {$exercice} AND p.is_delete = 0)";
        }

        // Actifs = statut ACTIF et non supprimés
        $sql = "SELECT COUNT(*)
        FROM asset a
        WHERE a.is_delete = 0
        AND a.statut NOT IN ('INACTIF', 'SORTIS', 'SORTIE')
        {$exerciceFilter}";
        $actifs = (int) $conn->executeQuery($sql)->fetchOne();

        // Inactifs = statut != ACTIF et non supprimés
        $sql = "SELECT COUNT(*) FROM asset a WHERE a.is_delete = 0 AND a.statut = 'INACTIF' {$exerciceFilter}";
        $inactifs = (int) $conn->executeQuery($sql)->fetchOne();

        return [
            'actifs' => $actifs,
            'inactifs' => $inactifs
        ];
    }

    // =====================================================
    // MÉTHODES POUR L'HISTORIQUE ET LES MOUVEMENTS
    // =====================================================

    /**
     * Récupère l'historique des mouvements d'un bien
     */
    public function findAssetHistory(int $assetId): array
    {
        $history = [];
        $assetIdEscaped = (int) $assetId;
        $conn = $this->getEntityManager()->getConnection();

        // Affectations
        $sql = "SELECT 'ASSIGNMENT' as type, aa.id, aa.date_debut, aa.date_fin, aa.type_affectation,
                u.id as user_id, CONCAT(u.first_name, ' ', u.last_name) as user_name,
                s.id as service_id, s.nom as service_name, aa.created_at
                FROM asset_assignment aa
                LEFT JOIN user u ON aa.user_id = u.id
                LEFT JOIN service s ON aa.service_id = s.id
                WHERE aa.asset_id = {$assetIdEscaped} AND aa.is_delete = 0
                ORDER BY aa.created_at DESC";
        $history = array_merge($history, $conn->executeQuery($sql)->fetchAllAssociative());

        // Maintenances
        $sql = "SELECT 'MAINTENANCE' as type, am.id, am.date_intervention, am.date_recuperation, am.statut,
                am.motif, am.cout, am.created_at
                FROM asset_maintenance am
                INNER JOIN asset_maintenance_link aml ON am.id = aml.maintenance_id
                WHERE aml.asset_id = {$assetIdEscaped}
                ORDER BY am.created_at DESC";
        $history = array_merge($history, $conn->executeQuery($sql)->fetchAllAssociative());

        // Sorties (AssetExit)
        $sql = "SELECT 'EXIT' as type, ae.id, ae.date_sortie, ae.motif_sortie, ae.protocole_reference,
                s.id as service_id, s.nom as service_name,
                u.id as user_id, CONCAT(u.first_name, ' ', u.last_name) as user_name,
                ae.created_at
                FROM asset_exit ae
                LEFT JOIN service s ON ae.service_id = s.id
                LEFT JOIN user u ON ae.user_id = u.id
                WHERE ae.asset_id = {$assetIdEscaped} AND ae.is_delete = 0
                ORDER BY ae.created_at DESC";
        $history = array_merge($history, $conn->executeQuery($sql)->fetchAllAssociative());

        // BSP
        $sql = "SELECT 'BSP' as type, b.id, b.numero, b.date_etablissement, b.date_retour_effective, b.retour,
                b.quantite_servie, s.id as service_id, s.nom as service_name,
                CONCAT(ben.first_name, ' ', ben.last_name) as beneficiaire_name,
                b.created_at
                FROM bsp b
                INNER JOIN asset_exit ae ON b.asset_exit_id = ae.id
                LEFT JOIN service s ON b.service_id = s.id
                LEFT JOIN user ben ON b.beneficiaire_id = ben.id
                WHERE ae.asset_id = {$assetIdEscaped} AND b.is_delete = 0
                ORDER BY b.created_at DESC";
        $history = array_merge($history, $conn->executeQuery($sql)->fetchAllAssociative());

        // Trier par date de création
        usort($history, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return $history;
    }

    /**
     * Récupère les mouvements globaux
     */
    public function findGlobalMovements(
        int $page,
        int $limit,
        ?string $type = null,
        ?\DateTimeInterface $dateDebut = null,
        ?\DateTimeInterface $dateFin = null
    ): array {
        $conn = $this->getEntityManager()->getConnection();
        $offset = ($page - 1) * $limit;
        $limitEscaped = (int) $limit;
        $offsetEscaped = (int) $offset;

        $where = ["WHERE 1=1"];

        if ($type) {
            $typeEscaped = $conn->quote($type);
            $where[] = "type = {$typeEscaped}";
        }

        if ($dateDebut) {
            $dateDebutEscaped = $conn->quote($dateDebut->format('Y-m-d H:i:s'));
            $where[] = "created_at >= {$dateDebutEscaped}";
        }

        if ($dateFin) {
            $dateFinEscaped = $conn->quote($dateFin->format('Y-m-d H:i:s'));
            $where[] = "created_at <= {$dateFinEscaped}";
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT 'ASSIGNMENT' as type, aa.id, a.id as asset_id, a.nom as asset_nom,
                aa.date_debut, aa.date_fin, aa.created_at
                FROM asset_assignment aa
                INNER JOIN asset a ON aa.asset_id = a.id
                WHERE aa.is_delete = 0

                UNION ALL

                SELECT 'MAINTENANCE' as type, am.id, a.id as asset_id, a.nom as asset_nom,
                am.date_intervention as date_debut, am.date_recuperation as date_fin, am.created_at
                FROM asset_maintenance am
                INNER JOIN asset_maintenance_link aml ON am.id = aml.maintenance_id
                INNER JOIN asset a ON aml.asset_id = a.id

                UNION ALL

                SELECT 'EXIT' as type, ae.id, a.id as asset_id, a.nom as asset_nom,
                ae.date_sortie as date_debut, NULL as date_fin, ae.created_at
                FROM asset_exit ae
                INNER JOIN asset a ON ae.asset_id = a.id
                WHERE ae.is_delete = 0

                UNION ALL

                SELECT 'BSP' as type, b.id, a.id as asset_id, a.nom as asset_nom,
                b.date_etablissement as date_debut, b.date_retour_effective as date_fin, b.created_at
                FROM bsp b
                INNER JOIN asset_exit ae ON b.asset_exit_id = ae.id
                INNER JOIN asset a ON ae.asset_id = a.id
                WHERE b.is_delete = 0

                ORDER BY created_at DESC
                LIMIT {$limitEscaped} OFFSET {$offsetEscaped}";

        return $conn->executeQuery($sql)->fetchAllAssociative();
    }

    /**
     * Compte les mouvements globaux
     */
    public function countByGlobalMovements(
        ?string $type = null,
        ?\DateTimeInterface $dateDebut = null,
        ?\DateTimeInterface $dateFin = null
    ): int {
        $conn = $this->getEntityManager()->getConnection();

        $where = ["WHERE 1=1"];

        if ($type) {
            $typeEscaped = $conn->quote($type);
            $where[] = "type = {$typeEscaped}";
        }

        if ($dateDebut) {
            $dateDebutEscaped = $conn->quote($dateDebut->format('Y-m-d H:i:s'));
            $where[] = "created_at >= {$dateDebutEscaped}";
        }

        if ($dateFin) {
            $dateFinEscaped = $conn->quote($dateFin->format('Y-m-d H:i:s'));
            $where[] = "created_at <= {$dateFinEscaped}";
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT COUNT(*) FROM (
            SELECT 'ASSIGNMENT' as type, aa.created_at
            FROM asset_assignment aa
            WHERE aa.is_delete = 0

            UNION ALL

            SELECT 'MAINTENANCE' as type, am.created_at
            FROM asset_maintenance am

            UNION ALL

            SELECT 'EXIT' as type, ae.created_at
            FROM asset_exit ae
            WHERE ae.is_delete = 0

            UNION ALL

            SELECT 'BSP' as type, b.created_at
            FROM bsp b
            WHERE b.is_delete = 0
        ) as movements $whereClause";

        return (int) $conn->executeQuery($sql)->fetchOne();
    }
}
