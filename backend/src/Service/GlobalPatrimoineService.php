<?php

namespace App\Service;

use App\Entity\Asset;
use App\Entity\AssetAssignment;
use App\Entity\Category;
use App\Entity\Consumable;
use App\Entity\Departement;
use App\Entity\EtatBien;
use App\Entity\Project;
use App\Entity\Region;
use App\Entity\Service;
use App\Entity\Arrondissement;
use App\Entity\AssetType;
use App\Repository\AssetRepository;
use App\Repository\AssetAssignmentRepository;
use App\Repository\ConsumableRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service d'agrégation globale du patrimoine et des consommables.
 * 
 * Ce service fournit une vue consolidée et structurée de l'ensemble du patrimoine
 * et des consommables, avec des regroupements par différents critères.
 */
class GlobalPatrimoineService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AssetRepository $assetRepository,
        private readonly AssetAssignmentRepository $assignmentRepository,
        private readonly ConsumableRepository $consumableRepository,
        private readonly AssetResponseBuilder $assetResponseBuilder,
        private readonly PaginationFactory $paginationFactory
    ) {
    }

    /**
     * Récupère la vue globale complète du patrimoine et des consommables.
     * 
     * @param int $page Numéro de page (défaut: 1)
     * @param int $limit Nombre d'éléments par page (défaut: 100)
     */
    public function getGlobalPatrimoine(int $page = 1, int $limit = 100, array $filters = []): array
    {
        return [
            'PATRIMOINE_GLOBAL' => [
                'BIENS' => $this->getBiensData($page, $limit, $filters),
                'CONSOMMABLES' => $this->getConsommablesData($page, $limit, $filters),
            ],
        ];
    }

    /**
     * Récupère les données agrégées pour les biens.
     * 
     * @param int $page Numéro de page
     * @param int $limit Nombre d'éléments par page
     * @param array $filters Filtres optionnels (service, categorie, type, statut, etat, sousType)
     */
    private function getBiensData(int $page, int $limit, array $filters = []): array
    {
        return [
            'totalBiens' => $this->getTotalBiens($filters),
            'affectations' => $this->getAffectationsData($filters),
            'restitutions' => $this->getRestitutionsData($filters),
            'statuts' => $this->getStatutsData($filters),
            'Bien_parEtat' => $this->getBiensParEtat($page, $limit, $filters),
            'Bien_parRegion' => $this->getBiensParRegion($page, $limit, $filters),
            'Bien_parDepartement' => $this->getBiensParDepartement($page, $limit, $filters),
            'Bien_parArrondissement' => $this->getBiensParArrondissement($page, $limit, $filters),
            'Bien_parService' => $this->getBiensParService($page, $limit, $filters),
            'Bien_parProjet' => $this->getBiensParProjet($page, $limit, $filters),
            'Bien_parCategorie' => $this->getBiensParCategorie($page, $limit, $filters),
            'Bien_parType' => $this->getBiensParType($page, $limit, $filters),
            'Bien_parRegionDepartementCategorieEtat' => $this->getBiensParRegionDepartementCategorieEtat($page, $limit, $filters),
            'Bien_parCategorieRegion' => $this->getBiensParCategorieRegion($page, $limit, $filters),
        ];
    }

    /**
     * Récupère les données agrégées pour les consommables.
     * 
     * @param int $page Numéro de page
     * @param int $limit Nombre d'éléments par page
     * @param array $filters Filtres optionnels (service, categorie)
     */
    private function getConsommablesData(int $page, int $limit, array $filters = []): array
    {
        return [
            'totalConsommables' => $this->getTotalConsommables($filters),
            'Consommables_parService' => $this->getConsommablesParService($page, $limit, $filters),
            'Consommables_parCategorie' => $this->getConsommablesParCategorie($page, $limit, $filters),
        ];
    }

    /**
     * Applique les filtres à un QueryBuilder pour les biens.
     * 
     * @param \Doctrine\ORM\QueryBuilder $qb Le QueryBuilder à modifier
     * @param array $filters Les filtres à appliquer
     * @return \Doctrine\ORM\QueryBuilder Le QueryBuilder modifié
     */
    private function applyAssetFilters($qb, array $filters)
    {
        // Filtre par service (ids séparés par virgules)
        if (!empty($filters['service'])) {
            $serviceIds = array_map('intval', explode(',', $filters['service']));
            $qb->innerJoin('a.services', 's_filter')
               ->andWhere('s_filter.id IN (:serviceIds)')
               ->setParameter('serviceIds', $serviceIds);
        }

        // Filtre par catégorie (ids séparés par virgules)
        if (!empty($filters['categorie'])) {
            $categoryIds = array_map('intval', explode(',', $filters['categorie']));
            $qb->innerJoin('a.categories', 'c_filter')
               ->andWhere('c_filter.id IN (:categoryIds)')
               ->setParameter('categoryIds', $categoryIds);
        }

        // Filtre par type (AssetType ids séparés par virgules)
        if (!empty($filters['type'])) {
            $typeIds = array_map('intval', explode(',', $filters['type']));
            $qb->innerJoin('a.assetTypes', 't_filter')
               ->andWhere('t_filter.id IN (:typeIds)')
               ->setParameter('typeIds', $typeIds);
        }

        // Filtre par statut (valeurs séparées par virgules)
        if (!empty($filters['statut'])) {
            $statuts = explode(',', $filters['statut']);
            $qb->andWhere('a.statut IN (:statuts)')
               ->setParameter('statuts', $statuts);
        }

        // Filtre par état (EtatBien ids séparés par virgules)
        if (!empty($filters['etat'])) {
            $etatIds = array_map('intval', explode(',', $filters['etat']));
            $qb->innerJoin('a.etatBiens', 'e_filter')
               ->andWhere('e_filter.id IN (:etatIds)')
               ->setParameter('etatIds', $etatIds);
        }

        // Filtre par sous-type (AssetSubType ids séparés par virgules)
        if (!empty($filters['sousType'])) {
            $subTypeIds = array_map('intval', explode(',', $filters['sousType']));
            $qb->innerJoin('a.assetSubTypes', 'st_filter')
               ->andWhere('st_filter.id IN (:subTypeIds)')
               ->setParameter('subTypeIds', $subTypeIds);
        }

        return $qb;
    }

    /**
     * Applique les filtres à un QueryBuilder pour les consommables.
     * 
     * @param \Doctrine\ORM\QueryBuilder $qb Le QueryBuilder à modifier
     * @param array $filters Les filtres à appliquer
     * @return \Doctrine\ORM\QueryBuilder Le QueryBuilder modifié
     */
    private function applyConsumableFilters($qb, array $filters)
    {
        // Filtre par service (ids séparés par virgules)
        if (!empty($filters['service'])) {
            $serviceIds = array_map('intval', explode(',', $filters['service']));
            $qb->andWhere('c.service IN (:serviceIds)')
               ->setParameter('serviceIds', $serviceIds);
        }

        // Filtre par catégorie (ids séparés par virgules)
        if (!empty($filters['categorie'])) {
            $categoryIds = array_map('intval', explode(',', $filters['categorie']));
            $qb->innerJoin('c.category', 'cat_filter')
               ->andWhere('cat_filter.id IN (:categoryIds)')
               ->setParameter('categoryIds', $categoryIds);
        }

        return $qb;
    }

    /**
     * Compte le total des biens (avec filtres d'exclusion).
     */
    private function getTotalBiens(array $filters = []): int
    {
        $qb = $this->assetRepository->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.id)')
            ->where('a.isDelete = false')
            ->andWhere('a.statut != :sortis')
            ->andWhere('a.statut != :sortie')
            ->setParameter('sortis', 'SORTIS')
            ->setParameter('sortie', 'SORTIE');

        $qb = $this->applyAssetFilters($qb, $filters);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Récupère les données d'affectations (indicateurs uniquement).
     */
    private function getAffectationsData(array $filters = []): array
    {
        $qb = $this->assignmentRepository->createQueryBuilder('aa')
            ->select('COUNT(DISTINCT aa.id) as total')
            ->where('aa.isDelete = false')
            ->andWhere('aa.typeAffectation = :type')
            // ->andWhere('aa.detenteur = true')
            // ->andWhere('aa.dateFin IS NULL')
            ->setParameter('type', 'AFFECTATION');

        $total = (int) $qb->getQuery()->getSingleScalarResult();

        // Répartition par service
        $qbService = $this->assignmentRepository->createQueryBuilder('aa')
            ->select('s.id, s.nom, COUNT(DISTINCT aa.id) as total')
            ->innerJoin('aa.service', 's')
            ->where('aa.isDelete = false')
            ->andWhere('aa.typeAffectation = :type')
            ->andWhere('aa.detenteur = true')
            ->andWhere('aa.dateFin IS NULL')
            ->setParameter('type', 'AFFECTATION')
            ->groupBy('s.id, s.nom')
            ->orderBy('s.nom', 'ASC');

        $parService = array_map(function ($row) {
            return [
                'service' => ['id' => $row['id'], 'nom' => $row['nom']],
                'total' => (int) $row['total'],
            ];
        }, $qbService->getQuery()->getResult());

        return [
            'total' => $total,
            'parService' => $parService,
        ];
    }

    /**
     * Récupère les données de restitutions (indicateurs uniquement).
     */
    private function getRestitutionsData(array $filters = []): array
    {
        $qb = $this->assignmentRepository->createQueryBuilder('aa')
            ->select('COUNT(DISTINCT aa.id) as total')
            ->where('aa.isDelete = false')
            ->andWhere('aa.typeAffectation = :type')
            // ->andWhere('aa.detenteur = true')
            // ->andWhere('aa.dateFin IS NULL')
            ->setParameter('type', 'RESTITUTION');

        $total = (int) $qb->getQuery()->getSingleScalarResult();

        // Répartition par service
        $qbService = $this->assignmentRepository->createQueryBuilder('aa')
            ->select('s.id, s.nom, COUNT(DISTINCT aa.id) as total')
            ->innerJoin('aa.service', 's')
            ->where('aa.isDelete = false')
            ->andWhere('aa.typeAffectation = :type')
            // ->andWhere('aa.detenteur = true')
            // ->andWhere('aa.dateFin IS NULL')
            ->setParameter('type', 'RESTITUTION')
            ->groupBy('s.id, s.nom')
            ->orderBy('s.nom', 'ASC');

        $parService = array_map(function ($row) {
            return [
                'service' => ['id' => $row['id'], 'nom' => $row['nom']],
                'total' => (int) $row['total'],
            ];
        }, $qbService->getQuery()->getResult());

        return [
            'total' => $total,
            'parService' => $parService,
        ];
    }

    /**
     * Récupère les données de statuts (indicateurs uniquement).
     */
    private function getStatutsData(array $filters = []): array
    {
        $qb = $this->assetRepository->createQueryBuilder('a')
            ->select('a.statut, COUNT(DISTINCT a.id) as total')
            ->where('a.isDelete = false')
            ->groupBy('a.statut')
            ->orderBy('a.statut', 'ASC');

        $result = $qb->getQuery()->getResult();

        $statuts = [];
        foreach ($result as $row) {
            $statuts[$row['statut'] ?? 'NULL'] = (int) $row['total'];
        }

        return $statuts;
    }

    /**
     * Récupère les biens regroupés par état (avec liste de biens).
     * 
     * @param int $page Numéro de page
     * @param int $limit Nombre d'éléments par page
     * @param array $filters Filtres optionnels
     */
    private function getBiensParEtat(int $page, int $limit, array $filters = []): array
    {
        $qb = $this->assetRepository->createQueryBuilder('a')
            ->select('e.id, e.nom, COUNT(DISTINCT a.id) as total')
            ->innerJoin('a.etatBiens', 'e')
            ->where('a.isDelete = false')
            ->andWhere('a.statut != :sorti')
            ->andWhere('a.statut != :sortie')
            ->andWhere('e.nom != :reforme')
            ->setParameter('sorti', 'SORTIS')
            ->setParameter('sortie', 'SORTIE')
            ->setParameter('reforme', 'RÉFORMÉ')
            ->groupBy('e.id, e.nom')
            ->orderBy('e.nom', 'ASC');

        $qb = $this->applyAssetFilters($qb, $filters);

        $result = $qb->getQuery()->getResult();

        $parEtat = [];
        foreach ($result as $row) {
            $etatId = $row['id'];
            $etatNom = $row['nom'];
            $total = (int) $row['total'];
            
            // Récupérer les biens pour cet état avec pagination
            $biens = $this->getBiensByEtat($etatId, $page, $limit, $filters);
            
            $parEtat[$etatNom] = [
                'total' => $total,
                'biens' => $this->paginationFactory->createPaginatedResponse($biens, $page, $limit, $total),
            ];
        }

        return $parEtat;
    }

    /**
     * Récupère les biens regroupés par région (avec liste de biens).
     * 
     * @param int $page Numéro de page
     * @param int $limit Nombre d'éléments par page
     * @param array $filters Filtres optionnels
     */
    private function getBiensParRegion(int $page, int $limit, array $filters = []): array
    {
        $qb = $this->assetRepository->createQueryBuilder('a')
            ->select('r.id, r.nom, COUNT(DISTINCT a.id) as total')
            ->innerJoin('a.services', 's')
            ->innerJoin('s.region', 'r')
            ->where('a.isDelete = false')
            ->andWhere('a.statut != :sorti')
            ->andWhere('a.statut != :sortie')
            ->andWhere('r.id IS NOT NULL')
            ->setParameter('sorti', 'SORTIS')
            ->setParameter('sortie', 'SORTIE')
            ->groupBy('r.id, r.nom')
            ->orderBy('r.nom', 'ASC');

        $qb = $this->applyAssetFilters($qb, $filters);

        $result = $qb->getQuery()->getResult();

        $parRegion = [];
        foreach ($result as $row) {
            $regionId = $row['id'];
            $regionNom = $row['nom'];
            $total = (int) $row['total'];
            
            // Récupérer les biens pour cette région avec pagination
            $biens = $this->getBiensByRegion($regionId, $page, $limit, $filters);
            
            $parRegion[$regionNom] = [
                'total' => $total,
                'biens' => $this->paginationFactory->createPaginatedResponse($biens, $page, $limit, $total),
            ];
        }

        return $parRegion;
    }

    /**
     * Récupère les biens regroupés par département (avec liste de biens).
     * 
     * @param int $page Numéro de page
     * @param int $limit Nombre d'éléments par page
     * @param array $filters Filtres optionnels
     */
    private function getBiensParDepartement(int $page, int $limit, array $filters = []): array
    {
        $qb = $this->assetRepository->createQueryBuilder('a')
            ->select('d.id, d.nom, COUNT(DISTINCT a.id) as total')
            ->innerJoin('a.services', 's')
            ->innerJoin('s.departement', 'd')
            ->where('a.isDelete = false')
            ->andWhere('a.statut != :sorti')
            ->andWhere('a.statut != :sortie')
            ->andWhere('d.id IS NOT NULL')
            ->setParameter('sorti', 'SORTIS')
            ->setParameter('sortie', 'SORTIE')
            ->groupBy('d.id, d.nom')
            ->orderBy('d.nom', 'ASC');

        $qb = $this->applyAssetFilters($qb, $filters);

        $result = $qb->getQuery()->getResult();

        $parDepartement = [];
        foreach ($result as $row) {
            $departementId = $row['id'];
            $departementNom = $row['nom'];
            $total = (int) $row['total'];
            
            // Récupérer les biens pour ce département avec pagination
            $biens = $this->getBiensByDepartement($departementId, $page, $limit, $filters);
            
            $parDepartement[$departementNom] = [
                'total' => $total,
                'biens' => $this->paginationFactory->createPaginatedResponse($biens, $page, $limit, $total),
            ];
        }

        return $parDepartement;
    }

    /**
     * Récupère les biens regroupés par arrondissement (avec liste de biens).
     * 
     * @param int $page Numéro de page
     * @param int $limit Nombre d'éléments par page
     * @param array $filters Filtres optionnels
     */
    private function getBiensParArrondissement(int $page, int $limit, array $filters = []): array
    {
        $qb = $this->assetRepository->createQueryBuilder('a')
            ->select('arr.id, arr.nom, COUNT(DISTINCT a.id) as total')
            ->innerJoin('a.services', 's')
            ->innerJoin('s.arrondissement', 'arr')
            ->where('a.isDelete = false')
            ->andWhere('a.statut != :sorti')
            ->andWhere('a.statut != :sortie')
            ->andWhere('arr.id IS NOT NULL')
            ->setParameter('sorti', 'SORTIS')
            ->setParameter('sortie', 'SORTIE')
            ->groupBy('arr.id, arr.nom')
            ->orderBy('arr.nom', 'ASC');

        $qb = $this->applyAssetFilters($qb, $filters);

        $result = $qb->getQuery()->getResult();

        $parArrondissement = [];
        foreach ($result as $row) {
            $arrondissementId = $row['id'];
            $arrondissementNom = $row['nom'];
            $total = (int) $row['total'];
            
            // Récupérer les biens pour cet arrondissement avec pagination
            $biens = $this->getBiensByArrondissement($arrondissementId, $page, $limit, $filters);
            
            $parArrondissement[$arrondissementNom] = [
                'total' => $total,
                'biens' => $this->paginationFactory->createPaginatedResponse($biens, $page, $limit, $total),
            ];
        }

        return $parArrondissement;
    }

    /**
     * Récupère les biens regroupés par service (avec liste de biens).
     * 
     * @param int $page Numéro de page
     * @param int $limit Nombre d'éléments par page
     * @param array $filters Filtres optionnels
     */
    private function getBiensParService(int $page, int $limit, array $filters = []): array
    {
        $qb = $this->assetRepository->createQueryBuilder('a')
            ->select('s.id, s.nom, COUNT(DISTINCT a.id) as total')
            ->innerJoin('a.services', 's')
            ->where('a.isDelete = false')
            ->andWhere('a.statut != :sorti')
            ->andWhere('a.statut != :sortie')
            ->setParameter('sorti', 'SORTIS')
            ->setParameter('sortie', 'SORTIE')
            ->groupBy('s.id, s.nom')
            ->orderBy('s.nom', 'ASC');

        $qb = $this->applyAssetFilters($qb, $filters);

        $result = $qb->getQuery()->getResult();

        $parService = [];
        foreach ($result as $row) {
            $serviceId = $row['id'];
            $serviceNom = $row['nom'];
            $total = (int) $row['total'];
            
            // Récupérer les biens pour ce service avec pagination
            $biens = $this->getBiensByService($serviceId, $page, $limit, $filters);
            
            $parService[$serviceNom] = [
                'total' => $total,
                'biens' => $this->paginationFactory->createPaginatedResponse($biens, $page, $limit, $total),
            ];
        }

        return $parService;
    }

    /**
     * Récupère les biens regroupés par projet (avec liste de biens).
     * 
     * @param int $page Numéro de page
     * @param int $limit Nombre d'éléments par page
     * @param array $filters Filtres optionnels
     */
    private function getBiensParProjet(int $page, int $limit, array $filters = []): array
    {
        $qb = $this->assetRepository->createQueryBuilder('a')
            ->select('p.id, p.nom, COUNT(DISTINCT a.id) as total')
            ->innerJoin('a.projects', 'p')
            ->where('a.isDelete = false')
            ->andWhere('a.statut != :sorti')
            ->andWhere('a.statut != :sortie')
            ->andWhere('p.isDelete = false')
            ->setParameter('sorti', 'SORTIS')
            ->setParameter('sortie', 'SORTIE')
            ->groupBy('p.id, p.nom')
            ->orderBy('p.nom', 'ASC');

        $qb = $this->applyAssetFilters($qb, $filters);

        $result = $qb->getQuery()->getResult();

        $parProjet = [];
        foreach ($result as $row) {
            $projetId = $row['id'];
            $projetNom = $row['nom'];
            $total = (int) $row['total'];
            
            // Récupérer les biens pour ce projet avec pagination
            $biens = $this->getBiensByProjet($projetId, $page, $limit, $filters);
            
            $parProjet[$projetNom] = [
                'total' => $total,
                'biens' => $this->paginationFactory->createPaginatedResponse($biens, $page, $limit, $total),
            ];
        }

        return $parProjet;
    }

    /**
     * Récupère les biens regroupés par catégorie (avec liste de biens).
     * 
     * @param int $page Numéro de page
     * @param int $limit Nombre d'éléments par page
     * @param array $filters Filtres optionnels
     */
    private function getBiensParCategorie(int $page, int $limit, array $filters = []): array
    {
        $qb = $this->assetRepository->createQueryBuilder('a')
            ->select('c.id, c.nom, COUNT(DISTINCT a.id) as total')
            ->innerJoin('a.categories', 'c')
            ->where('a.isDelete = false')
            ->andWhere('a.statut != :sorti')
            ->andWhere('a.statut != :sortie')
            ->andWhere('c.isDelete = false')
            ->setParameter('sorti', 'SORTIS')
            ->setParameter('sortie', 'SORTIE')
            ->groupBy('c.id, c.nom')
            ->orderBy('c.nom', 'ASC');

        $qb = $this->applyAssetFilters($qb, $filters);

        $result = $qb->getQuery()->getResult();

        $parCategorie = [];
        foreach ($result as $row) {
            $categorieId = $row['id'];
            $categorieNom = $row['nom'];
            $total = (int) $row['total'];
            
            // Récupérer les biens pour cette catégorie avec pagination
            $biens = $this->getBiensByCategorie($categorieId, $page, $limit, $filters);
            
            $parCategorie[$categorieNom] = [
                'total' => $total,
                'biens' => $this->paginationFactory->createPaginatedResponse($biens, $page, $limit, $total),
            ];
        }

        return $parCategorie;
    }

    /**
     * Récupère les biens regroupés par type (avec liste de biens).
     * 
     * @param int $page Numéro de page
     * @param int $limit Nombre d'éléments par page
     * @param array $filters Filtres optionnels
     */
    private function getBiensParType(int $page, int $limit, array $filters = []): array
    {
        $qb = $this->assetRepository->createQueryBuilder('a')
            ->select('t.id, t.nom, COUNT(DISTINCT a.id) as total')
            ->innerJoin('a.assetTypes', 't')
            ->where('a.isDelete = false')
            ->andWhere('a.statut != :sorti')
            ->andWhere('a.statut != :sortie')
            ->andWhere('t.isDelete = false')
            ->setParameter('sorti', 'SORTIS')
            ->setParameter('sortie', 'SORTIE')
            ->groupBy('t.id, t.nom')
            ->orderBy('t.nom', 'ASC');

        $qb = $this->applyAssetFilters($qb, $filters);

        $result = $qb->getQuery()->getResult();

        $parType = [];
        foreach ($result as $row) {
            $typeId = $row['id'];
            $typeNom = $row['nom'];
            $total = (int) $row['total'];
            
            // Récupérer les biens pour ce type avec pagination
            $biens = $this->getBiensByType($typeId, $page, $limit, $filters);
            
            $parType[$typeNom] = [
                'total' => $total,
                'biens' => $this->paginationFactory->createPaginatedResponse($biens, $page, $limit, $total),
            ];
        }

        return $parType;
    }

    /**
     * Récupère les biens regroupés par combinaison Region-Departement-Categorie-Etat.
     * 
     * @param int $page Numéro de page
     * @param int $limit Nombre d'éléments par page
     * @param array $filters Filtres optionnels
     */
    private function getBiensParRegionDepartementCategorieEtat(int $page, int $limit, array $filters = []): array
    {
        $qb = $this->assetRepository->createQueryBuilder('a')
            ->select('r.id as region_id, r.nom as region_nom, d.id as dept_id, d.nom as dept_nom, c.id as cat_id, c.nom as cat_nom, e.id as etat_id, e.nom as etat_nom, COUNT(DISTINCT a.id) as total')
            ->innerJoin('a.services', 's')
            ->innerJoin('s.region', 'r')
            ->innerJoin('s.departement', 'd')
            ->innerJoin('a.categories', 'c')
            ->innerJoin('a.etatBiens', 'e')
            ->where('a.isDelete = false')
            ->andWhere('a.statut != :sorti')
            ->andWhere('a.statut != :sortie')
            ->andWhere('e.nom != :reforme')
            ->andWhere('r.id IS NOT NULL')
            ->andWhere('d.id IS NOT NULL')
            ->setParameter('sorti', 'SORTIS')
            ->setParameter('sortie', 'SORTIE')
            ->setParameter('reforme', 'RÉFORMÉ')
            ->groupBy('r.id, r.nom, d.id, d.nom, c.id, c.nom, e.id, e.nom')
            ->orderBy('r.nom', 'ASC')
            ->addOrderBy('d.nom', 'ASC')
            ->addOrderBy('c.nom', 'ASC')
            ->addOrderBy('e.nom', 'ASC');

        $qb = $this->applyAssetFilters($qb, $filters);

        $result = $qb->getQuery()->getResult();

        $parCombinaison = [];
        foreach ($result as $row) {
            $cle = $row['region_nom'] . '-' . $row['dept_nom'] . '-' . $row['cat_nom'] . '-' . $row['etat_nom'];
            $total = (int) $row['total'];
            
            // Récupérer les biens pour cette combinaison avec pagination
            $biens = $this->getBiensByRegionDepartementCategorieEtat(
                $row['region_id'],
                $row['dept_id'],
                $row['cat_id'],
                $row['etat_id'],
                $page,
                $limit,
                $filters
            );
            
            $parCombinaison[$cle] = [
                'total' => $total,
                'biens' => $this->paginationFactory->createPaginatedResponse($biens, $page, $limit, $total),
            ];
        }

        return $parCombinaison;
    }

    /**
     * Récupère les biens regroupés par combinaison Categorie-Region.
     * 
     * @param int $page Numéro de page
     * @param int $limit Nombre d'éléments par page
     * @param array $filters Filtres optionnels
     */
    private function getBiensParCategorieRegion(int $page, int $limit, array $filters = []): array
    {
        $qb = $this->assetRepository->createQueryBuilder('a')
            ->select('c.id as cat_id, c.nom as cat_nom, r.id as region_id, r.nom as region_nom, COUNT(DISTINCT a.id) as total')
            ->innerJoin('a.categories', 'c')
            ->innerJoin('a.services', 's')
            ->innerJoin('s.region', 'r')
            ->where('a.isDelete = false')
            ->andWhere('a.statut != :sorti')
            ->andWhere('a.statut != :sortie')
            ->andWhere('r.id IS NOT NULL')
            ->setParameter('sorti', 'SORTIS')
            ->setParameter('sortie', 'SORTIE')
            ->groupBy('c.id, c.nom, r.id, r.nom')
            ->orderBy('c.nom', 'ASC')
            ->addOrderBy('r.nom', 'ASC');

        $qb = $this->applyAssetFilters($qb, $filters);

        $result = $qb->getQuery()->getResult();

        $parCombinaison = [];
        foreach ($result as $row) {
            $cle = $row['cat_nom'] . '-' . $row['region_nom'];
            $total = (int) $row['total'];
            
            // Récupérer les biens pour cette combinaison avec pagination
            $biens = $this->getBiensByCategorieRegion($row['cat_id'], $row['region_id'], $page, $limit, $filters);
            
            $parCombinaison[$cle] = [
                'total' => $total,
                'biens' => $this->paginationFactory->createPaginatedResponse($biens, $page, $limit, $total),
            ];
        }

        return $parCombinaison;
    }

    /**
     * Compte le total des consommables.
     */
    private function getTotalConsommables(array $filters = []): int
    {
        $qb = $this->consumableRepository->createQueryBuilder('c')
            ->select('COUNT(DISTINCT c.id)')
            ->where('c.isDelete = false');

        $qb = $this->applyConsumableFilters($qb, $filters);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Récupère les consommables regroupés par service (avec liste de consommables).
     * 
     * @param int $page Numéro de page
     * @param int $limit Nombre d'éléments par page
     * @param array $filters Filtres optionnels
     */
    private function getConsommablesParService(int $page, int $limit, array $filters = []): array
    {
        $qb = $this->consumableRepository->createQueryBuilder('c')
            ->select('s.id, s.nom, COUNT(DISTINCT c.id) as total')
            ->innerJoin('c.service', 's')
            ->where('c.isDelete = false')
            ->andWhere('s.id IS NOT NULL')
            ->groupBy('s.id, s.nom')
            ->orderBy('s.nom', 'ASC');

        $qb = $this->applyConsumableFilters($qb, $filters);

        $result = $qb->getQuery()->getResult();

        $parService = [];
        foreach ($result as $row) {
            $serviceId = $row['id'];
            $serviceNom = $row['nom'];
            $total = (int) $row['total'];
            
            // Récupérer les consommables pour ce service avec pagination
            $consommables = $this->getConsommablesByService($serviceId, $page, $limit, $filters);
            
            $parService[$serviceNom] = [
                'total' => $total,
                'consommables' => $this->paginationFactory->createPaginatedResponse($consommables, $page, $limit, $total),
            ];
        }

        return $parService;
    }

    /**
     * Récupère les consommables regroupés par catégorie (avec liste de consommables).
     * 
     * @param int $page Numéro de page
     * @param int $limit Nombre d'éléments par page
     * @param array $filters Filtres optionnels
     */
    private function getConsommablesParCategorie(int $page, int $limit, array $filters = []): array
    {
        $qb = $this->consumableRepository->createQueryBuilder('c')
            ->select('cat.id, cat.nom, COUNT(DISTINCT c.id) as total')
            ->innerJoin('c.category', 'cat')
            ->where('c.isDelete = false')
            ->andWhere('cat.id IS NOT NULL')
            ->andWhere('cat.isDelete = false')
            ->groupBy('cat.id, cat.nom')
            ->orderBy('cat.nom', 'ASC');

        $qb = $this->applyConsumableFilters($qb, $filters);

        $result = $qb->getQuery()->getResult();

        $parCategorie = [];
        foreach ($result as $row) {
            $categorieId = $row['id'];
            $categorieNom = $row['nom'];
            $total = (int) $row['total'];
            
            // Récupérer les consommables pour cette catégorie avec pagination
            $consommables = $this->getConsommablesByCategorie($categorieId, $page, $limit, $filters);
            
            $parCategorie[$categorieNom] = [
                'total' => $total,
                'consommables' => $this->paginationFactory->createPaginatedResponse($consommables, $page, $limit, $total),
            ];
        }

        return $parCategorie;
    }

    // Méthodes auxiliaires pour récupérer les biens par critère

    private function getBiensByEtat(int $etatId, int $page = 1, int $limit = 100, array $filters = []): array
    {
        $qb = $this->assetRepository->createQueryBuilder('a')
            ->where('a.isDelete = false')
            ->andWhere('a.statut != :sorti')
            ->andWhere('a.statut != :sortie')
            ->innerJoin('a.etatBiens', 'e')
            ->andWhere('e.id = :etatId')
            ->setParameter('sorti', 'SORTIS')
            ->setParameter('sortie', 'SORTIE')
            ->setParameter('etatId', $etatId)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $qb = $this->applyAssetFilters($qb, $filters);

        $assets = $qb->getQuery()->getResult();
        
        return array_map([$this->assetResponseBuilder, 'buildListItem'], $assets);
    }

    private function getBiensByRegion(int $regionId, int $page = 1, int $limit = 100, array $filters = []): array
    {
        $qb = $this->assetRepository->createQueryBuilder('a')
            ->where('a.isDelete = false')
            ->andWhere('a.statut != :sorti')
            ->andWhere('a.statut != :sortie')
            ->innerJoin('a.services', 's')
            ->innerJoin('s.region', 'r')
            ->andWhere('r.id = :regionId')
            ->setParameter('sorti', 'SORTIS')
            ->setParameter('sortie', 'SORTIE')
            ->setParameter('regionId', $regionId)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $qb = $this->applyAssetFilters($qb, $filters);

        $assets = $qb->getQuery()->getResult();
        
        return array_map([$this->assetResponseBuilder, 'buildListItem'], $assets);
    }

    private function getBiensByDepartement(int $departementId, int $page = 1, int $limit = 100, array $filters = []): array
    {
        $qb = $this->assetRepository->createQueryBuilder('a')
            ->where('a.isDelete = false')
            ->andWhere('a.statut != :sorti')
            ->andWhere('a.statut != :sortie')
            ->innerJoin('a.services', 's')
            ->innerJoin('s.departement', 'd')
            ->andWhere('d.id = :departementId')
            ->setParameter('sorti', 'SORTIS')
            ->setParameter('sortie', 'SORTIE')
            ->setParameter('departementId', $departementId)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $qb = $this->applyAssetFilters($qb, $filters);

        $assets = $qb->getQuery()->getResult();
        
        return array_map([$this->assetResponseBuilder, 'buildListItem'], $assets);
    }

    private function getBiensByArrondissement(int $arrondissementId, int $page = 1, int $limit = 100, array $filters = []): array
    {
        $qb = $this->assetRepository->createQueryBuilder('a')
            ->where('a.isDelete = false')
            ->andWhere('a.statut != :sorti')
            ->andWhere('a.statut != :sortie')
            ->innerJoin('a.services', 's')
            ->innerJoin('s.arrondissement', 'arr')
            ->andWhere('arr.id = :arrondissementId')
            ->setParameter('sorti', 'SORTIS')
            ->setParameter('sortie', 'SORTIE')
            ->setParameter('arrondissementId', $arrondissementId)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $qb = $this->applyAssetFilters($qb, $filters);

        $assets = $qb->getQuery()->getResult();
        
        return array_map([$this->assetResponseBuilder, 'buildListItem'], $assets);
    }

    private function getBiensByService(int $serviceId, int $page = 1, int $limit = 100, array $filters = []): array
    {
        $qb = $this->assetRepository->createQueryBuilder('a')
            ->where('a.isDelete = false')
            ->andWhere('a.statut != :sorti')
            ->andWhere('a.statut != :sortie')
            ->innerJoin('a.services', 's')
            ->andWhere('s.id = :serviceId')
            ->setParameter('sorti', 'SORTIS')
            ->setParameter('sortie', 'SORTIE')
            ->setParameter('serviceId', $serviceId)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $qb = $this->applyAssetFilters($qb, $filters);

        $assets = $qb->getQuery()->getResult();
        
        return array_map([$this->assetResponseBuilder, 'buildListItem'], $assets);
    }

    private function getBiensByProjet(int $projetId, int $page = 1, int $limit = 100, array $filters = []): array
    {
        $qb = $this->assetRepository->createQueryBuilder('a')
            ->where('a.isDelete = false')
            ->andWhere('a.statut != :sorti')
            ->andWhere('a.statut != :sortie')
            ->innerJoin('a.projects', 'p')
            ->andWhere('p.id = :projetId')
            ->andWhere('p.isDelete = false')
            ->setParameter('sorti', 'SORTIS')
            ->setParameter('sortie', 'SORTIE')
            ->setParameter('projetId', $projetId)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $qb = $this->applyAssetFilters($qb, $filters);

        $assets = $qb->getQuery()->getResult();
        
        return array_map([$this->assetResponseBuilder, 'buildListItem'], $assets);
    }

    private function getBiensByCategorie(int $categorieId, int $page = 1, int $limit = 100, array $filters = []): array
    {
        $qb = $this->assetRepository->createQueryBuilder('a')
            ->where('a.isDelete = false')
            ->andWhere('a.statut != :sorti')
            ->andWhere('a.statut != :sortie')
            ->innerJoin('a.categories', 'c')
            ->andWhere('c.id = :categorieId')
            ->andWhere('c.isDelete = false')
            ->setParameter('sorti', 'SORTIS')
            ->setParameter('sortie', 'SORTIE')
            ->setParameter('categorieId', $categorieId)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $qb = $this->applyAssetFilters($qb, $filters);

        $assets = $qb->getQuery()->getResult();
        
        return array_map([$this->assetResponseBuilder, 'buildListItem'], $assets);
    }

    private function getBiensByType(int $typeId, int $page = 1, int $limit = 100, array $filters = []): array
    {
        $qb = $this->assetRepository->createQueryBuilder('a')
            ->where('a.isDelete = false')
            ->andWhere('a.statut != :sorti')
            ->andWhere('a.statut != :sortie')
            ->innerJoin('a.assetTypes', 't')
            ->andWhere('t.id = :typeId')
            ->andWhere('t.isDelete = false')
            ->setParameter('sorti', 'SORTIS')
            ->setParameter('sortie', 'SORTIE')
            ->setParameter('typeId', $typeId)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $qb = $this->applyAssetFilters($qb, $filters);

        $assets = $qb->getQuery()->getResult();
        
        return array_map([$this->assetResponseBuilder, 'buildListItem'], $assets);
    }

    private function getBiensByRegionDepartementCategorieEtat(int $regionId, int $departementId, int $categorieId, int $etatId, int $page = 1, int $limit = 100, array $filters = []): array
    {
        $qb = $this->assetRepository->createQueryBuilder('a')
            ->where('a.isDelete = false')
            ->andWhere('a.statut != :sorti')
            ->andWhere('a.statut != :sortie')
            ->innerJoin('a.services', 's')
            ->innerJoin('s.region', 'r')
            ->innerJoin('s.departement', 'd')
            ->innerJoin('a.categories', 'c')
            ->innerJoin('a.etatBiens', 'e')
            ->andWhere('r.id = :regionId')
            ->andWhere('d.id = :departementId')
            ->andWhere('c.id = :categorieId')
            ->andWhere('e.id = :etatId')
            ->andWhere('e.nom != :reforme')
            ->setParameter('sorti', 'SORTIS')
            ->setParameter('sortie', 'SORTIE')
            ->setParameter('reforme', 'RÉFORMÉ')
            ->setParameter('regionId', $regionId)
            ->setParameter('departementId', $departementId)
            ->setParameter('categorieId', $categorieId)
            ->setParameter('etatId', $etatId)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $qb = $this->applyAssetFilters($qb, $filters);

        $assets = $qb->getQuery()->getResult();
        
        return array_map([$this->assetResponseBuilder, 'buildListItem'], $assets);
    }

    private function getBiensByCategorieRegion(int $categorieId, int $regionId, int $page = 1, int $limit = 100, array $filters = []): array
    {
        $qb = $this->assetRepository->createQueryBuilder('a')
            ->where('a.isDelete = false')
            ->andWhere('a.statut != :sorti')
            ->andWhere('a.statut != :sortie')
            ->innerJoin('a.categories', 'c')
            ->innerJoin('a.services', 's')
            ->innerJoin('s.region', 'r')
            ->andWhere('c.id = :categorieId')
            ->andWhere('r.id = :regionId')
            ->setParameter('sorti', 'SORTIS')
            ->setParameter('sortie', 'SORTIE')
            ->setParameter('categorieId', $categorieId)
            ->setParameter('regionId', $regionId)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $qb = $this->applyAssetFilters($qb, $filters);

        $assets = $qb->getQuery()->getResult();
        
        return array_map([$this->assetResponseBuilder, 'buildListItem'], $assets);
    }

    private function getConsommablesByService(int $serviceId, int $page = 1, int $limit = 100, array $filters = []): array
    {
        $qb = $this->consumableRepository->createQueryBuilder('c')
            ->where('c.isDelete = false')
            ->andWhere('c.service = :serviceId')
            ->setParameter('serviceId', $serviceId)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $qb = $this->applyConsumableFilters($qb, $filters);

        $consumables = $qb->getQuery()->getResult();
        
        return array_map([$this, 'buildConsumableItem'], $consumables);
    }

    private function getConsommablesByCategorie(int $categorieId, int $page = 1, int $limit = 100, array $filters = []): array
    {
        $qb = $this->consumableRepository->createQueryBuilder('c')
            ->where('c.isDelete = false')
            ->innerJoin('c.category', 'cat')
            ->andWhere('cat.id = :categorieId')
            ->andWhere('cat.isDelete = false')
            ->setParameter('categorieId', $categorieId)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $qb = $this->applyConsumableFilters($qb, $filters);

        $consumables = $qb->getQuery()->getResult();
        
        return array_map([$this, 'buildConsumableItem'], $consumables);
    }

    private function buildConsumableItem(Consumable $consumable): array
    {
        return [
            'id' => $consumable->getId(),
            'nom' => $consumable->getNom(),
            'description' => $consumable->getDescription(),
            'categorie' => $consumable->getCategory() ? [
                'id' => $consumable->getCategory()->getId(),
                'nom' => $consumable->getCategory()->getNom(),
            ] : null,
            'assetType' => $consumable->getAssetType() ? [
                'id' => $consumable->getAssetType()->getId(),
                'nom' => $consumable->getAssetType()->getNom(),
            ] : null,
            'service' => $consumable->getService() ? [
                'id' => $consumable->getService()->getId(),
                'nom' => $consumable->getService()->getNom(),
            ] : null,
            // 'quantite' => $consumable->getQuantite(),
            'stockActuel' => $consumable->getStockActuel(),
            'prixInitial' => $consumable->getPrixInitial(),
            'prixTotal' => $consumable->getPrixTotal(),
        ];
    }
}
