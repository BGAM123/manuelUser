<?php
// src/Repository/StatisticsRepository.php

namespace App\Repository;

use App\Entity\Asset;
use App\Entity\AssetAssignment;
use App\Entity\AssetMaintenance;
use App\Entity\Category;
use App\Entity\Departement;
use App\Entity\Service;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class StatisticsRepository extends ServiceEntityRepository
{
    /**
     * KPIs volontairement non implémentés, tous azimuts (véhicules/terrains/bâtiments) : le champ
     * métier nécessaire n'est stocké nulle part dans le schéma actuel. Aucun d'eux n'expose de
     * clé placeholder (null) dans les réponses JSON — ils sont simplement absents tant que la
     * donnée n'existe pas.
     *
     * Résolution prévue via le catalogue `Champ` (App\Entity\Champ) une fois qu'il stockera une
     * valeur par bien (aujourd'hui, Champ ne définit qu'un nom de champ par catégorie, sans valeur
     * associée à un Asset — cf. les lignes commentées "❌ SUPPRIMEZ" dans Champ/ChampRepository).
     * Le jour où ce stockage existera : lire la valeur du Champ nommé ci-dessous, rattaché à la
     * bonne catégorie, et brancher dans la méthode indiquée.
     *
     * clé Champ => [domaine, méthode où brancher la donnée une fois disponible]
     */
    private const CHAMPS_EN_ATTENTE = [
        'Marque' => ['Matériel Roulant', 'getVehiculesRepartitionParType()'],
        'Superficie' => ['Terrain / Structure (surface au sol des infrastructures)', 'getTerrainsValeurTotale()'],
        'Loyer mensuel' => ['Terrain / Bâtiment', 'getTerrainsLoues() / getBatimentsLoues()'],
        'Nombre de pièces' => ['Bâtiment', '(pas encore de méthode — KPI "Nombre de pièces")'],
        'Motif d\'absence de déclaration' => ['Départements sans déclaration (écran suivi/évolution/alerte)', 'getDepartementsSansDeclaration()'],
    ];

    /**
     * KPIs "Structure" volontairement non implémentés pour une raison différente de
     * self::CHAMPS_EN_ATTENTE : ce ne sont pas des attributs par bien (donc pas résolubles via le
     * catalogue Champ, qui est rattaché à Category/Asset), mais des attributs propres à la
     * structure (Service) elle-même. Nécessitent de nouvelles colonnes sur l'entité Service (ou
     * une table dédiée), pas un mécanisme déjà en place. Idem CHAMPS_EN_ATTENTE : aucune clé
     * placeholder exposée dans les réponses JSON tant que la donnée n'existe pas.
     */
    private const ATTRIBUTS_STRUCTURE_EN_ATTENTE = [
        'Génère des recettes propres' => 'booléen à ajouter sur Service — KPI "Structures génératrices de recettes propres" (12)',
        'Régisseur nommé par le MINFI' => 'à ajouter sur Service (ou lié à un régisseur/User) — KPI (12), pertinent seulement pour les structures génératrices de recettes propres',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Asset::class);
    }

    public function calculateAll(array $filters): array
    {
        return [
            'general' => $this->getGeneralStatistics($filters),
            'top_services_biens' => $this->getTopServicesByBiens($filters),
            'top_projets_biens' => $this->getTopProjectsByBiens($filters),
            'top_services_valeur' => $this->getTopServicesByValeur($filters),
            'patrimoine_par_mois' => $this->getPatrimoineParMois($filters),
            'maintenance_par_mois' => $this->getMaintenanceParMois($filters),
            'biens_affectes_par_mois' => $this->getBiensAffectesParMois($filters),
        ];
    }

    public function getGeneralStatistics(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select(
                'COUNT(a.id) as total_biens',
                'COALESCE(SUM(a.valeur), 0) as valeur_totale_patrimoine'
            )
            ->where('a.isDelete = :isDelete')
            ->setParameter('isDelete', false);

        $this->applyFilters($qb, $filters);
        $result = $qb->getQuery()->getSingleResult();

        $servicesQb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT s.id) as total_services_avec_biens')
            ->innerJoin('a.services', 's')
            ->where('a.isDelete = :isDelete')
            ->andWhere('s.is_active = :isActive')
            ->setParameter('isDelete', false)
            ->setParameter('isActive', true);

        $this->applyFilters($servicesQb, $filters);
        $servicesResult = $servicesQb->getQuery()->getSingleResult();

        // Sortis : biens ayant une sortie (AssetExit) non supprimée. Plus fiable que a.statut,
        // qui peut être écrasé librement via PATCH /assets et n'est pas la source de vérité du cycle de vie.
        $sortisQb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.id) as total')
            ->innerJoin('a.sortie', 'ex')
            ->where('a.isDelete = :isDelete')
            ->andWhere('ex.isDelete = :isDeleteEx')
            ->setParameter('isDelete', false)
            ->setParameter('isDeleteEx', false);
        // excludeSortis=false : c'est justement cette requête qui les compte, l'exclusion par
        // défaut de applyFiltersOnAsset() la rendrait sinon contradictoire (toujours 0).
        $this->applyFilters($sortisQb, $filters, false);
        $totalSortis = (int) $sortisQb->getQuery()->getSingleScalarResult();

        // En maintenance : biens ayant une intervention (AssetMaintenance) sans date de récupération.
        // Aucune synchronisation n'existe entre AssetMaintenance et a.statut : on interroge la relation directement.
        $maintenanceQb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.id) as total')
            ->innerJoin('a.maintenances', 'm')
            ->where('a.isDelete = :isDelete')
            ->andWhere('m.dateRecuperation IS NULL')
            ->setParameter('isDelete', false);
        $this->applyFilters($maintenanceQb, $filters);
        $totalMaintenance = (int) $maintenanceQb->getQuery()->getSingleScalarResult();

        // Actifs : ni sorti, ni actuellement en maintenance.
        $actifsQb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.id) as total')
            ->leftJoin('a.sortie', 'ex2')
            ->leftJoin('a.maintenances', 'm2', Join::WITH, 'm2.dateRecuperation IS NULL')
            ->where('a.isDelete = :isDelete')
            ->andWhere('(ex2.id IS NULL OR ex2.isDelete = :isDeleteEx2)')
            ->andWhere('m2.id IS NULL')
            ->setParameter('isDelete', false)
            ->setParameter('isDeleteEx2', true);
        $this->applyFilters($actifsQb, $filters);
        $totalActifs = (int) $actifsQb->getQuery()->getSingleScalarResult();

        return [
            'total_biens' => (int) $result['total_biens'],
            'valeur_totale_patrimoine' => (float) $result['valeur_totale_patrimoine'],
            'total_biens_actifs' => $totalActifs,
            'total_biens_sortis' => $totalSortis,
            'total_biens_maintenance' => $totalMaintenance,
            'total_services_avec_biens' => (int) $servicesResult['total_services_avec_biens'],
        ];
    }

    /**
     * KPI "Nombre de biens par catégorie" + "Valeur totale du patrimoine par catégorie".
     */
    public function getRepartitionParCategorie(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select(
                'c.id as categorie_id',
                'c.nom as categorie_nom',
                'COUNT(a.id) as nombre_biens',
                'COALESCE(SUM(a.valeur), 0) as valeur_patrimoine'
            )
            ->innerJoin('a.categories', 'c')
            ->where('a.isDelete = :isDelete')
            ->andWhere('c.isDelete = :isDeleteC')
            ->setParameter('isDelete', false)
            ->setParameter('isDeleteC', false)
            ->groupBy('c.id, c.nom')
            ->orderBy('nombre_biens', 'DESC');

        $this->applyFilters($qb, $filters);
        $results = $qb->getQuery()->getResult();

        return array_map(fn($row) => [
            'categorie_id' => (int) $row['categorie_id'],
            'categorie_nom' => $row['categorie_nom'],
            'nombre_biens' => (int) $row['nombre_biens'],
            'valeur_patrimoine' => (float) $row['valeur_patrimoine'],
        ], $results);
    }

    public function getRepartitionServicesCentralDeconcentre(array $filters): array
    {
        $qbTotal = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.id) as total')
            ->innerJoin('a.services', 's')
            ->where('a.isDelete = :isDelete')
            ->andWhere('s.is_active = :isActive')
            ->setParameter('isDelete', false)
            ->setParameter('isActive', true);
        $this->applyFilters($qbTotal, $filters);
        $totalBiens = (int) $qbTotal->getQuery()->getSingleScalarResult();

        $keywordMap = [
            'Centrale' => ['%centrale%', '%central%'],
            'Déconcentrée' => ['%déconcentr%', '%deconcentr%'],
            'Rattachée' => ['%rattach%'],
            'Sous tutelle' => ['%tutelle%'],
        ];

        $out = [];
        $totalClasses = 0;
        foreach ($keywordMap as $label => $patterns) {
            $qb = $this->createQueryBuilder('a')
                ->select('COUNT(DISTINCT a.id) as nombre_biens')
                ->innerJoin('a.services', 's')
                ->innerJoin('s.typeOrganigrammes', 'to')
                ->where('a.isDelete = :isDelete')
                ->andWhere('s.is_active = :isActive')
                ->andWhere('to.isDelete = false')
                ->setParameter('isDelete', false)
                ->setParameter('isActive', true);

            $orX = [];
            foreach ($patterns as $i => $pattern) {
                $orX[] = "LOWER(to.nom) LIKE :kw{$i}";
                $qb->setParameter("kw{$i}", $pattern);
            }
            $qb->andWhere(implode(' OR ', $orX));
            
            $this->applyFilters($qb, $filters);
            $count = (int) $qb->getQuery()->getSingleScalarResult();
            $out[$label] = $count;
            $totalClasses += $count;
        }

        // Pour la rétro-compatibilité avec le controller s'il attend l'ancien format,
        // mais le tableau sera transformé.
        $out['Non classée'] = max(0, $totalBiens - $totalClasses);

        return $out;
    }

    /**
     * KPI "Biens en mauvais état (alerte)".
     * Recherche par mot-clé sur le nom de l'état (insensible à la casse), à l'image du pattern
     * déjà utilisé pour repérer l'état "Réformé" (EtatBienRepository::findReformeEtat).
     *
     * "À RÉFORMER" (bien pas encore réformé, à traiter en priorité) compte comme mauvais état.
     * "RÉFORMÉ" (bien déjà sorti du cycle actif via le workflow de réforme) n'en fait PAS partie :
     * on cible donc explicitement "à réformer" et on exclut "réformé" par sécurité.
     */
    public function getBiensMauvaisEtat(array $filters): array
    {
        $patterns = [
            "%hors d%usage%",
            '%vétust%',
            '%vetust%',
            '%panne%',
            '%à réformer%',
            '%a réformer%',
            '%a reformer%',
        ];

        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.id) as total')
            ->innerJoin('a.etatBiens', 'e')
            ->where('a.isDelete = :isDelete')
            ->andWhere('e.isDelete = :isDeleteE')
            ->setParameter('isDelete', false)
            ->setParameter('isDeleteE', false)
            ->andWhere('LOWER(e.nom) NOT LIKE :excludeReforme')
            ->setParameter('excludeReforme', '%réformé%');

        $orX = [];
        foreach ($patterns as $i => $pattern) {
            $orX[] = "LOWER(e.nom) LIKE :etatKw{$i}";
            $qb->setParameter("etatKw{$i}", $pattern);
        }
        $qb->andWhere(implode(' OR ', $orX));

        $this->applyFilters($qb, $filters);
        $mauvaisEtat = (int) $qb->getQuery()->getSingleScalarResult();

        $totalQb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.id) as total')
            ->where('a.isDelete = :isDelete')
            ->setParameter('isDelete', false);
        $this->applyFilters($totalQb, $filters);
        $total = (int) $totalQb->getQuery()->getSingleScalarResult();

        return [
            'nombre_biens' => $mauvaisEtat,
            'total_biens' => $total,
            'pourcentage' => $total > 0 ? round(($mauvaisEtat / $total) * 100, 2) : 0.0,
        ];
    }

    /**
     * KPI "Biens sans information" : état / occupation (affectation) / sécurisation non renseignés.
     */
    public function getBiensSansInformation(array $filters): array
    {
        $sansEtatQb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.id) as total')
            ->leftJoin('a.etatBiens', 'e')
            ->where('a.isDelete = :isDelete')
            ->andWhere('e.id IS NULL')
            ->setParameter('isDelete', false);
        $this->applyFilters($sansEtatQb, $filters);
        $sansEtat = (int) $sansEtatQb->getQuery()->getSingleScalarResult();

        $sansAffectationQb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.id) as total')
            ->leftJoin('a.assignments', 'ass', Join::WITH, 'ass.isDelete = false')
            ->where('a.isDelete = :isDelete')
            ->andWhere('ass.id IS NULL')
            ->setParameter('isDelete', false);
        $this->applyFilters($sansAffectationQb, $filters);
        $sansOccupation = (int) $sansAffectationQb->getQuery()->getSingleScalarResult();

        $sansSecuriteQb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.id) as total')
            ->leftJoin('a.assetSecurities', 'sec')
            ->where('a.isDelete = :isDelete')
            ->andWhere('sec.id IS NULL')
            ->setParameter('isDelete', false);
        $this->applyFilters($sansSecuriteQb, $filters);
        $sansSecurisation = (int) $sansSecuriteQb->getQuery()->getSingleScalarResult();

        return [
            'sans_etat' => $sansEtat,
            'sans_occupation' => $sansOccupation,
            'sans_securisation' => $sansSecurisation,
        ];
    }

    /**
     * KPI "Évolution du patrimoine (écart entre deux périodes)" : par région et catégorie, biens
     * acquis durant `$anneeDebut` vs `$anneeFin`, et écart (GAP). `$anneeDebut` n'est pas
     * nécessairement `$anneeFin - 1` : l'appelant peut comparer deux années quelconques
     * (généralisation de l'ancien comportement N-1 vs N, conservé comme valeur par défaut côté
     * contrôleur pour rester rétrocompatible avec `?annee=`).
     */
    public function getEvolutionGap(array $filters, int $anneeDebut, int $anneeFin): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select(
                'r.id as region_id',
                'r.nom as region_nom',
                'c.id as categorie_id',
                'c.nom as categorie_nom',
                'SUBSTRING(a.dateAcquisition, 1, 4) as annee',
                'COUNT(DISTINCT a.id) as nombre_biens'
            )
            ->innerJoin('a.services', 's')
            ->innerJoin('s.region', 'r')
            ->innerJoin('a.categories', 'c')
            ->where('a.isDelete = :isDelete')
            ->andWhere('s.is_active = :isActive')
            ->andWhere('a.dateAcquisition IS NOT NULL')
            ->andWhere('SUBSTRING(a.dateAcquisition, 1, 4) IN (:annees)')
            ->setParameter('isDelete', false)
            ->setParameter('isActive', true)
            ->setParameter('annees', [(string) $anneeDebut, (string) $anneeFin])
            ->groupBy('r.id, r.nom, c.id, c.nom, annee');

        $this->applyFilters($qb, $filters);
        $results = $qb->getQuery()->getResult();

        $matrix = [];
        foreach ($results as $row) {
            $key = $row['region_id'] . '_' . $row['categorie_id'];
            if (!isset($matrix[$key])) {
                $matrix[$key] = [
                    'region_id' => (int) $row['region_id'],
                    'region_nom' => $row['region_nom'],
                    'categorie_id' => (int) $row['categorie_id'],
                    'categorie_nom' => $row['categorie_nom'],
                    'annee_debut' => 0,
                    'annee_fin' => 0,
                ];
            }
            if ((int) $row['annee'] === $anneeFin) {
                $matrix[$key]['annee_fin'] = (int) $row['nombre_biens'];
            } else {
                $matrix[$key]['annee_debut'] = (int) $row['nombre_biens'];
            }
        }

        return array_values(array_map(function ($row) {
            $row['gap'] = $row['annee_fin'] - $row['annee_debut'];
            return $row;
        }, $matrix));
    }

    /**
     * KPI "Évolution sur plusieurs années" : série complète (toutes années confondues, pas
     * seulement deux) du nombre de biens acquis, par région et catégorie.
     */
    public function getEvolutionPluriannuelle(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select(
                'r.id as region_id',
                'r.nom as region_nom',
                'c.id as categorie_id',
                'c.nom as categorie_nom',
                'SUBSTRING(a.dateAcquisition, 1, 4) as annee',
                'COUNT(DISTINCT a.id) as nombre_biens'
            )
            ->innerJoin('a.services', 's')
            ->innerJoin('s.region', 'r')
            ->innerJoin('a.categories', 'c')
            ->where('a.isDelete = :isDelete')
            ->andWhere('s.is_active = :isActive')
            ->andWhere('a.dateAcquisition IS NOT NULL')
            ->setParameter('isDelete', false)
            ->setParameter('isActive', true)
            ->groupBy('r.id, r.nom, c.id, c.nom, annee')
            ->orderBy('r.nom', 'ASC')
            ->addOrderBy('c.nom', 'ASC')
            ->addOrderBy('annee', 'ASC');

        $this->applyFilters($qb, $filters);
        $results = $qb->getQuery()->getResult();

        $grouped = [];
        foreach ($results as $row) {
            $key = $row['region_id'] . '_' . $row['categorie_id'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'region_id' => (int) $row['region_id'],
                    'region_nom' => $row['region_nom'],
                    'categorie_id' => (int) $row['categorie_id'],
                    'categorie_nom' => $row['categorie_nom'],
                    'series' => [],
                ];
            }
            $grouped[$key]['series'][] = [
                'annee' => $row['annee'],
                'nombre_biens' => (int) $row['nombre_biens'],
            ];
        }

        return array_values($grouped);
    }

    /**
     * KPI "Classement annuel des régions" : pour une année donnée, rang de chaque région dans
     * chaque catégorie (sur les biens acquis cette année-là — même convention "flux annuel" que
     * getEvolutionGap / getEvolutionPluriannuelle, pas un stock cumulé).
     */
    public function getClassementAnnuelRegions(array $filters, int $annee): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select(
                'c.id as categorie_id',
                'c.nom as categorie_nom',
                'r.id as region_id',
                'r.nom as region_nom',
                'COUNT(DISTINCT a.id) as nombre_biens'
            )
            ->innerJoin('a.categories', 'c')
            ->innerJoin('a.services', 's')
            ->innerJoin('s.region', 'r')
            ->where('a.isDelete = :isDelete')
            ->andWhere('s.is_active = :isActive')
            ->andWhere('SUBSTRING(a.dateAcquisition, 1, 4) = :annee')
            ->setParameter('isDelete', false)
            ->setParameter('isActive', true)
            ->setParameter('annee', (string) $annee)
            ->groupBy('c.id, c.nom, r.id, r.nom')
            ->orderBy('c.nom', 'ASC')
            ->addOrderBy('nombre_biens', 'DESC');

        $this->applyFilters($qb, $filters);
        $results = $qb->getQuery()->getResult();

        $grouped = [];
        foreach ($results as $row) {
            $catId = (int) $row['categorie_id'];
            if (!isset($grouped[$catId])) {
                $grouped[$catId] = [
                    'categorie_id' => $catId,
                    'categorie_nom' => $row['categorie_nom'],
                    'annee' => $annee,
                    'regions' => [],
                ];
            }
            $grouped[$catId]['regions'][] = [
                'region_id' => (int) $row['region_id'],
                'region_nom' => $row['region_nom'],
                'nombre_biens' => (int) $row['nombre_biens'],
            ];
        }

        // Rang explicite : les régions sont déjà triées par nombre_biens décroissant (orderBy ci-dessus).
        foreach ($grouped as &$categorie) {
            foreach ($categorie['regions'] as $i => &$region) {
                $region['rang'] = $i + 1;
            }
            unset($region);
        }
        unset($categorie);

        return array_values($grouped);
    }

    /**
     * KPI "Départements sans déclaration" : départements n'ayant aucun bien de la catégorie donnée.
     * Le "motif" n'est pas exposé : voir self::CHAMPS_EN_ATTENTE.
     */
    public function getDepartementsSansDeclaration(array $filters, int $categorieId): array
    {
        $avecQb = $this->createQueryBuilder('a')
            ->select('DISTINCT d.id as id')
            ->innerJoin('a.categories', 'c')
            ->innerJoin('a.services', 's')
            ->innerJoin('s.departement', 'd')
            ->where('a.isDelete = :isDelete')
            ->andWhere('s.is_active = :isActive')
            ->andWhere('c.id = :categorieId')
            ->andWhere('c.isDelete = :isDeleteC')
            ->setParameter('isDelete', false)
            ->setParameter('isActive', true)
            ->setParameter('categorieId', $categorieId)
            ->setParameter('isDeleteC', false);
        $this->applyFilters($avecQb, $filters);
        $avecIds = array_map(fn($row) => (int) $row['id'], $avecQb->getQuery()->getResult());

        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('d.id as id', 'd.nom as nom', 'r.id as region_id', 'r.nom as region_nom')
            ->from(Departement::class, 'd')
            ->innerJoin('d.region', 'r')
            ->where('d.isDelete = false')
            ->orderBy('r.nom', 'ASC')
            ->addOrderBy('d.nom', 'ASC');
        $all = $qb->getQuery()->getResult();

        $sansDeclaration = array_values(array_filter(
            $all,
            fn($row) => !in_array((int) $row['id'], $avecIds, true)
        ));

        return array_map(fn($row) => [
            'departement_id' => (int) $row['id'],
            'departement_nom' => $row['nom'],
            'region_id' => (int) $row['region_id'],
            'region_nom' => $row['region_nom'],
        ], $sansDeclaration);
    }

    /**
     * KPI "Suivi de la collecte de données" : nouveaux biens enregistrés et biens mis à jour,
     * semaine ISO par semaine ISO. Bucketing fait en PHP (pas de fonction DQL YEARWEEK
     * disponible dans ce projet — cf. les autres usages de SUBSTRING pour le même type de
     * contournement).
     */
    public function getSuiviCollecteHebdomadaire(array $filters, ?\DateTimeImmutable $depuis = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('a.createdAt as createdAt', 'a.updatedAt as updatedAt')
            ->where('a.isDelete = :isDelete')
            ->setParameter('isDelete', false);

        if (null !== $depuis) {
            $qb->andWhere('a.createdAt >= :depuis')->setParameter('depuis', $depuis);
        }

        $this->applyFilters($qb, $filters);
        $results = $qb->getQuery()->getResult();

        $creations = [];
        $misesAJour = [];
        foreach ($results as $row) {
            /** @var \DateTimeImmutable $createdAt */
            $createdAt = $row['createdAt'];
            $creations[$createdAt->format('o-\WW')] = ($creations[$createdAt->format('o-\WW')] ?? 0) + 1;

            /** @var \DateTimeImmutable|null $updatedAt */
            $updatedAt = $row['updatedAt'];
            if (null !== $updatedAt && $updatedAt > $createdAt) {
                $semaine = $updatedAt->format('o-\WW');
                $misesAJour[$semaine] = ($misesAJour[$semaine] ?? 0) + 1;
            }
        }

        ksort($creations);
        ksort($misesAJour);

        return [
            'nouveaux_biens_par_semaine' => array_map(
                fn(string $semaine, int $nombre) => ['semaine' => $semaine, 'nombre' => $nombre],
                array_keys($creations),
                array_values($creations)
            ),
            'biens_mis_a_jour_par_semaine' => array_map(
                fn(string $semaine, int $nombre) => ['semaine' => $semaine, 'nombre' => $nombre],
                array_keys($misesAJour),
                array_values($misesAJour)
            ),
        ];
    }

    /**
     * KPI "Points d'attention prioritaires" : sites (terrains/bâtiments) dont l'état contient
     * "prioritaire", avec leur statut de sécurisation courant comme indicateur de traitement.
     * Hypothèse à confirmer : pas de flag "priorité" dédié dans le schéma — même mécanisme
     * ÉtatBien par mot-clé que "Disparu" / "En litige" ailleurs.
     */
    /**
     * BUG CORRIGÉ (500 remonté par le frontend, via /dashboard), deux causes cumulées :
     * 1) chargeait l'entité Asset complète (`SELECT a`) → colonne `active_depreciation` absente
     *    de la table réelle (cf. commentaire de getTerrainsEnLitige()) ;
     * 2) `$security->getSecurityMode()?->getNom()` supposait une relation vers l'entité
     *    SecurityMode, qui n'existe plus (`Security::securityMode` est un simple texte depuis un
     *    refactoring ultérieur, cf. Security.php) → aurait été une erreur fatale PHP (appel de
     *    méthode sur une chaîne) si la requête avait atteint ce point.
     * Réécrite en 3 requêtes scalaires (sites, localisation, sécurisation) au lieu de parcourir
     * le graphe d'objets, pour ne dépendre que des colonnes réellement nécessaires.
     */
    public function getPointsAttentionPrioritaires(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('DISTINCT a.id as id', 'a.nom as nom', 'a.reference as reference')
            ->innerJoin('a.etatBiens', 'e')
            ->innerJoin('a.services', 's')
            ->where('a.isDelete = :isDelete')
            ->andWhere('s.is_active = :isActive')
            ->andWhere('e.isDelete = :isDeleteE')
            ->andWhere('LOWER(e.nom) LIKE :prioritaire')
            ->setParameter('isDelete', false)
            ->setParameter('isActive', true)
            ->setParameter('isDeleteE', false)
            ->setParameter('prioritaire', '%prioritaire%');
        $this->applySiteScope($qb);
        $this->applyFilters($qb, $filters);

        $sites1 = $qb->getQuery()->getResult();
        
        $qb2 = $this->createQueryBuilder('a')
            ->select('DISTINCT a.id as id', 'a.nom as nom', 'a.reference as reference')
            ->innerJoin('a.services', 's')
            ->leftJoin('a.assetSecurities', 'sec', Join::WITH, 'sec.isDelete = false')
            ->where('a.isDelete = :isDelete')
            ->andWhere('s.is_active = :isActive')
            ->andWhere('sec.id IS NULL')
            ->setParameter('isDelete', false)
            ->setParameter('isActive', true);
        $this->applyLandScope($qb2);
        $this->applyFilters($qb2, $filters);
        
        $sites2 = $qb2->getQuery()->getResult();
        
        // Fusion des deux listes
        $sites = $sites1;
        $existingIds = array_column($sites1, 'id');
        foreach ($sites2 as $s2) {
            if (!in_array($s2['id'], $existingIds, true)) {
                $sites[] = $s2;
            }
        }

        if (empty($sites)) {
            return [];
        }

        $ids = array_map(fn(array $row) => (int) $row['id'], $sites);

        $locRows = $this->createQueryBuilder('a')
            ->select(
                'a.id as id',
                'r.nom as region_nom',
                'd.nom as departement_nom',
                'ar.nom as arrondissement_nom'
            )
            ->innerJoin('a.services', 's')
            ->leftJoin('s.region', 'r')
            ->leftJoin('s.departement', 'd')
            ->leftJoin('s.arrondissement', 'ar')
            ->where('a.id IN (:ids)')
            ->andWhere('s.is_active = true')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $locByAsset = [];
        foreach ($locRows as $row) {
            $id = (int) $row['id'];
            if (!isset($locByAsset[$id])) {
                $locByAsset[$id] = [
                    'region_nom' => $row['region_nom'],
                    'departement_nom' => $row['departement_nom'],
                    'arrondissement_nom' => $row['arrondissement_nom'],
                ];
            }
        }

        $secRows = $this->createQueryBuilder('a')
            ->select('a.id as id', 'sec.securityMode as mode')
            ->innerJoin('a.assetSecurities', 'asec')
            ->innerJoin('asec.security', 'sec')
            ->where('a.id IN (:ids)')
            ->andWhere('asec.isDelete = false')
            ->andWhere('sec.isDelete = false')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $secByAsset = [];
        foreach ($secRows as $row) {
            $id = (int) $row['id'];
            $modeNom = mb_strtolower($row['mode'] ?? '');
            $secByAsset[$id]['juridique'] = ($secByAsset[$id]['juridique'] ?? false) || str_contains($modeNom, 'juridique');
            $secByAsset[$id]['physique'] = ($secByAsset[$id]['physique'] ?? false) || str_contains($modeNom, 'physique');
        }

        return array_map(function (array $row) use ($locByAsset, $secByAsset) {
            $id = (int) $row['id'];
            $loc = $locByAsset[$id] ?? ['region_nom' => null, 'departement_nom' => null, 'arrondissement_nom' => null];
            $sec = $secByAsset[$id] ?? ['juridique' => false, 'physique' => false];

            $traitement = match (true) {
                $sec['juridique'] && $sec['physique'] => 'Sécurisé (juridique + physique)',
                $sec['juridique'] => 'Sécurisation juridique uniquement',
                $sec['physique'] => 'Sécurisation physique uniquement',
                default => 'Non traité',
            };

            return [
                'id' => $id,
                'site' => $row['nom'] ?: $row['reference'],
                'region_nom' => $loc['region_nom'],
                'departement_nom' => $loc['departement_nom'],
                'arrondissement_nom' => $loc['arrondissement_nom'],
                'traitement' => $traitement,
            ];
        }, $sites);
    }

    /**
     * Restreint une requête aux "sites" (terrains ou bâtiments), pour le KPI "points d'attention
     * prioritaires" de l'écran suivi/évolution/alerte.
     */
    private function applySiteScope(QueryBuilder $qb, string $alias = 'a'): void
    {
        $qb->innerJoin("{$alias}.categories", 'catsite')
            ->andWhere('(LOWER(catsite.nom) LIKE :terrainTypeSite OR LOWER(catsite.nom) LIKE :batimentTypeSite OR catsite.id IN (:siteCatIds))')
            ->andWhere('catsite.isDelete = :catsiteDelete')
            ->setParameter('terrainTypeSite', '%terrain%')
            ->setParameter('batimentTypeSite', '%bâtiment%')
            ->setParameter('siteCatIds', [29, 35])
            ->setParameter('catsiteDelete', false);
    }

    /**
     * KPI "Classement des régions" : pour chaque catégorie, régions classées par nombre de biens décroissant.
     */
    public function getClassementRegionsParCategorie(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select(
                'c.id as categorie_id',
                'c.nom as categorie_nom',
                'r.id as region_id',
                'r.nom as region_nom',
                'COUNT(DISTINCT a.id) as nombre_biens'
            )
            ->innerJoin('a.categories', 'c')
            ->innerJoin('a.services', 's')
            ->innerJoin('s.region', 'r')
            ->where('a.isDelete = :isDelete')
            ->andWhere('s.is_active = :isActive')
            ->setParameter('isDelete', false)
            ->setParameter('isActive', true)
            ->groupBy('c.id, c.nom, r.id, r.nom')
            ->orderBy('c.nom', 'ASC')
            ->addOrderBy('nombre_biens', 'DESC');

        $this->applyFilters($qb, $filters);
        $results = $qb->getQuery()->getResult();

        $grouped = [];
        foreach ($results as $row) {
            $catId = (int) $row['categorie_id'];
            if (!isset($grouped[$catId])) {
                $grouped[$catId] = [
                    'categorie_id' => $catId,
                    'categorie_nom' => $row['categorie_nom'],
                    'regions' => [],
                ];
            }
            $grouped[$catId]['regions'][] = [
                'region_id' => (int) $row['region_id'],
                'region_nom' => $row['region_nom'],
                'nombre_biens' => (int) $row['nombre_biens'],
            ];
        }

        return array_values($grouped);
    }

    public function getTopServicesByBiens(array $filters, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select(
                's.id as service_id',
                's.nom as service_nom',
                'COUNT(a.id) as nombre_biens'
            )
            ->innerJoin('a.services', 's')
            ->where('a.isDelete = :isDelete')
            ->andWhere('s.is_active = :isActive')
            ->setParameter('isDelete', false)
            ->setParameter('isActive', true)
            ->groupBy('s.id, s.nom')
            ->orderBy('nombre_biens', 'DESC')
            ->setMaxResults($limit);

        $this->applyFilters($qb, $filters);
        $results = $qb->getQuery()->getResult();

        return array_map(fn($row) => [
            'service_id' => (int) $row['service_id'],
            'service_nom' => $row['service_nom'],
            'nombre_biens' => (int) $row['nombre_biens'],
        ], $results);
    }

    public function getTopProjectsByBiens(array $filters, int $limit = 5): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select(
                'p.id as projet_id',
                'p.nom as projet_nom',
                'COUNT(a.id) as nombre_biens'
            )
            ->innerJoin('a.projects', 'p')
            ->where('a.isDelete = :isDelete')
            ->andWhere('p.isDelete = :isDelete')
            ->setParameter('isDelete', false)
            ->groupBy('p.id, p.nom')
            ->orderBy('nombre_biens', 'DESC')
            ->setMaxResults($limit);

        $this->applyFilters($qb, $filters);
        $results = $qb->getQuery()->getResult();

        return array_map(fn($row) => [
            'projet_id' => (int) $row['projet_id'],
            'projet_nom' => $row['projet_nom'],
            'nombre_biens' => (int) $row['nombre_biens'],
        ], $results);
    }

    public function getRepartitionParRegion(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select(
                'r.id as region_id',
                'r.nom as region_nom',
                'COUNT(a.id) as nombre_biens',
                'COALESCE(SUM(a.valeur), 0) as valeur_patrimoine'
            )
            ->innerJoin('a.services', 's')
            ->innerJoin('s.region', 'r')
            ->where('a.isDelete = :isDelete')
            ->andWhere('s.is_active = :isActive')
            ->setParameter('isDelete', false)
            ->setParameter('isActive', true)
            ->groupBy('r.id, r.nom')
            ->orderBy('nombre_biens', 'DESC');

        $this->applyFilters($qb, $filters);
        $results = $qb->getQuery()->getResult();

        return array_map(fn($row) => [
            'region_id' => (int) $row['region_id'],
            'region_nom' => $row['region_nom'],
            'nombre_biens' => (int) $row['nombre_biens'],
            'valeur_patrimoine' => (float) $row['valeur_patrimoine'],
        ], $results);
    }

    public function getTopServicesByValeur(array $filters, int $limit = 6): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select(
                's.id as service_id',
                's.nom as service_nom',
                'COALESCE(SUM(a.valeur), 0) as valeur_patrimoine',
                'COUNT(a.id) as nombre_biens'
            )
            ->innerJoin('a.services', 's')
            ->where('a.isDelete = :isDelete')
            ->andWhere('s.is_active = :isActive')
            ->andWhere('a.valeur IS NOT NULL')
            ->setParameter('isDelete', false)
            ->setParameter('isActive', true)
            ->groupBy('s.id, s.nom')
            ->orderBy('valeur_patrimoine', 'DESC')
            ->setMaxResults($limit);

        $this->applyFilters($qb, $filters);
        $results = $qb->getQuery()->getResult();

        return array_map(fn($row) => [
            'service_id' => (int) $row['service_id'],
            'service_nom' => $row['service_nom'],
            'valeur_patrimoine' => (float) $row['valeur_patrimoine'],
            'nombre_biens' => (int) $row['nombre_biens'],
        ], $results);
    }

    public function getPatrimoineParMois(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select(
                "SUBSTRING(a.dateAcquisition, 1, 7) as mois",
                'SUM(a.valeur) as total_mois'
            )
            ->where('a.isDelete = :isDelete')
            ->andWhere('a.dateAcquisition IS NOT NULL')
            ->andWhere('a.valeur IS NOT NULL')
            ->setParameter('isDelete', false)
            ->groupBy('mois')
            ->orderBy('mois', 'ASC');

        $this->applyFilters($qb, $filters);
        $results = $qb->getQuery()->getResult();

        $cumul = 0;
        $patrimoineParMois = [];

        foreach ($results as $row) {
            $cumul += (float) $row['total_mois'];
            $patrimoineParMois[] = [
                'mois' => $row['mois'],
                'valeur_patrimoine' => $cumul,
            ];
        }

        return $patrimoineParMois;
    }

    public function getMaintenanceParMois(array $filters): array
    {
        $qb = $this->getEntityManager()
            ->createQueryBuilder()
            ->select(
                "SUBSTRING(m.dateIntervention, 1, 7) as mois",
                'COUNT(DISTINCT a.id) as nombre_biens'
            )
            ->from(AssetMaintenance::class, 'm')
            ->innerJoin('m.assets', 'a')
            ->where('a.isDelete = :isDelete')
            ->andWhere('m.dateIntervention IS NOT NULL')
            ->setParameter('isDelete', false)
            ->groupBy('mois')
            ->orderBy('mois', 'ASC');

        $this->applyFiltersOnAsset($qb, $filters, 'a');
        $results = $qb->getQuery()->getResult();

        return array_map(fn($row) => [
            'mois' => $row['mois'],
            'nombre_biens' => (int) $row['nombre_biens'],
        ], $results);
    }

    public function getBiensAffectesParMois(array $filters): array
    {
        $qb = $this->getEntityManager()
            ->createQueryBuilder()
            ->select(
                "SUBSTRING(ass.dateDebut, 1, 7) as mois",
                'COUNT(DISTINCT a.id) as nombre_biens'
            )
            ->from(AssetAssignment::class, 'ass')
            ->innerJoin('ass.asset', 'a')
            ->where('a.isDelete = :isDelete')
            ->andWhere('ass.isDelete = :isDelete')
            ->andWhere('ass.dateDebut IS NOT NULL')
            ->setParameter('isDelete', false)
            ->groupBy('mois')
            ->orderBy('mois', 'ASC');

        $this->applyFiltersOnAsset($qb, $filters, 'a');
        $results = $qb->getQuery()->getResult();

        return array_map(fn($row) => [
            'mois' => $row['mois'],
            'nombre_biens' => (int) $row['nombre_biens'],
        ], $results);
    }

    // ======================================================================
    // Matériel roulant (véhicules)
    // Périmètre : biens rattachés à la catégorie "Matériel Roulant" (cf. applyVehicleScope).
    //
    // "Marque" n'est PAS implémentée : voir self::CHAMPS_EN_ATTENTE en tête de classe.
    //
    // "Ancienneté du parc" (KPI 7) suit une logique différente : en l'absence de "date de mise en
    // circulation", on calcule l'âge à partir de `dateAcquisition` (déjà en base) comme proxy.
    // À remplacer par une vraie date de mise en circulation dès qu'elle existera.
    // ======================================================================

    public function getVehiculesTotal(array $filters): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.id) as total')
            ->where('a.isDelete = :isDelete')
            ->setParameter('isDelete', false);

        $this->applyVehicleScope($qb);
        $this->applyFilters($qb, $filters);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * KPI "Répartition par état" : Neuf, Bon, Passable, En panne, Vétuste, Hors d'usage,
     * Réformé, Aucune information (véhicule sans etatBien renseigné).
     */
    public function getVehiculesRepartitionParEtat(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('e.nom as etat_nom', 'COUNT(DISTINCT a.id) as nombre')
            ->innerJoin('a.etatBiens', 'e')
            ->where('a.isDelete = :isDelete')
            ->andWhere('e.isDelete = :isDeleteE')
            ->setParameter('isDelete', false)
            ->setParameter('isDeleteE', false)
            ->groupBy('e.id, e.nom')
            ->orderBy('nombre', 'DESC');
        $this->applyVehicleScope($qb);
        $this->applyFilters($qb, $filters);
        $results = $qb->getQuery()->getResult();

        $sansEtatQb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.id) as total')
            ->leftJoin('a.etatBiens', 'e2')
            ->where('a.isDelete = :isDelete')
            ->andWhere('e2.id IS NULL')
            ->setParameter('isDelete', false);
        $this->applyVehicleScope($sansEtatQb);
        $this->applyFilters($sansEtatQb, $filters);
        $sansEtat = (int) $sansEtatQb->getQuery()->getSingleScalarResult();

        $total = $this->getVehiculesTotal($filters);
        $pct = fn(int $n) => $total > 0 ? round(($n / $total) * 100, 2) : 0.0;

        $breakdown = array_map(fn($row) => [
            'etat' => $row['etat_nom'],
            'nombre' => (int) $row['nombre'],
            'pourcentage' => $pct((int) $row['nombre']),
        ], $results);

        $breakdown[] = [
            'etat' => 'Aucune information',
            'nombre' => $sansEtat,
            'pourcentage' => $pct($sansEtat),
        ];

        return $breakdown;
    }

    /**
     * KPI "Répartition géographique" : arbre Région -> Département -> Arrondissement.
     */
    public function getVehiculesRepartitionGeographique(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select(
                'r.id as region_id',
                'r.nom as region_nom',
                'd.id as departement_id',
                'd.nom as departement_nom',
                'ar.id as arrondissement_id',
                'ar.nom as arrondissement_nom',
                'COUNT(DISTINCT a.id) as nombre_vehicules'
            )
            ->innerJoin('a.services', 's')
            ->innerJoin('s.region', 'r')
            ->leftJoin('s.departement', 'd')
            ->leftJoin('s.arrondissement', 'ar')
            ->where('a.isDelete = :isDelete')
            ->andWhere('s.is_active = :isActive')
            ->setParameter('isDelete', false)
            ->setParameter('isActive', true)
            ->groupBy('r.id, r.nom, d.id, d.nom, ar.id, ar.nom')
            ->orderBy('r.nom', 'ASC');
        $this->applyVehicleScope($qb);
        $this->applyFilters($qb, $filters);
        $results = $qb->getQuery()->getResult();

        $tree = [];
        foreach ($results as $row) {
            $regionId = (int) $row['region_id'];
            if (!isset($tree[$regionId])) {
                $tree[$regionId] = [
                    'region_id' => $regionId,
                    'region_nom' => $row['region_nom'],
                    'nombre_vehicules' => 0,
                    'departements' => [],
                ];
            }
            $tree[$regionId]['nombre_vehicules'] += (int) $row['nombre_vehicules'];

            if (null !== $row['departement_id']) {
                $depId = (int) $row['departement_id'];
                if (!isset($tree[$regionId]['departements'][$depId])) {
                    $tree[$regionId]['departements'][$depId] = [
                        'departement_id' => $depId,
                        'departement_nom' => $row['departement_nom'],
                        'nombre_vehicules' => 0,
                        'arrondissements' => [],
                    ];
                }
                $tree[$regionId]['departements'][$depId]['nombre_vehicules'] += (int) $row['nombre_vehicules'];

                if (null !== $row['arrondissement_id']) {
                    $arId = (int) $row['arrondissement_id'];
                    $tree[$regionId]['departements'][$depId]['arrondissements'][$arId] = [
                        'arrondissement_id' => $arId,
                        'arrondissement_nom' => $row['arrondissement_nom'],
                        'nombre_vehicules' => (int) $row['nombre_vehicules'],
                    ];
                }
            }
        }

        return array_values(array_map(function ($region) {
            $region['departements'] = array_values(array_map(function ($dep) {
                $dep['arrondissements'] = array_values($dep['arrondissements']);
                return $dep;
            }, $region['departements']));
            return $region;
        }, $tree));
    }

    /**
     * KPI "Véhicules à réformer" : global + par région. Même définition d'"à réformer" que
     * StatisticsRepository::getBiensMauvaisEtat (exclut explicitement "Réformé").
     */
    public function getVehiculesAReformer(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.id) as total')
            ->innerJoin('a.etatBiens', 'e')
            ->where('a.isDelete = :isDelete')
            ->andWhere('e.isDelete = :isDeleteE')
            ->setParameter('isDelete', false)
            ->setParameter('isDeleteE', false);
        $this->addAReformerCondition($qb, 'e');
        $this->applyVehicleScope($qb);
        $this->applyFilters($qb, $filters);
        $total = (int) $qb->getQuery()->getSingleScalarResult();

        $parRegionQb = $this->createQueryBuilder('a')
            ->select('r.id as region_id', 'r.nom as region_nom', 'COUNT(DISTINCT a.id) as nombre')
            ->innerJoin('a.etatBiens', 'e')
            ->innerJoin('a.services', 's')
            ->innerJoin('s.region', 'r')
            ->where('a.isDelete = :isDelete')
            ->andWhere('e.isDelete = :isDeleteE')
            ->andWhere('s.is_active = :isActive')
            ->setParameter('isDelete', false)
            ->setParameter('isDeleteE', false)
            ->setParameter('isActive', true)
            ->groupBy('r.id, r.nom')
            ->orderBy('nombre', 'DESC');
        $this->addAReformerCondition($parRegionQb, 'e');
        $this->applyVehicleScope($parRegionQb);
        $this->applyFilters($parRegionQb, $filters);
        $parRegionResults = $parRegionQb->getQuery()->getResult();

        return [
            'total' => $total,
            'par_region' => array_map(fn($row) => [
                'region_id' => (int) $row['region_id'],
                'region_nom' => $row['region_nom'],
                'nombre' => (int) $row['nombre'],
            ], $parRegionResults),
        ];
    }

    /**
     * KPI "Répartition par source de financement".
     *
     * BUG CORRIGÉ (500 remonté par le frontend) : cette méthode lisait `a.sourceFinancement`, un
     * champ qui n'existe plus sur Asset — supprimé lors d'un refactoring ultérieur (le mapping
     * payload correspondant est commenté dans AssetManagementService::applyPayload()). Le "source
     * de financement" d'un bien est désormais dérivé de son premier projet lié (cf.
     * AssetResponseBuilder, qui fait exactement ce calcul : `$asset->getProjects()->first()`),
     * pas d'un champ libre. Requêter `a.sourceFinancement` fait échouer la validation sémantique
     * DQL (`Class App\Entity\Asset has no field or association named sourceFinancement`), d'où le
     * 500 : Doctrine lève avant même d'atteindre la base.
     */
    public function getVehiculesRepartitionFinancement(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select(
                "COALESCE(p.nom, 'Non renseigné') as source",
                'COUNT(DISTINCT a.id) as nombre'
            )
            ->leftJoin('a.projects', 'p', Join::WITH, 'p.isDelete = false')
            ->where('a.isDelete = :isDelete')
            ->setParameter('isDelete', false)
            ->groupBy('source')
            ->orderBy('nombre', 'DESC');
        $this->applyVehicleScope($qb);
        $this->applyFilters($qb, $filters);
        $results = $qb->getQuery()->getResult();

        return array_map(fn($row) => [
            'source_financement' => $row['source'],
            'nombre' => (int) $row['nombre'],
        ], $results);
    }

    /**
     * KPI "Répartition par type / sous-type" (ex. Pick-up, Berline, Moto...).
     * La "marque" n'est pas incluse : aucun champ ne la stocke actuellement (cf. note d'en-tête).
     */
    public function getVehiculesRepartitionParType(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select(
                't.id as type_id',
                't.nom as type_nom',
                'st.id as sous_type_id',
                'st.nom as sous_type_nom',
                'COUNT(DISTINCT a.id) as nombre'
            )
            ->innerJoin('a.assetTypes', 't')
            ->leftJoin('a.assetSubTypes', 'st')
            ->where('a.isDelete = :isDelete')
            ->andWhere('t.isDelete = :isDeleteT')
            ->setParameter('isDelete', false)
            ->setParameter('isDeleteT', false)
            ->groupBy('t.id, t.nom, st.id, st.nom')
            ->orderBy('t.nom', 'ASC')
            ->addOrderBy('nombre', 'DESC');
        $this->applyVehicleScope($qb);
        $this->applyFilters($qb, $filters);
        $results = $qb->getQuery()->getResult();

        $grouped = [];
        foreach ($results as $row) {
            $typeId = (int) $row['type_id'];
            if (!isset($grouped[$typeId])) {
                $grouped[$typeId] = [
                    'type_id' => $typeId,
                    'type_nom' => $row['type_nom'],
                    'nombre' => 0,
                    'sous_types' => [],
                ];
            }
            $grouped[$typeId]['nombre'] += (int) $row['nombre'];
            if (null !== $row['sous_type_id']) {
                $grouped[$typeId]['sous_types'][] = [
                    'sous_type_id' => (int) $row['sous_type_id'],
                    'sous_type_nom' => $row['sous_type_nom'],
                    'nombre' => (int) $row['nombre'],
                ];
            }
        }

        return array_values($grouped);
    }

    /**
     * KPI "Ancienneté du parc" : âge moyen + répartition par tranche d'âge.
     * Calculé depuis `dateAcquisition` en l'absence d'une "date de mise en circulation" dédiée
     * (même logique de proxy que pour "marque" dans getVehiculesRepartitionParType).
     */
    public function getVehiculesAnciennete(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select(
                'SUBSTRING(a.dateAcquisition, 1, 4) as annee_acquisition',
                'COUNT(DISTINCT a.id) as nombre'
            )
            ->where('a.isDelete = :isDelete')
            ->andWhere('a.dateAcquisition IS NOT NULL')
            ->setParameter('isDelete', false)
            ->groupBy('annee_acquisition')
            ->orderBy('annee_acquisition', 'ASC');
        $this->applyVehicleScope($qb);
        $this->applyFilters($qb, $filters);
        $results = $qb->getQuery()->getResult();

        $anneeCourante = (int) (new \DateTimeImmutable())->format('Y');
        $totalAvecDate = 0;
        $sommeAges = 0;
        $tranches = [
            '0-2 ans' => 0,
            '3-5 ans' => 0,
            '6-10 ans' => 0,
            'plus de 10 ans' => 0,
        ];

        foreach ($results as $row) {
            $annee = (int) $row['annee_acquisition'];
            $nombre = (int) $row['nombre'];
            $age = max(0, $anneeCourante - $annee);

            $totalAvecDate += $nombre;
            $sommeAges += $age * $nombre;

            $tranche = match (true) {
                $age <= 2 => '0-2 ans',
                $age <= 5 => '3-5 ans',
                $age <= 10 => '6-10 ans',
                default => 'plus de 10 ans',
            };
            $tranches[$tranche] += $nombre;
        }

        return [
            'age_moyen_annees' => $totalAvecDate > 0 ? round($sommeAges / $totalAvecDate, 1) : null,
            'total_vehicules' => $this->getVehiculesTotal($filters),
            'vehicules_avec_date_connue' => $totalAvecDate,
            'repartition_par_tranche' => array_map(
                fn(string $label, int $nombre) => ['tranche' => $label, 'nombre' => $nombre],
                array_keys($tranches),
                array_values($tranches)
            ),
        ];
    }

    /**
     * KPI "Véhicules disparus" : ÉtatBien = "Disparu", global + par région.
     */
    public function getVehiculesDisparus(array $filters): array
    {
        $total = $this->getVehiculesTotal($filters);

        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.id) as total')
            ->innerJoin('a.etatBiens', 'e')
            ->where('a.isDelete = :isDelete')
            ->andWhere('e.isDelete = :isDeleteE')
            ->andWhere('LOWER(e.nom) LIKE :disparu')
            ->setParameter('isDelete', false)
            ->setParameter('isDeleteE', false)
            ->setParameter('disparu', '%disparu%');
        $this->applyVehicleScope($qb);
        $this->applyFilters($qb, $filters);
        $disparus = (int) $qb->getQuery()->getSingleScalarResult();

        $parRegionQb = $this->createQueryBuilder('a')
            ->select('r.id as region_id', 'r.nom as region_nom', 'COUNT(DISTINCT a.id) as nombre')
            ->innerJoin('a.etatBiens', 'e')
            ->innerJoin('a.services', 's')
            ->innerJoin('s.region', 'r')
            ->where('a.isDelete = :isDelete')
            ->andWhere('e.isDelete = :isDeleteE')
            ->andWhere('s.is_active = :isActive')
            ->andWhere('LOWER(e.nom) LIKE :disparu')
            ->setParameter('isDelete', false)
            ->setParameter('isDeleteE', false)
            ->setParameter('isActive', true)
            ->setParameter('disparu', '%disparu%')
            ->groupBy('r.id, r.nom')
            ->orderBy('nombre', 'DESC');
        $this->applyVehicleScope($parRegionQb);
        $this->applyFilters($parRegionQb, $filters);
        $parRegionResults = $parRegionQb->getQuery()->getResult();

        return [
            'nombre' => $disparus,
            'total_vehicules' => $total,
            'pourcentage' => $total > 0 ? round(($disparus / $total) * 100, 2) : 0.0,
            'par_region' => array_map(fn($row) => [
                'region_id' => (int) $row['region_id'],
                'region_nom' => $row['region_nom'],
                'nombre' => (int) $row['nombre'],
            ], $parRegionResults),
        ];
    }

    /**
     * KPI "Pièces manquantes" : véhicules sans pièce jointe dont le nom contient "carte grise".
     */
    public function getVehiculesCarteGriseManquante(array $filters): array
    {
        $total = $this->getVehiculesTotal($filters);

        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.id) as total')
            ->leftJoin('a.piecesJointes', 'pj', Join::WITH, 'LOWER(pj.nom) LIKE :carteGrise')
            ->where('a.isDelete = :isDelete')
            ->andWhere('pj.id IS NULL')
            ->setParameter('isDelete', false)
            ->setParameter('carteGrise', '%carte grise%');
        $this->applyVehicleScope($qb);
        $this->applyFilters($qb, $filters);
        $manquantes = (int) $qb->getQuery()->getSingleScalarResult();

        return [
            'nombre' => $manquantes,
            'total_vehicules' => $total,
            'pourcentage' => $total > 0 ? round(($manquantes / $total) * 100, 2) : 0.0,
        ];
    }

    /**
     * KPI "Biens par détenteur" : véhicules affectés à un utilisateur donné.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getVehiculesParDetenteur(int $userId, array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->innerJoin('a.assignments', 'ass')
            ->where('a.isDelete = :isDelete')
            ->andWhere('ass.isDelete = false')
            ->andWhere('ass.user = :userId')
            ->setParameter('isDelete', false)
            ->setParameter('userId', $userId)
            ->orderBy('ass.dateDebut', 'DESC');
        $this->applyVehicleScope($qb);
        $this->applyFilters($qb, $filters);

        /** @var Asset[] $assets */
        $assets = $qb->getQuery()->getResult();

        return array_map(fn(Asset $asset) => [
            'id' => $asset->getId(),
            'reference' => $asset->getReference(),
            'nom' => $asset->getNom(),
            'numeroSerie' => $asset->getNumeroSerie(),
            'statut' => $asset->getStatut(),
        ], $assets);
    }

    /**
     * KPI "Valeur totale du parc automobile".
     */
    public function getVehiculesValeurTotale(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select(
                'COUNT(a.id) as total_vehicules',
                'COUNT(a.valeur) as vehicules_avec_valeur',
                'COALESCE(SUM(a.valeur), 0) as valeur_totale'
            )
            ->where('a.isDelete = :isDelete')
            ->setParameter('isDelete', false);
        $this->applyVehicleScope($qb);
        $this->applyFilters($qb, $filters);
        $result = $qb->getQuery()->getSingleResult();

        return [
            'valeur_totale' => (float) $result['valeur_totale'],
            'total_vehicules' => (int) $result['total_vehicules'],
            'vehicules_avec_valeur_renseignee' => (int) $result['vehicules_avec_valeur'],
        ];
    }

    /**
     * KPI "Croisement état x département".
     */
    public function getVehiculesCroisementEtatDepartement(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select(
                'd.id as departement_id',
                'd.nom as departement_nom',
                'e.id as etat_id',
                'e.nom as etat_nom',
                'COUNT(DISTINCT a.id) as nombre'
            )
            ->innerJoin('a.etatBiens', 'e')
            ->innerJoin('a.services', 's')
            ->innerJoin('s.departement', 'd')
            ->where('a.isDelete = :isDelete')
            ->andWhere('e.isDelete = :isDeleteE')
            ->andWhere('s.is_active = :isActive')
            ->setParameter('isDelete', false)
            ->setParameter('isDeleteE', false)
            ->setParameter('isActive', true)
            ->groupBy('d.id, d.nom, e.id, e.nom')
            ->orderBy('d.nom', 'ASC');
        $this->applyVehicleScope($qb);
        $this->applyFilters($qb, $filters);
        $results = $qb->getQuery()->getResult();

        $grouped = [];
        foreach ($results as $row) {
            $depId = (int) $row['departement_id'];
            if (!isset($grouped[$depId])) {
                $grouped[$depId] = [
                    'departement_id' => $depId,
                    'departement_nom' => $row['departement_nom'],
                    'etats' => [],
                ];
            }
            $grouped[$depId]['etats'][] = [
                'etat' => $row['etat_nom'],
                'nombre' => (int) $row['nombre'],
            ];
        }

        return array_values($grouped);
    }

    /**
     * KPI "Classement des régions" (parc automobile uniquement).
     */
    public function getVehiculesClassementRegions(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('r.id as region_id', 'r.nom as region_nom', 'COUNT(DISTINCT a.id) as nombre')
            ->innerJoin('a.services', 's')
            ->innerJoin('s.region', 'r')
            ->where('a.isDelete = :isDelete')
            ->andWhere('s.is_active = :isActive')
            ->setParameter('isDelete', false)
            ->setParameter('isActive', true)
            ->groupBy('r.id, r.nom')
            ->orderBy('nombre', 'DESC');
        $this->applyVehicleScope($qb);
        $this->applyFilters($qb, $filters);
        $results = $qb->getQuery()->getResult();

        return array_map(fn($row) => [
            'region_id' => (int) $row['region_id'],
            'region_nom' => $row['region_nom'],
            'nombre_vehicules' => (int) $row['nombre'],
        ], $results);
    }

    /**
     * Restreint une requête aux biens de la catégorie "Matériel Roulant" (véhicules).
     * Résolution par mot-clé (comme les états), pas par ID, pour ne pas dépendre d'un ID fixe.
     */
    private function applyVehicleScope(QueryBuilder $qb, string $alias = 'a'): void
    {
        $qb->innerJoin("{$alias}.categories", 'catv')
            ->andWhere('(LOWER(catv.nom) LIKE :vehiculeCategorie1 OR LOWER(catv.nom) LIKE :vehiculeCategorie2 OR catv.id = :vehiculeCatId)')
            ->andWhere('catv.isDelete = :catvDelete')
            ->setParameter('vehiculeCategorie1', '%matériel roulant%')
            ->setParameter('vehiculeCategorie2', '%materiel roulant%')
            ->setParameter('vehiculeCatId', 28)
            ->setParameter('catvDelete', false);
    }

    /**
     * Ajoute la condition "état = à réformer" (et exclut explicitement "réformé") sur l'alias d'état donné.
     */
    private function addAReformerCondition(QueryBuilder $qb, string $etatAlias): void
    {
        $qb->andWhere(
            "LOWER({$etatAlias}.nom) LIKE :aReformer1 OR " .
            "LOWER({$etatAlias}.nom) LIKE :aReformer2 OR " .
            "LOWER({$etatAlias}.nom) LIKE :aReformer3"
        )
            ->andWhere("LOWER({$etatAlias}.nom) NOT LIKE :excludeReformeAr")
            ->setParameter('aReformer1', '%à réformer%')
            ->setParameter('aReformer2', '%a réformer%')
            ->setParameter('aReformer3', '%a reformer%')
            ->setParameter('excludeReformeAr', '%réformé%');
    }

    // ======================================================================
    // Terrains
    // Périmètre : biens de catégorie "Terrains" (ID 29 ou nom %terrain%)
    //
    // "Superficie" (KPI 3) et "Loyer mensuel" (KPI 10) ne sont PAS implémentées : voir
    // self::CHAMPS_EN_ATTENTE en tête de classe.
    // "Bâti/non bâti" (5), "Occupation" (7), "Litige" (8) et "Loué" (10, nombre de terrains) sont
    // supposés portés par des ÉtatBien dédiés (même mécanisme que "Disparu" pour les véhicules)
    // — à confirmer contre les données réelles.
    // "Sécurisation" (6) s'appuie sur le module dédié Security/AssetSecurity/SecurityMode.
    // ======================================================================

    public function getTerrainsTotal(array $filters): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.id) as total')
            ->where('a.isDelete = :isDelete')
            ->setParameter('isDelete', false);
        $this->applyLandScope($qb);
        $this->applyFilters($qb, $filters);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function applyLandScope(QueryBuilder $qb, string $alias = 'a'): void
    {
        $qb->innerJoin("{$alias}.categories", 'catl')
            ->andWhere('(LOWER(catl.nom) LIKE :terrainType OR catl.id = :terrainCatId)')
            ->andWhere('catl.isDelete = :catlDelete')
            ->setParameter('terrainType', '%terrain%')
            ->setParameter('terrainCatId', 29)
            ->setParameter('catlDelete', false);
    }

    /**
     * KPI "Répartition géographique" : arbre Région -> Département -> Arrondissement.
     */
    public function getTerrainsRepartitionGeographique(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select(
                'r.id as region_id',
                'r.nom as region_nom',
                'd.id as departement_id',
                'd.nom as departement_nom',
                'ar.id as arrondissement_id',
                'ar.nom as arrondissement_nom',
                'COUNT(DISTINCT a.id) as nombre_terrains'
            )
            ->innerJoin('a.services', 's')
            ->innerJoin('s.region', 'r')
            ->leftJoin('s.departement', 'd')
            ->leftJoin('s.arrondissement', 'ar')
            ->where('a.isDelete = :isDelete')
            ->andWhere('s.is_active = :isActive')
            ->setParameter('isDelete', false)
            ->setParameter('isActive', true)
            ->groupBy('r.id, r.nom, d.id, d.nom, ar.id, ar.nom')
            ->orderBy('r.nom', 'ASC');
        $this->applyLandScope($qb);
        $this->applyFilters($qb, $filters);
        $results = $qb->getQuery()->getResult();

        $tree = [];
        foreach ($results as $row) {
            $regionId = (int) $row['region_id'];
            if (!isset($tree[$regionId])) {
                $tree[$regionId] = [
                    'region_id' => $regionId,
                    'region_nom' => $row['region_nom'],
                    'nombre_terrains' => 0,
                    'departements' => [],
                ];
            }
            $tree[$regionId]['nombre_terrains'] += (int) $row['nombre_terrains'];

            if (null !== $row['departement_id']) {
                $depId = (int) $row['departement_id'];
                if (!isset($tree[$regionId]['departements'][$depId])) {
                    $tree[$regionId]['departements'][$depId] = [
                        'departement_id' => $depId,
                        'departement_nom' => $row['departement_nom'],
                        'nombre_terrains' => 0,
                        'arrondissements' => [],
                    ];
                }
                $tree[$regionId]['departements'][$depId]['nombre_terrains'] += (int) $row['nombre_terrains'];

                if (null !== $row['arrondissement_id']) {
                    $arId = (int) $row['arrondissement_id'];
                    $tree[$regionId]['departements'][$depId]['arrondissements'][$arId] = [
                        'arrondissement_id' => $arId,
                        'arrondissement_nom' => $row['arrondissement_nom'],
                        'nombre_terrains' => (int) $row['nombre_terrains'],
                    ];
                }
            }
        }

        return array_values(array_map(function ($region) {
            $region['departements'] = array_values(array_map(function ($dep) {
                $dep['arrondissements'] = array_values($dep['arrondissements']);
                return $dep;
            }, $region['departements']));
            return $region;
        }, $tree));
    }

    /**
     * KPI "Valeur totale déclarée" : somme des valeurs connues + nombre de terrains sans valeur.
     */
    public function getTerrainsValeurTotale(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select(
                'COUNT(a.id) as total_terrains',
                'COUNT(a.valeur) as terrains_avec_valeur',
                'COALESCE(SUM(a.valeur), 0) as valeur_totale'
            )
            ->where('a.isDelete = :isDelete')
            ->setParameter('isDelete', false);
        $this->applyLandScope($qb);
        $this->applyFilters($qb, $filters);
        $result = $qb->getQuery()->getSingleResult();

        $total = (int) $result['total_terrains'];
        $avecValeur = (int) $result['terrains_avec_valeur'];

        return [
            'valeur_totale' => (float) $result['valeur_totale'],
            'total_terrains' => $total,
            'terrains_avec_valeur_renseignee' => $avecValeur,
            'terrains_sans_valeur' => $total - $avecValeur,
        ];
    }

    /**
     * KPI "Bâti / non bâti" : nombre + pourcentage, plus "Aucune information".
     */
    public function getTerrainsRepartitionBati(array $filters): array
    {
        $total = $this->getTerrainsTotal($filters);
        $nonBati = $this->countTerrainsMatchingInput($filters, '%bâti%', ['%non%', 'false', '0', '%non bât%', '%non-bât%']);
        // Bâti peut être stocké comme 'OUI', 'True', ou valeur contenant 'bâti' mais qui ne matche pas 'non bâti'
        $totalBatiOccurrences = $this->countTerrainsMatchingInput($filters, '%bâti%', ['%oui%', 'true', '1', '%bâti%']);
        
        $bati = max(0, $totalBatiOccurrences - $nonBati);
        $aucuneInfo = max(0, $total - $bati - $nonBati);
        $pct = fn(int $n) => $total > 0 ? round(($n / $total) * 100, 2) : 0.0;

        return [
            ['statut' => 'Bâti', 'nombre' => $bati, 'pourcentage' => $pct($bati)],
            ['statut' => 'Non bâti', 'nombre' => $nonBati, 'pourcentage' => $pct($nonBati)],
            ['statut' => 'Aucune information', 'nombre' => $aucuneInfo, 'pourcentage' => $pct($aucuneInfo)],
        ];
    }

    /**
     * KPI "Sécurisation" : juridique seulement / physique seulement / les deux / aucun.
     */
    public function getTerrainsSecurisation(array $filters): array
    {
        $total = $this->getTerrainsTotal($filters);

        $juridiqueIds = array_flip($this->getTerrainIdsBySecurityModeKeyword($filters, 'juridique'));
        $physiqueIds = array_flip($this->getTerrainIdsBySecurityModeKeyword($filters, 'physique'));

        $lesDeux = count(array_intersect_key($juridiqueIds, $physiqueIds));
        $juridiqueSeul = count($juridiqueIds) - $lesDeux;
        $physiqueSeul = count($physiqueIds) - $lesDeux;
        $auMoinsUn = count($juridiqueIds) + count($physiqueIds) - $lesDeux;
        $aucun = max(0, $total - $auMoinsUn);

        return [
            'juridique_seulement' => $juridiqueSeul,
            'physique_seulement' => $physiqueSeul,
            'les_deux' => $lesDeux,
            'aucun' => $aucun,
            'total_terrains' => $total,
        ];
    }

    /**
     * @return int[] IDs des terrains dont au moins une sécurisation a un SecurityMode
     *               dont le nom contient le mot-clé donné.
     */
    /**
     * BUG CORRIGÉ (500 remonté par le frontend, via /dashboard) : `Security::securityMode` n'est
     * plus une relation ManyToOne vers l'entité `SecurityMode` (cf. commentaire "✅ Plus de
     * relation ManyToOne vers SecurityMode" dans Security.php) mais une simple colonne texte.
     * Le JOIN vers `sec.securityMode` faisait échouer la validation sémantique DQL
     * (`Class App\Entity\Security has no association named securityMode`).
     */
    private function getTerrainIdsBySecurityModeKeyword(array $filters, string $keyword): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('DISTINCT a.id as id')
            ->innerJoin('a.assetSecurities', 'asec')
            ->innerJoin('asec.security', 'sec')
            ->where('a.isDelete = :isDelete')
            ->andWhere('asec.isDelete = :isDeleteAsec')
            ->andWhere('sec.isDelete = :isDeleteSec')
            ->andWhere('LOWER(sec.securityMode) LIKE :kw')
            ->setParameter('isDelete', false)
            ->setParameter('isDeleteAsec', false)
            ->setParameter('isDeleteSec', false)
            ->setParameter('kw', '%' . $keyword . '%');
        $this->applyLandScope($qb);
        $this->applyFilters($qb, $filters);

        return array_map(fn($row) => (int) $row['id'], $qb->getQuery()->getResult());
    }

    /**
     * KPI "Occupation" : régulière / irrégulière / aucune information.
     */
    public function getTerrainsRepartitionOccupation(array $filters): array
    {
        $total = $this->getTerrainsTotal($filters);
        $irreg = $this->countTerrainsMatchingInput($filters, '%occupation%', ['%irrégulière%', '%irreguliere%']);
        $reg = $this->countTerrainsMatchingInput($filters, '%occupation%', ['%régulière%', '%reguliere%']);
        $aucuneInfo = max(0, $total - $reg - $irreg);
        $pct = fn(int $n) => $total > 0 ? round(($n / $total) * 100, 2) : 0.0;

        return [
            ['occupation' => 'Régulière', 'nombre' => $reg, 'pourcentage' => $pct($reg)],
            ['occupation' => 'Irrégulière', 'nombre' => $irreg, 'pourcentage' => $pct($irreg)],
            ['occupation' => 'Aucune information', 'nombre' => $aucuneInfo, 'pourcentage' => $pct($aucuneInfo)],
        ];
    }

    /**
     * KPI "Terrains en litige" : nombre + liste.
     */
    /**
     * BUG CORRIGÉ (500 remonté par le frontend, via /dashboard) : cette méthode chargeait
     * l'entité Asset complète (`SELECT a`), ce qui force Doctrine à sélectionner TOUTES les
     * colonnes mappées — dont `active_depreciation`, qui existe dans Asset::$activeDepreciation
     * mais pas dans la table `asset` réelle (schéma désynchronisé du mapping, à corriger côté
     * infra avec `doctrine:schema:update` — indépendant de ce module). On ne sélectionne
     * désormais que les 3 champs scalaires réellement utilisés, ce qui contourne le problème et
     * réduit la charge de la requête.
     */
    public function getTerrainsEnLitige(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('DISTINCT a.id as id', 'a.reference as reference', 'a.nom as nom')
            ->innerJoin('a.inputs', 'inp')
            ->innerJoin('inp.champ', 'ch')
            ->where('a.isDelete = :isDelete')
            ->andWhere('inp.isDelete = false')
            ->andWhere('ch.isDelete = false')
            ->andWhere('LOWER(ch.nom) LIKE :litige')
            ->andWhere('(LOWER(inp.valeur) LIKE :val0 OR LOWER(inp.valeur) LIKE :val1 OR LOWER(inp.valeur) LIKE :val2)')
            ->setParameter('isDelete', false)
            ->setParameter('litige', '%litige%')
            ->setParameter('val0', '%oui%')
            ->setParameter('val1', '%true%')
            ->setParameter('val2', '1');
        $this->applyLandScope($qb);
        $this->applyFilters($qb, $filters);

        $terrains = $qb->getQuery()->getResult();

        return [
            'nombre' => count($terrains),
            'terrains' => array_map(fn(array $row) => [
                'id' => (int) $row['id'],
                'reference' => $row['reference'],
                'nom' => $row['nom'],
            ], $terrains),
        ];
    }

    /**
     * KPI "Titre foncier disponible" : terrains sans pièce jointe dont le nom contient "titre foncier".
     */
    public function getTerrainsTitreFoncierManquant(array $filters): array
    {
        $total = $this->getTerrainsTotal($filters);

        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.id) as total')
            ->leftJoin('a.piecesJointes', 'pj', Join::WITH, 'LOWER(pj.nom) LIKE :titreFoncier')
            ->where('a.isDelete = :isDelete')
            ->andWhere('pj.id IS NULL')
            ->setParameter('isDelete', false)
            ->setParameter('titreFoncier', '%titre foncier%');
        $this->applyLandScope($qb);
        $this->applyFilters($qb, $filters);
        $manquant = (int) $qb->getQuery()->getSingleScalarResult();

        return [
            'nombre_sans_titre' => $manquant,
            'nombre_avec_titre' => $total - $manquant,
            'total_terrains' => $total,
            'pourcentage_sans_titre' => $total > 0 ? round(($manquant / $total) * 100, 2) : 0.0,
        ];
    }

    /**
     * KPI "Terrains loués" : nombre de terrains à l'état "Loué" uniquement. Le loyer mensuel
     * total n'est pas exposé — aucun champ ne le stocke actuellement (cf. note d'en-tête, même
     * traitement que "Marque" pour les véhicules).
     */
    public function getTerrainsLoues(array $filters): array
    {
        $nombre = $this->countTerrainsMatchingEtat($filters, ['%loué%', '%louée%']);

        return [
            'nombre' => $nombre,
        ];
    }

    /**
     * KPI "Acquisitions par année".
     */
    public function getTerrainsAcquisitionsParAnnee(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select(
                'SUBSTRING(a.dateAcquisition, 1, 4) as annee',
                'COUNT(DISTINCT a.id) as nombre'
            )
            ->where('a.isDelete = :isDelete')
            ->andWhere('a.dateAcquisition IS NOT NULL')
            ->setParameter('isDelete', false)
            ->groupBy('annee')
            ->orderBy('annee', 'ASC');
        $this->applyLandScope($qb);
        $this->applyFilters($qb, $filters);
        $results = $qb->getQuery()->getResult();

        return array_map(fn($row) => [
            'annee' => $row['annee'],
            'nombre' => (int) $row['nombre'],
        ], $results);
    }

    /**
     * KPI "Croisement Bâti/Non bâti x Département".
     */
    public function getTerrainsCroisementBatiDepartement(array $filters): array
    {
        $total = $this->terrainsGroupByDepartement($filters);
        $nonBatiPatterns = ['%non bâti%', '%non-bâti%'];
        $bati = $this->terrainsGroupByDepartement($filters, ['%bâti%'], $nonBatiPatterns);
        $nonBati = $this->terrainsGroupByDepartement($filters, $nonBatiPatterns);

        $result = [];
        foreach ($total as $deptId => $info) {
            $b = $bati[$deptId]['nombre'] ?? 0;
            $nb = $nonBati[$deptId]['nombre'] ?? 0;
            $result[] = [
                'departement_id' => $deptId,
                'departement_nom' => $info['nom'],
                'bati' => $b,
                'non_bati' => $nb,
                'aucune_information' => max(0, $info['nombre'] - $b - $nb),
            ];
        }

        usort($result, fn($a, $b) => strcmp((string) $a['departement_nom'], (string) $b['departement_nom']));

        return $result;
    }

    /**
     * KPI "Croisement Occupation x Département".
     */
    public function getTerrainsCroisementOccupationDepartement(array $filters): array
    {
        $total = $this->terrainsGroupByDepartement($filters);
        $irregPatterns = ['%irrégulière%', '%irreguliere%'];
        $reg = $this->terrainsGroupByDepartement($filters, ['%régulière%', '%reguliere%'], $irregPatterns);
        $irreg = $this->terrainsGroupByDepartement($filters, $irregPatterns);

        $result = [];
        foreach ($total as $deptId => $info) {
            $r = $reg[$deptId]['nombre'] ?? 0;
            $ir = $irreg[$deptId]['nombre'] ?? 0;
            $result[] = [
                'departement_id' => $deptId,
                'departement_nom' => $info['nom'],
                'reguliere' => $r,
                'irreguliere' => $ir,
                'aucune_information' => max(0, $info['nombre'] - $r - $ir),
            ];
        }

        usort($result, fn($a, $b) => strcmp((string) $a['departement_nom'], (string) $b['departement_nom']));

        return $result;
    }

    /**
     * KPI "Classement des régions" (par nombre — le classement par superficie n'est pas
     * disponible : aucun champ de superficie n'existe actuellement, cf. note d'en-tête).
     */
    public function getTerrainsClassementRegions(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('r.id as region_id', 'r.nom as region_nom', 'COUNT(DISTINCT a.id) as nombre')
            ->innerJoin('a.services', 's')
            ->innerJoin('s.region', 'r')
            ->where('a.isDelete = :isDelete')
            ->andWhere('s.is_active = :isActive')
            ->setParameter('isDelete', false)
            ->setParameter('isActive', true)
            ->groupBy('r.id, r.nom')
            ->orderBy('nombre', 'DESC');
        $this->applyLandScope($qb);
        $this->applyFilters($qb, $filters);
        $results = $qb->getQuery()->getResult();

        return array_map(fn($row) => [
            'region_id' => (int) $row['region_id'],
            'region_nom' => $row['region_nom'],
            'nombre_terrains' => (int) $row['nombre'],
        ], $results);
    }



    /**
     * Compte les terrains ayant au moins un ÉtatBien correspondant à l'un des motifs `$includePatterns`,
     * et n'en ayant aucun correspondant à `$excludePatterns` (le cas échéant).
     *
     * @param string[] $includePatterns
     * @param string[]|null $excludePatterns
     */
    private function countTerrainsMatchingEtat(array $filters, array $includePatterns, ?array $excludePatterns = null): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.id) as total')
            ->innerJoin('a.etatBiens', 'e')
            ->where('a.isDelete = :isDelete')
            ->andWhere('e.isDelete = :isDeleteE')
            ->setParameter('isDelete', false)
            ->setParameter('isDeleteE', false);

        $orX = [];
        foreach ($includePatterns as $i => $pattern) {
            $orX[] = "LOWER(e.nom) LIKE :inc{$i}";
            $qb->setParameter("inc{$i}", $pattern);
        }
        $qb->andWhere(implode(' OR ', $orX));

        if (null !== $excludePatterns) {
            foreach ($excludePatterns as $i => $pattern) {
                $qb->andWhere("LOWER(e.nom) NOT LIKE :exc{$i}")->setParameter("exc{$i}", $pattern);
            }
        }

        $this->applyLandScope($qb);
        $this->applyFilters($qb, $filters);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Compte les terrains ayant au moins un Input correspondant à l'un des motifs `$valeurPatterns`
     * pour un Champ dont le nom correspond à `$champPattern`.
     */
    private function countTerrainsMatchingInput(array $filters, string $champPattern, array $valeurPatterns): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.id) as total')
            ->innerJoin('a.inputs', 'inp')
            ->innerJoin('inp.champ', 'ch')
            ->where('a.isDelete = :isDelete')
            ->andWhere('inp.isDelete = false')
            ->andWhere('ch.isDelete = false')
            ->setParameter('isDelete', false);

        $qb->andWhere('LOWER(ch.nom) LIKE :champName')
           ->setParameter('champName', $champPattern);

        $orX = [];
        foreach ($valeurPatterns as $i => $pattern) {
            $orX[] = "LOWER(inp.valeur) LIKE :valSearch{$i}";
            $qb->setParameter("valSearch{$i}", $pattern);
        }
        $qb->andWhere(implode(' OR ', $orX));

        $this->applyLandScope($qb);
        $this->applyFilters($qb, $filters);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Regroupe les terrains par département, avec un filtre optionnel sur les mots-clés d'état.
     *
     * @param string[]|null $includePatterns
     * @param string[]|null $excludePatterns
     * @return array<int, array{nom: string, nombre: int}> indexé par departement_id
     */
    private function terrainsGroupByDepartement(array $filters, ?array $includePatterns = null, ?array $excludePatterns = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('d.id as departement_id', 'd.nom as departement_nom', 'COUNT(DISTINCT a.id) as nombre')
            ->innerJoin('a.services', 's')
            ->innerJoin('s.departement', 'd')
            ->where('a.isDelete = :isDelete')
            ->andWhere('s.is_active = :isActive')
            ->setParameter('isDelete', false)
            ->setParameter('isActive', true)
            ->groupBy('d.id, d.nom');

        if (null !== $includePatterns) {
            $qb->innerJoin('a.etatBiens', 'e')
                ->andWhere('e.isDelete = false');

            $orX = [];
            foreach ($includePatterns as $i => $pattern) {
                $orX[] = "LOWER(e.nom) LIKE :inc{$i}";
                $qb->setParameter("inc{$i}", $pattern);
            }
            $qb->andWhere(implode(' OR ', $orX));

            if (null !== $excludePatterns) {
                foreach ($excludePatterns as $i => $pattern) {
                    $qb->andWhere("LOWER(e.nom) NOT LIKE :exc{$i}")->setParameter("exc{$i}", $pattern);
                }
            }
        }

        $this->applyLandScope($qb);
        $this->applyFilters($qb, $filters);
        $results = $qb->getQuery()->getResult();

        $out = [];
        foreach ($results as $row) {
            $out[(int) $row['departement_id']] = [
                'nom' => $row['departement_nom'],
                'nombre' => (int) $row['nombre'],
            ];
        }

        return $out;
    }

    // ======================================================================
    // Bâtiments
    // Périmètre : biens dont un AssetType a pour nom "%bâtiment%" (cf. applyBuildingScope).
    //
    // "Nombre de pièces" (KPI 5) et "Loyer mensuel" (KPI 9) ne sont PAS implémentés : voir
    // self::CHAMPS_EN_ATTENTE en tête de classe.
    //
    // "Bâtiments à réfectionner" (4), "Occupation" (6), "Litige" (7) et "Loué" (9, nombre) sont
    // supposés portés par des ÉtatBien dédiés (même mécanisme que "Disparu"/"À réformer" pour
    // les autres modules) — à confirmer contre les données réelles.
    //
    // "Année de construction" (11a) est calculée depuis `dateAcquisition` comme proxy (même
    // logique que l'ancienneté du parc automobile) : à remplacer par une vraie date de
    // construction dès qu'elle existera. "Année de dernière réfection" (11b) est déduite de la
    // dernière intervention AssetMaintenance dont le motif contient "réfection" — à confirmer,
    // car ce n'est pas un champ dédié mais une lecture du motif libre de maintenance.
    // ======================================================================

    public function getBatimentsTotal(array $filters): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.id) as total')
            ->where('a.isDelete = :isDelete')
            ->setParameter('isDelete', false);
        $this->applyBuildingScope($qb);
        $this->applyFilters($qb, $filters);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * KPI "Répartition géographique" : arbre Région -> Département -> Arrondissement.
     */
    public function getBatimentsRepartitionGeographique(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select(
                'r.id as region_id',
                'r.nom as region_nom',
                'd.id as departement_id',
                'd.nom as departement_nom',
                'ar.id as arrondissement_id',
                'ar.nom as arrondissement_nom',
                'COUNT(DISTINCT a.id) as nombre_batiments'
            )
            ->innerJoin('a.services', 's')
            ->innerJoin('s.region', 'r')
            ->leftJoin('s.departement', 'd')
            ->leftJoin('s.arrondissement', 'ar')
            ->where('a.isDelete = :isDelete')
            ->andWhere('s.is_active = :isActive')
            ->setParameter('isDelete', false)
            ->setParameter('isActive', true)
            ->groupBy('r.id, r.nom, d.id, d.nom, ar.id, ar.nom')
            ->orderBy('r.nom', 'ASC');
        $this->applyBuildingScope($qb);
        $this->applyFilters($qb, $filters);
        $results = $qb->getQuery()->getResult();

        $tree = [];
        foreach ($results as $row) {
            $regionId = (int) $row['region_id'];
            if (!isset($tree[$regionId])) {
                $tree[$regionId] = [
                    'region_id' => $regionId,
                    'region_nom' => $row['region_nom'],
                    'nombre_batiments' => 0,
                    'departements' => [],
                ];
            }
            $tree[$regionId]['nombre_batiments'] += (int) $row['nombre_batiments'];

            if (null !== $row['departement_id']) {
                $depId = (int) $row['departement_id'];
                if (!isset($tree[$regionId]['departements'][$depId])) {
                    $tree[$regionId]['departements'][$depId] = [
                        'departement_id' => $depId,
                        'departement_nom' => $row['departement_nom'],
                        'nombre_batiments' => 0,
                        'arrondissements' => [],
                    ];
                }
                $tree[$regionId]['departements'][$depId]['nombre_batiments'] += (int) $row['nombre_batiments'];

                if (null !== $row['arrondissement_id']) {
                    $arId = (int) $row['arrondissement_id'];
                    $tree[$regionId]['departements'][$depId]['arrondissements'][$arId] = [
                        'arrondissement_id' => $arId,
                        'arrondissement_nom' => $row['arrondissement_nom'],
                        'nombre_batiments' => (int) $row['nombre_batiments'],
                    ];
                }
            }
        }

        return array_values(array_map(function ($region) {
            $region['departements'] = array_values(array_map(function ($dep) {
                $dep['arrondissements'] = array_values($dep['arrondissements']);
                return $dep;
            }, $region['departements']));
            return $region;
        }, $tree));
    }

    /**
     * KPI "Répartition par état" : Neuf, Bon, Passable, Vétuste, À réfectionner, Hors d'usage,
     * Travaux inachevés, Aucune information (bâtiment sans etatBien renseigné).
     */
    public function getBatimentsRepartitionParEtat(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('e.nom as etat_nom', 'COUNT(DISTINCT a.id) as nombre')
            ->innerJoin('a.etatBiens', 'e')
            ->where('a.isDelete = :isDelete')
            ->andWhere('e.isDelete = :isDeleteE')
            ->setParameter('isDelete', false)
            ->setParameter('isDeleteE', false)
            ->groupBy('e.id, e.nom')
            ->orderBy('nombre', 'DESC');
        $this->applyBuildingScope($qb);
        $this->applyFilters($qb, $filters);
        $results = $qb->getQuery()->getResult();

        $sansEtatQb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.id) as total')
            ->leftJoin('a.etatBiens', 'e2')
            ->where('a.isDelete = :isDelete')
            ->andWhere('e2.id IS NULL')
            ->setParameter('isDelete', false);
        $this->applyBuildingScope($sansEtatQb);
        $this->applyFilters($sansEtatQb, $filters);
        $sansEtat = (int) $sansEtatQb->getQuery()->getSingleScalarResult();

        $total = $this->getBatimentsTotal($filters);
        $pct = fn(int $n) => $total > 0 ? round(($n / $total) * 100, 2) : 0.0;

        $breakdown = array_map(fn($row) => [
            'etat' => $row['etat_nom'],
            'nombre' => (int) $row['nombre'],
            'pourcentage' => $pct((int) $row['nombre']),
        ], $results);

        $breakdown[] = [
            'etat' => 'Aucune information',
            'nombre' => $sansEtat,
            'pourcentage' => $pct($sansEtat),
        ];

        return $breakdown;
    }

    /**
     * KPI "Bâtiments à réfectionner" : global + par région.
     */
    public function getBatimentsARefectionner(array $filters): array
    {
        $total = $this->countBatimentsMatchingEtat($filters, ['%à réformer%', '%a réformer%', '%a reformer%'], ['%réformé%']);

        $parRegionQb = $this->createQueryBuilder('a')
            ->select('r.id as region_id', 'r.nom as region_nom', 'COUNT(DISTINCT a.id) as nombre')
            ->innerJoin('a.etatBiens', 'e')
            ->innerJoin('a.services', 's')
            ->innerJoin('s.region', 'r')
            ->where('a.isDelete = :isDelete')
            ->andWhere('e.isDelete = :isDeleteE')
            ->andWhere('s.is_active = :isActive')
            ->setParameter('isDelete', false)
            ->setParameter('isDeleteE', false)
            ->setParameter('isActive', true)
            ->groupBy('r.id, r.nom')
            ->orderBy('nombre', 'DESC');
        $this->addAReformerCondition($parRegionQb, 'e');
        $this->applyBuildingScope($parRegionQb);
        $this->applyFilters($parRegionQb, $filters);
        $parRegionResults = $parRegionQb->getQuery()->getResult();

        return [
            'total' => $total,
            'par_region' => array_map(fn($row) => [
                'region_id' => (int) $row['region_id'],
                'region_nom' => $row['region_nom'],
                'nombre' => (int) $row['nombre'],
            ], $parRegionResults),
        ];
    }

    /**
     * KPI "Occupation" : régulière / irrégulière / cohabitation / inoccupé / aucune information.
     */
    public function getBatimentsRepartitionOccupation(array $filters): array
    {
        $total = $this->getBatimentsTotal($filters);
        $irregPatterns = ['%irrégulière%', '%irreguliere%'];
        $irreg = $this->countBatimentsMatchingEtat($filters, $irregPatterns);
        $reg = $this->countBatimentsMatchingEtat($filters, ['%régulière%', '%reguliere%'], $irregPatterns);
        $cohabitation = $this->countBatimentsMatchingEtat($filters, ['%cohabitation%']);
        $inoccupe = $this->countBatimentsMatchingEtat($filters, ['%inoccup%']);
        $aucuneInfo = max(0, $total - $reg - $irreg - $cohabitation - $inoccupe);
        $pct = fn(int $n) => $total > 0 ? round(($n / $total) * 100, 2) : 0.0;

        return [
            ['occupation' => 'Régulière', 'nombre' => $reg, 'pourcentage' => $pct($reg)],
            ['occupation' => 'Irrégulière', 'nombre' => $irreg, 'pourcentage' => $pct($irreg)],
            ['occupation' => 'Cohabitation', 'nombre' => $cohabitation, 'pourcentage' => $pct($cohabitation)],
            ['occupation' => 'Inoccupé', 'nombre' => $inoccupe, 'pourcentage' => $pct($inoccupe)],
            ['occupation' => 'Aucune information', 'nombre' => $aucuneInfo, 'pourcentage' => $pct($aucuneInfo)],
        ];
    }

    /**
     * KPI "Bâtiments en litige" : nombre + liste.
     */
    /**
     * BUG CORRIGÉ (500 remonté par le frontend, via /dashboard) : voir le commentaire équivalent
     * sur getTerrainsEnLitige() — `active_depreciation` (Asset::$activeDepreciation) n'existe pas
     * dans la table `asset` réelle ; on sélectionne donc uniquement les champs scalaires utilisés
     * au lieu de charger l'entité complète.
     */
    public function getBatimentsEnLitige(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('a.id as id', 'a.reference as reference', 'a.nom as nom')
            ->innerJoin('a.etatBiens', 'e')
            ->where('a.isDelete = :isDelete')
            ->andWhere('e.isDelete = :isDeleteE')
            ->andWhere('LOWER(e.nom) LIKE :litige')
            ->setParameter('isDelete', false)
            ->setParameter('isDeleteE', false)
            ->setParameter('litige', '%litige%');
        $this->applyBuildingScope($qb);
        $this->applyFilters($qb, $filters);

        $batiments = $qb->getQuery()->getResult();

        return [
            'nombre' => count($batiments),
            'batiments' => array_map(fn(array $row) => [
                'id' => (int) $row['id'],
                'reference' => $row['reference'],
                'nom' => $row['nom'],
            ], $batiments),
        ];
    }

    /**
     * KPI "Titre foncier disponible" : bâtiments sans pièce jointe dont le nom contient "titre foncier".
     */
    public function getBatimentsTitreFoncierManquant(array $filters): array
    {
        $total = $this->getBatimentsTotal($filters);

        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.id) as total')
            ->leftJoin('a.piecesJointes', 'pj', Join::WITH, 'LOWER(pj.nom) LIKE :titreFoncier')
            ->where('a.isDelete = :isDelete')
            ->andWhere('pj.id IS NULL')
            ->setParameter('isDelete', false)
            ->setParameter('titreFoncier', '%titre foncier%');
        $this->applyBuildingScope($qb);
        $this->applyFilters($qb, $filters);
        $manquant = (int) $qb->getQuery()->getSingleScalarResult();

        return [
            'nombre_sans_titre' => $manquant,
            'nombre_avec_titre' => $total - $manquant,
            'total_batiments' => $total,
            'pourcentage_sans_titre' => $total > 0 ? round(($manquant / $total) * 100, 2) : 0.0,
        ];
    }

    /**
     * KPI "Bâtiments loués" : nombre de bâtiments à l'état "Loué" uniquement. Le loyer mensuel
     * total n'est pas exposé — aucun champ ne le stocke actuellement (cf. note d'en-tête, même
     * traitement que "Marque" / "Superficie").
     */
    public function getBatimentsLoues(array $filters): array
    {
        $nombre = $this->countBatimentsMatchingEtat($filters, ['%loué%', '%louée%']);

        return [
            'nombre' => $nombre,
        ];
    }

    /**
     * KPI "Source de financement" : par ligne budgétaire (sourceFinancement) et par projet donateur.
     */
    /**
     * BUG CORRIGÉ (500 remonté par le frontend) : `par_source_financement` lisait
     * `a.sourceFinancement`, un champ qui n'existe plus sur Asset (supprimé lors d'un
     * refactoring ultérieur — mapping payload commenté dans
     * AssetManagementService::applyPayload()). La "source de financement" est désormais dérivée
     * du projet lié (même logique que AssetResponseBuilder), donc essentiellement la même donnée
     * que `par_projet` ci-dessous, présentée différemment (avec bucket "Non renseigné" agrégé).
     */
    public function getBatimentsFinancement(array $filters): array
    {
        $sourceQb = $this->createQueryBuilder('a')
            ->select(
                "COALESCE(p.nom, 'Non renseigné') as source",
                'COUNT(DISTINCT a.id) as nombre'
            )
            ->leftJoin('a.projects', 'p', Join::WITH, 'p.isDelete = false')
            ->where('a.isDelete = :isDelete')
            ->setParameter('isDelete', false)
            ->groupBy('source')
            ->orderBy('nombre', 'DESC');
        $this->applyBuildingScope($sourceQb);
        $this->applyFilters($sourceQb, $filters);
        $sourceResults = $sourceQb->getQuery()->getResult();

        $projetQb = $this->createQueryBuilder('a')
            ->select('p.id as projet_id', 'p.nom as projet_nom', 'COUNT(DISTINCT a.id) as nombre')
            ->innerJoin('a.projects', 'p')
            ->where('a.isDelete = :isDelete')
            ->andWhere('p.isDelete = :isDeleteP')
            ->setParameter('isDelete', false)
            ->setParameter('isDeleteP', false)
            ->groupBy('p.id, p.nom')
            ->orderBy('nombre', 'DESC');
        $this->applyBuildingScope($projetQb);
        $this->applyFilters($projetQb, $filters);
        $projetResults = $projetQb->getQuery()->getResult();

        return [
            'par_source_financement' => array_map(fn($row) => [
                'source_financement' => $row['source'],
                'nombre' => (int) $row['nombre'],
            ], $sourceResults),
            'par_projet' => array_map(fn($row) => [
                'projet_id' => (int) $row['projet_id'],
                'projet_nom' => $row['projet_nom'],
                'nombre' => (int) $row['nombre'],
            ], $projetResults),
        ];
    }

    /**
     * KPI "Année de construction" (11a). Proxy : `dateAcquisition` (pas de date de construction
     * dédiée dans le schéma actuel — même logique que l'ancienneté du parc automobile).
     */
    public function getBatimentsRepartitionAnneeConstruction(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select(
                'SUBSTRING(a.dateAcquisition, 1, 4) as annee',
                'COUNT(DISTINCT a.id) as nombre'
            )
            ->where('a.isDelete = :isDelete')
            ->andWhere('a.dateAcquisition IS NOT NULL')
            ->setParameter('isDelete', false)
            ->groupBy('annee')
            ->orderBy('annee', 'ASC');
        $this->applyBuildingScope($qb);
        $this->applyFilters($qb, $filters);
        $results = $qb->getQuery()->getResult();

        return array_map(fn($row) => [
            'annee' => $row['annee'],
            'nombre' => (int) $row['nombre'],
        ], $results);
    }

    /**
     * KPI "Année de dernière réfection" (11b). Proxy : dernière intervention AssetMaintenance
     * dont le motif contient "réfection" — pas un champ dédié, à confirmer contre les données réelles.
     */
    public function getBatimentsRepartitionAnneeRefection(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('MAX(m.dateIntervention) as derniere_refection')
            ->innerJoin('a.maintenances', 'm')
            ->where('a.isDelete = :isDelete')
            ->andWhere('LOWER(m.motif) LIKE :refection')
            ->andWhere('m.dateIntervention IS NOT NULL')
            ->setParameter('isDelete', false)
            ->setParameter('refection', '%réfection%')
            ->groupBy('a.id');
        $this->applyBuildingScope($qb);
        $this->applyFilters($qb, $filters);
        $results = $qb->getQuery()->getResult();

        $parAnnee = [];
        foreach ($results as $row) {
            $date = $row['derniere_refection'];
            if (null === $date) {
                continue;
            }
            $annee = is_string($date) ? substr($date, 0, 4) : $date->format('Y');
            $parAnnee[$annee] = ($parAnnee[$annee] ?? 0) + 1;
        }
        ksort($parAnnee);

        return array_map(
            fn(string $annee, int $nombre) => ['annee' => $annee, 'nombre' => $nombre],
            array_keys($parAnnee),
            array_values($parAnnee)
        );
    }

    /**
     * KPI "Croisement état x département".
     */
    public function getBatimentsCroisementEtatDepartement(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select(
                'd.id as departement_id',
                'd.nom as departement_nom',
                'e.nom as etat_nom',
                'COUNT(DISTINCT a.id) as nombre'
            )
            ->innerJoin('a.etatBiens', 'e')
            ->innerJoin('a.services', 's')
            ->innerJoin('s.departement', 'd')
            ->where('a.isDelete = :isDelete')
            ->andWhere('e.isDelete = :isDeleteE')
            ->andWhere('s.is_active = :isActive')
            ->setParameter('isDelete', false)
            ->setParameter('isDeleteE', false)
            ->setParameter('isActive', true)
            ->groupBy('d.id, d.nom, e.id, e.nom')
            ->orderBy('d.nom', 'ASC');
        $this->applyBuildingScope($qb);
        $this->applyFilters($qb, $filters);
        $results = $qb->getQuery()->getResult();

        $grouped = [];
        foreach ($results as $row) {
            $depId = (int) $row['departement_id'];
            if (!isset($grouped[$depId])) {
                $grouped[$depId] = [
                    'departement_id' => $depId,
                    'departement_nom' => $row['departement_nom'],
                    'etats' => [],
                ];
            }
            $grouped[$depId]['etats'][] = [
                'etat' => $row['etat_nom'],
                'nombre' => (int) $row['nombre'],
            ];
        }

        return array_values($grouped);
    }

    /**
     * KPI "Classement des régions".
     */
    public function getBatimentsClassementRegions(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('r.id as region_id', 'r.nom as region_nom', 'COUNT(DISTINCT a.id) as nombre')
            ->innerJoin('a.services', 's')
            ->innerJoin('s.region', 'r')
            ->where('a.isDelete = :isDelete')
            ->andWhere('s.is_active = :isActive')
            ->setParameter('isDelete', false)
            ->setParameter('isActive', true)
            ->groupBy('r.id, r.nom')
            ->orderBy('nombre', 'DESC');
        $this->applyBuildingScope($qb);
        $this->applyFilters($qb, $filters);
        $results = $qb->getQuery()->getResult();

        return array_map(fn($row) => [
            'region_id' => (int) $row['region_id'],
            'region_nom' => $row['region_nom'],
            'nombre_batiments' => (int) $row['nombre'],
        ], $results);
    }

    /**
     * Restreint une requête aux biens dont un AssetType a pour nom "%bâtiment%".
     */
    private function applyBuildingScope(QueryBuilder $qb, string $alias = 'a'): void
    {
        $qb->innerJoin("{$alias}.categories", 'catb')
            ->andWhere('(LOWER(catb.nom) LIKE :batimentType OR catb.id = :batimentCatId)')
            ->andWhere('catb.isDelete = :catbDelete')
            ->setParameter('batimentType', '%bâtiment%')
            ->setParameter('batimentCatId', 35)
            ->setParameter('catbDelete', false);
    }

    /**
     * Compte les bâtiments ayant au moins un ÉtatBien correspondant à l'un des motifs
     * `$includePatterns`, et n'en ayant aucun correspondant à `$excludePatterns` (le cas échéant).
     *
     * @param string[] $includePatterns
     * @param string[]|null $excludePatterns
     */
    private function countBatimentsMatchingEtat(array $filters, array $includePatterns, ?array $excludePatterns = null): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.id) as total')
            ->innerJoin('a.etatBiens', 'e')
            ->where('a.isDelete = :isDelete')
            ->andWhere('e.isDelete = :isDeleteE')
            ->setParameter('isDelete', false)
            ->setParameter('isDeleteE', false);

        $orX = [];
        foreach ($includePatterns as $i => $pattern) {
            $orX[] = "LOWER(e.nom) LIKE :inc{$i}";
            $qb->setParameter("inc{$i}", $pattern);
        }
        $qb->andWhere(implode(' OR ', $orX));

        if (null !== $excludePatterns) {
            foreach ($excludePatterns as $i => $pattern) {
                $qb->andWhere("LOWER(e.nom) NOT LIKE :exc{$i}")->setParameter("exc{$i}", $pattern);
            }
        }

        $this->applyBuildingScope($qb);
        $this->applyFilters($qb, $filters);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    // ======================================================================
    // Structures (Service)
    // Contrairement aux domaines précédents, ce module est centré sur l'entité Service elle-même
    // (pas sur Asset) : les requêtes purement "structure" (total, classification, type de service,
    // géographique) sont rootées sur Service via applyServiceFilters(). Les requêtes portant sur
    // l'infrastructure d'une structure (bâtiment, valeur, réfection, plan, devis) restent rootées
    // sur Asset et rejoignent Service via `a.services`, en réutilisant applyBuildingScope() /
    // applyFilters() tels quels (le filtre `servicesId` y a déjà le même sens).
    //
    // "Surface au sol" (KPI 9) n'est PAS implémentée : voir self::CHAMPS_EN_ATTENTE en tête de
    // classe (regroupée avec "Superficie" des terrains).
    // "Structures génératrices de recettes propres" (12) n'est PAS implémentée : voir
    // self::ATTRIBUTS_STRUCTURE_EN_ATTENTE en tête de classe — nécessite de nouveaux attributs sur
    // Service, pas résoluble via le catalogue Champ.
    //
    // Hypothèses (à confirmer) :
    // - "Centrale / Déconcentrée / Rattachée / Sous tutelle" (KPI 1) sont des tags TypeOrganigramme
    //   (mot-clé sur TypeOrganigramme.nom), et non le critère region_id utilisé par
    //   getRepartitionServicesCentralDeconcentre() pour la répartition des BIENS par structure
    //   (KPI patrimoine "central vs déconcentré", confirmé correct pour cet autre usage).
    // - "Bâtiment construit" (4), "à réfectionner" (5), "plan"/"plan-type officiel" (10) et
    //   "devis" (11) réutilisent respectivement applyBuildingScope, un ÉtatBien "à réfectionner"
    //   et des mots-clés sur PieceJointe.nom ("plan", "plan-type"/"plan type", "devis") — même
    //   mécanisme que les autres domaines.
    // - "Coût de réfection estimé" (7) est lu sur AssetMaintenance.cout, pour les interventions
    //   dont le motif contient "réfection" — même hypothèse que l'année de dernière réfection des
    //   bâtiments.
    // - "Année de construction" (8) délègue à getBatimentsRepartitionAnneeConstruction() : même
    //   donnée que le KPI 11a des bâtiments, pas de duplication de requête.
    // ======================================================================

    public function getStructuresTotal(array $filters): int
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(DISTINCT s.id) as total')
            ->from(Service::class, 's')
            ->where('s.is_active = :isActive')
            ->setParameter('isActive', true);
        $this->applyServiceFilters($qb, $filters, 's');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * KPI "Nombre de structures" par classification (Centrale / Déconcentrée / Rattachée /
     * Sous tutelle), via mot-clé sur TypeOrganigramme.nom.
     */
    public function getStructuresRepartitionClassification(array $filters): array
    {
        $total = $this->getStructuresTotal($filters);

        $keywordMap = [
            'Centrale' => ['%centrale%', '%central%'],
            'Déconcentrée' => ['%déconcentr%', '%deconcentr%'],
            'Rattachée' => ['%rattach%'],
            'Sous tutelle' => ['%tutelle%'],
        ];

        $breakdown = [];
        $countedIds = [];
        foreach ($keywordMap as $label => $patterns) {
            $ids = $this->getStructureIdsByTypeOrganigrammeKeyword($filters, $patterns);
            $breakdown[] = ['classification' => $label, 'nombre' => count($ids)];
            foreach ($ids as $id) {
                $countedIds[$id] = true;
            }
        }

        $breakdown[] = ['classification' => 'Non classée', 'nombre' => max(0, $total - count($countedIds))];

        return ['total' => $total, 'par_classification' => $breakdown];
    }

    /**
     * @param string[] $patterns
     * @return int[]
     */
    private function getStructureIdsByTypeOrganigrammeKeyword(array $filters, array $patterns): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('DISTINCT s.id as id')
            ->from(Service::class, 's')
            ->innerJoin('s.typeOrganigrammes', 'to')
            ->where('s.is_active = :isActive')
            ->andWhere('to.isDelete = false')
            ->setParameter('isActive', true);

        $orX = [];
        foreach ($patterns as $i => $pattern) {
            $orX[] = "LOWER(to.nom) LIKE :kw{$i}";
            $qb->setParameter("kw{$i}", $pattern);
        }
        $qb->andWhere(implode(' OR ', $orX));

        $this->applyServiceFilters($qb, $filters, 's');

        return array_map(fn($row) => (int) $row['id'], $qb->getQuery()->getResult());
    }

    /**
     * KPI "Répartition par type de service" (champ `type_service`, ex. POSTE / SERVICE).
     */
    public function getStructuresRepartitionParTypeService(array $filters): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select(
                "CASE WHEN s.type_service IS NULL OR s.type_service = '' THEN 'Non renseigné' ELSE s.type_service END as type_service",
                'COUNT(DISTINCT s.id) as nombre'
            )
            ->from(Service::class, 's')
            ->where('s.is_active = :isActive')
            ->setParameter('isActive', true)
            ->groupBy('type_service')
            ->orderBy('nombre', 'DESC');
        $this->applyServiceFilters($qb, $filters, 's');
        $results = $qb->getQuery()->getResult();

        return array_map(fn($row) => [
            'type_service' => $row['type_service'],
            'nombre' => (int) $row['nombre'],
        ], $results);
    }

    /**
     * KPI "Répartition géographique" des structures : arbre Région -> Département -> Arrondissement.
     */
    public function getStructuresRepartitionGeographique(array $filters): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select(
                'r.id as region_id',
                'r.nom as region_nom',
                'd.id as departement_id',
                'd.nom as departement_nom',
                'ar.id as arrondissement_id',
                'ar.nom as arrondissement_nom',
                'COUNT(DISTINCT s.id) as nombre_structures'
            )
            ->from(Service::class, 's')
            ->innerJoin('s.region', 'r')
            ->leftJoin('s.departement', 'd')
            ->leftJoin('s.arrondissement', 'ar')
            ->where('s.is_active = :isActive')
            ->setParameter('isActive', true)
            ->groupBy('r.id, r.nom, d.id, d.nom, ar.id, ar.nom')
            ->orderBy('r.nom', 'ASC');
        $this->applyServiceFilters($qb, $filters, 's');
        $results = $qb->getQuery()->getResult();

        $tree = [];
        foreach ($results as $row) {
            $regionId = (int) $row['region_id'];
            if (!isset($tree[$regionId])) {
                $tree[$regionId] = [
                    'region_id' => $regionId,
                    'region_nom' => $row['region_nom'],
                    'nombre_structures' => 0,
                    'departements' => [],
                ];
            }
            $tree[$regionId]['nombre_structures'] += (int) $row['nombre_structures'];

            if (null !== $row['departement_id']) {
                $depId = (int) $row['departement_id'];
                if (!isset($tree[$regionId]['departements'][$depId])) {
                    $tree[$regionId]['departements'][$depId] = [
                        'departement_id' => $depId,
                        'departement_nom' => $row['departement_nom'],
                        'nombre_structures' => 0,
                        'arrondissements' => [],
                    ];
                }
                $tree[$regionId]['departements'][$depId]['nombre_structures'] += (int) $row['nombre_structures'];

                if (null !== $row['arrondissement_id']) {
                    $arId = (int) $row['arrondissement_id'];
                    $tree[$regionId]['departements'][$depId]['arrondissements'][$arId] = [
                        'arrondissement_id' => $arId,
                        'arrondissement_nom' => $row['arrondissement_nom'],
                        'nombre_structures' => (int) $row['nombre_structures'],
                    ];
                }
            }
        }

        return array_values(array_map(function ($region) {
            $region['departements'] = array_values(array_map(function ($dep) {
                $dep['arrondissements'] = array_values($dep['arrondissements']);
                return $dep;
            }, $region['departements']));
            return $region;
        }, $tree));
    }

    /**
     * KPI "Structures avec bâtiment construit" : nombre avec / sans bâtiment lié.
     */
    public function getStructuresAvecBatiment(array $filters): array
    {
        $total = $this->getStructuresTotal($filters);

        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT s.id) as total')
            ->innerJoin('a.services', 's')
            ->where('a.isDelete = :isDelete')
            ->andWhere('s.is_active = :isActive')
            ->setParameter('isDelete', false)
            ->setParameter('isActive', true);
        $this->applyBuildingScope($qb);
        $this->applyFilters($qb, $filters);
        $avec = (int) $qb->getQuery()->getSingleScalarResult();

        return [
            'total_structures' => $total,
            'avec_batiment' => $avec,
            'sans_batiment' => max(0, $total - $avec),
        ];
    }

    /**
     * KPI "Structures à réfectionner" : bâtiment lié à l'état "à réfectionner".
     */
    public function getStructuresARefectionner(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT s.id) as total')
            ->innerJoin('a.services', 's')
            ->innerJoin('a.etatBiens', 'e')
            ->where('a.isDelete = :isDelete')
            ->andWhere('s.is_active = :isActive')
            ->andWhere('e.isDelete = :isDeleteE')
            ->setParameter('isDelete', false)
            ->setParameter('isActive', true)
            ->setParameter('isDeleteE', false);
        $this->addAReformerCondition($qb, 'e');
        $this->applyBuildingScope($qb);
        $this->applyFilters($qb, $filters);
        $nombre = (int) $qb->getQuery()->getSingleScalarResult();

        return ['nombre' => $nombre, 'total_structures' => $this->getStructuresTotal($filters)];
    }

    /**
     * KPI "Valeur totale des infrastructures" : somme des valeurs des bâtiments liés à une structure.
     */
    public function getStructuresValeurInfrastructures(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select(
                'COALESCE(SUM(a.valeur), 0) as valeur_totale',
                'COUNT(DISTINCT a.id) as batiments_avec_valeur'
            )
            ->innerJoin('a.services', 's')
            ->where('a.isDelete = :isDelete')
            ->andWhere('s.is_active = :isActive')
            ->andWhere('a.valeur IS NOT NULL')
            ->setParameter('isDelete', false)
            ->setParameter('isActive', true);
        $this->applyBuildingScope($qb);
        $this->applyFilters($qb, $filters);
        $result = $qb->getQuery()->getSingleResult();

        return [
            'valeur_totale' => (float) $result['valeur_totale'],
            'batiments_avec_valeur_renseignee' => (int) $result['batiments_avec_valeur'],
        ];
    }

    /**
     * KPI "Coût de réfection estimé" : global + par région. Lu sur AssetMaintenance.cout pour les
     * interventions dont le motif contient "réfection" (cf. note d'en-tête).
     */
    public function getStructuresCoutRefection(array $filters): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select(
                'COALESCE(SUM(m.cout), 0) as cout_total',
                'COUNT(DISTINCT a.id) as nombre_batiments'
            )
            ->from(Asset::class, 'a')
            ->innerJoin('a.services', 's')
            ->innerJoin('a.maintenances', 'm')
            ->where('a.isDelete = :isDelete')
            ->andWhere('s.is_active = :isActive')
            ->andWhere('LOWER(m.motif) LIKE :refection')
            ->andWhere('m.cout IS NOT NULL')
            ->setParameter('isDelete', false)
            ->setParameter('isActive', true)
            ->setParameter('refection', '%réfection%');
        $this->applyBuildingScope($qb, 'a');
        $this->applyFilters($qb, $filters);
        $global = $qb->getQuery()->getSingleResult();

        $parRegionQb = $this->getEntityManager()->createQueryBuilder()
            ->select(
                'r.id as region_id',
                'r.nom as region_nom',
                'COALESCE(SUM(m.cout), 0) as cout_total'
            )
            ->from(Asset::class, 'a')
            ->innerJoin('a.services', 's')
            ->innerJoin('s.region', 'r')
            ->innerJoin('a.maintenances', 'm')
            ->where('a.isDelete = :isDelete')
            ->andWhere('s.is_active = :isActive')
            ->andWhere('LOWER(m.motif) LIKE :refection')
            ->andWhere('m.cout IS NOT NULL')
            ->setParameter('isDelete', false)
            ->setParameter('isActive', true)
            ->setParameter('refection', '%réfection%')
            ->groupBy('r.id, r.nom')
            ->orderBy('cout_total', 'DESC');
        $this->applyBuildingScope($parRegionQb, 'a');
        $this->applyFilters($parRegionQb, $filters);
        $parRegionResults = $parRegionQb->getQuery()->getResult();

        return [
            'cout_total' => (float) $global['cout_total'],
            'nombre_batiments_concernes' => (int) $global['nombre_batiments'],
            'par_region' => array_map(fn($row) => [
                'region_id' => (int) $row['region_id'],
                'region_nom' => $row['region_nom'],
                'cout_total' => (float) $row['cout_total'],
            ], $parRegionResults),
        ];
    }

    /**
     * KPI "Structures avec plan disponible" (+ construites selon le plan-type officiel).
     * Mot-clé sur PieceJointe.nom, comme "titre foncier" / "carte grise" ailleurs.
     */
    public function getStructuresPlanDisponible(array $filters): array
    {
        $total = $this->getStructuresTotal($filters);

        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT s.id) as total')
            ->innerJoin('a.services', 's')
            ->innerJoin('a.piecesJointes', 'pj')
            ->where('a.isDelete = :isDelete')
            ->andWhere('s.is_active = :isActive')
            ->andWhere('LOWER(pj.nom) LIKE :plan')
            ->setParameter('isDelete', false)
            ->setParameter('isActive', true)
            ->setParameter('plan', '%plan%');
        $this->applyBuildingScope($qb);
        $this->applyFilters($qb, $filters);
        $avecPlan = (int) $qb->getQuery()->getSingleScalarResult();

        $qb2 = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT s.id) as total')
            ->innerJoin('a.services', 's')
            ->innerJoin('a.piecesJointes', 'pj')
            ->where('a.isDelete = :isDelete')
            ->andWhere('s.is_active = :isActive')
            ->andWhere('LOWER(pj.nom) LIKE :planType1 OR LOWER(pj.nom) LIKE :planType2')
            ->setParameter('isDelete', false)
            ->setParameter('isActive', true)
            ->setParameter('planType1', '%plan-type%')
            ->setParameter('planType2', '%plan type%');
        $this->applyBuildingScope($qb2);
        $this->applyFilters($qb2, $filters);
        $planType = (int) $qb2->getQuery()->getSingleScalarResult();

        return [
            'total_structures' => $total,
            'avec_plan' => $avecPlan,
            'construites_selon_plan_type_officiel' => $planType,
        ];
    }

    /**
     * KPI "Structures avec devis disponible". Mot-clé sur PieceJointe.nom.
     */
    public function getStructuresDevisDisponible(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT s.id) as total')
            ->innerJoin('a.services', 's')
            ->innerJoin('a.piecesJointes', 'pj')
            ->where('a.isDelete = :isDelete')
            ->andWhere('s.is_active = :isActive')
            ->andWhere('LOWER(pj.nom) LIKE :devis')
            ->setParameter('isDelete', false)
            ->setParameter('isActive', true)
            ->setParameter('devis', '%devis%');
        $this->applyBuildingScope($qb);
        $this->applyFilters($qb, $filters);
        $avecDevis = (int) $qb->getQuery()->getSingleScalarResult();

        return ['avec_devis' => $avecDevis, 'total_structures' => $this->getStructuresTotal($filters)];
    }

    /**
     * Résout `servicesId` (filtre fusionné, ex-`service_ids` + ex-`organigramme_service_id`) : pour
     * chaque ID donné, inclut l'ID lui-même ET tous ses descendants d'organigramme
     * (resolveOrganigrammeServiceIds fait déjà les deux), puis fait l'union sur l'ensemble des IDs
     * fournis. Un ID feuille se comporte donc comme l'ancien `service_ids` (lui-même seulement,
     * pas de descendants) ; un ID de direction inclut automatiquement les services rattachés,
     * comme l'ancien `organigramme_service_id`.
     *
     * @param array<int|string> $servicesId
     * @return int[]
     */
    private function resolveServicesIdWithDescendants(array $servicesId): array
    {
        $resolved = [];
        foreach ($servicesId as $serviceId) {
            foreach ($this->resolveOrganigrammeServiceIds((int) $serviceId) as $id) {
                $resolved[$id] = true;
            }
        }

        return array_keys($resolved);
    }

    /**
     * Applique les filtres pertinents pour une requête ROOTÉE SUR Service (pas Asset) : périmètre
     * (`servicesId`, région/département/arrondissement) et scoping RBAC (`secured_service_id` /
     * `secured_region_id`). Les autres clés de filtre (catégorie, types de biens, sous-types,
     * projets, états, statuts, exercice) n'ont pas de sens ici et sont ignorées.
     */
    private function applyServiceFilters(QueryBuilder $qb, array $filters, string $alias): void
    {
        if (!empty($filters['servicesId'])) {
            $qb->andWhere("{$alias}.id IN (:servicesIdFilterS)")
                ->setParameter('servicesIdFilterS', $this->resolveServicesIdWithDescendants($filters['servicesId']));
        }

        if (!empty($filters['regionIds'])) {
            $qb->andWhere("{$alias}.region IN (:regionIdsFilterS)")
                ->setParameter('regionIdsFilterS', $filters['regionIds']);
        }

        if (!empty($filters['departementIds'])) {
            $qb->andWhere("{$alias}.departement IN (:departementIdsFilterS)")
                ->setParameter('departementIdsFilterS', $filters['departementIds']);
        }

        if (!empty($filters['arrondissementIds'])) {
            $qb->andWhere("{$alias}.arrondissement IN (:arrondissementIdsFilterS)")
                ->setParameter('arrondissementIdsFilterS', $filters['arrondissementIds']);
        }

        if (!empty($filters['secured_service_id'])) {
            $qb->andWhere("{$alias}.id = :securedServiceIdS")
                ->setParameter('securedServiceIdS', $filters['secured_service_id']);
        }

        if (!empty($filters['secured_region_id'])) {
            $qb->andWhere("{$alias}.region = :securedRegionIdS")
                ->setParameter('securedRegionIdS', $filters['secured_region_id']);
        }
    }

    /**
     * Résout le domaine du dashboard (`vehicules` / `terrains` / `batiments`) correspondant à une
     * catégorie de biens, par mot-clé sur son nom (même convention que applyVehicleScope /
     * applyLandScope / applyBuildingScope). Remplace l'ancien filtre `domaines` : sélectionner une
     * catégorie sélectionne implicitement le domaine associé.
     *
     * `null` si la catégorie n'existe pas, est supprimée, ou ne correspond à aucun domaine dédié
     * (ex. "Matériel informatique", "Mobilier de bureau" — pas de module spécifique aujourd'hui) :
     * dans ce cas l'appelant retombe sur la section "patrimoine" générale, déjà filtrable par
     * catégorie via le mécanisme standard.
     */
    public function resolveDomaineParCategorieId(int $categorieId): ?string
    {
        // Priorité 1: Mapping direct par ID pour éviter les erreurs de modification de libellé par les admins
        $domaine = match ($categorieId) {
            28 => 'vehicules',
            29 => 'terrains',
            35 => 'batiments',
            // La catégorie 31 (Informatique) n'a pas de module dashboard dédié = affichage Patrimoine général
            31 => null, 
            default => null,
        };

        if (null !== $domaine) {
            return $domaine;
        }

        // Priorité 2: Fallback par nom de catégorie, au cas où la BDD aurait d'autres IDs pour les mêmes types
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('c.nom as nom')
            ->from(Category::class, 'c')
            ->where('c.id = :id')
            ->andWhere('c.isDelete = false')
            ->setParameter('id', $categorieId);

        $result = $qb->getQuery()->getOneOrNullResult();
        if (null === $result) {
            return null;
        }

        $nom = mb_strtolower((string) $result['nom']);

        return match (true) {
            str_contains($nom, 'matériel roulant') || str_contains($nom, 'materiel roulant') => 'vehicules',
            str_contains($nom, 'terrain') => 'terrains',
            str_contains($nom, 'bâtiment') || str_contains($nom, 'batiment') => 'batiments',
            default => null,
        };
    }

    /**
     * @param bool $excludeSortis Exclut par défaut les biens "sortis" du patrimoine actif (cf.
     *                            applyFiltersOnAsset). Ne passer `false` que pour les requêtes qui
     *                            mesurent spécifiquement les sorties elles-mêmes (ex.
     *                            getGeneralStatistics::sortisQb) — sinon la mesure retournerait
     *                            toujours 0, la condition devenant contradictoire.
     */
    private function applyFilters(QueryBuilder $qb, array $filters, bool $excludeSortis = true): void
    {
        $this->applyFiltersOnAsset($qb, $filters, 'a', $excludeSortis);
    }

    /**
     * Résout une branche d'organigramme (filtre "Organigramme", écran de disponibilité des
     * filtres) : le service racine donné + tous ses descendants, par parcours en mémoire de
     * l'arbre `parent_id` (pas de CTE récursive : MySQL 5.7, ciblé par ce projet d'après
     * DATABASE_URL, ne les supporte pas).
     *
     * @return int[]
     */
    private function resolveOrganigrammeServiceIds(int $rootServiceId): array
    {
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('s.id as id', 'IDENTITY(s.parent) as parent_id')
            ->from(Service::class, 's')
            ->where('s.is_active = :isActive')
            ->setParameter('isActive', true)
            ->getQuery()
            ->getResult();

        $childrenMap = [];
        foreach ($rows as $row) {
            if (null === $row['parent_id']) {
                continue;
            }
            $childrenMap[(int) $row['parent_id']][] = (int) $row['id'];
        }

        $ids = [$rootServiceId];
        $queue = [$rootServiceId];
        while (!empty($queue)) {
            $current = array_pop($queue);
            foreach ($childrenMap[$current] ?? [] as $childId) {
                if (!in_array($childId, $ids, true)) {
                    $ids[] = $childId;
                    $queue[] = $childId;
                }
            }
        }

        return $ids;
    }

    private function applyFiltersOnAsset(QueryBuilder $qb, array $filters, string $alias, bool $excludeSortis = true): void
    {
        // Exclusion par défaut des biens "sortis" du patrimoine actif : un bien ayant une sortie
        // (AssetExit) non supprimée n'est PAS comptabilisé dans les KPIs, en plus du filtre
        // `isDelete = false` déjà appliqué par chaque méthode appelante. Basé sur la relation
        // AssetExit (pas sur le champ libre `a.statut`, écrasable via PATCH /assets et pas
        // source de vérité — cf. commentaire de getGeneralStatistics()).
        // Ignorée si l'appelant demande explicitement les biens SORTIS via le filtre `statuts`
        // (ex. ?statuts[]=SORTIS ou ?statuts[]=SORTIE pour les mesurer), ou passe $excludeSortis=false (requêtes qui
        // mesurent les sorties elles-mêmes).
<<<<<<< HEAD
        $excludeSortisRequested = !in_array('SORTIS', $filters['statuts'] ?? [], true) && !in_array('SORTIE', $filters['statuts'] ?? [], true);
        if ($excludeSortis && $excludeSortisRequested) {
=======
        if ($excludeSortis && !in_array('SORTIE', $filters['statuts'] ?? [], true)) {
>>>>>>> origin/marceldev
            $qb->leftJoin("{$alias}.sortie", 'default_excl_sortie')
                ->andWhere('(default_excl_sortie.id IS NULL OR default_excl_sortie.isDelete = :defaultExclSortieDelete)')
                ->setParameter('defaultExclSortieDelete', true);
        }

        // Filtre par structures (fusion ex-service_ids + ex-organigramme_service_id : chaque ID
        // inclut lui-même + tous ses descendants d'organigramme, cf. resolveServicesIdWithDescendants).
        if (!empty($filters['servicesId'])) {
            $qb->innerJoin("{$alias}.services", 's_filter')
                ->andWhere('s_filter.id IN (:servicesId)')
                ->andWhere('s_filter.is_active = :isActiveFilter')
                ->setParameter('servicesId', $this->resolveServicesIdWithDescendants($filters['servicesId']))
                ->setParameter('isActiveFilter', true);
        }

        // Filtre par catégories (une ou plusieurs)
        if (!empty($filters['categorieId'])) {
            $qb->innerJoin("{$alias}.categories", 'c_filter')
                ->andWhere('c_filter.id IN (:categorieId)')
                ->andWhere('c_filter.isDelete = :isDeleteFilter')
                ->setParameter('categorieId', $filters['categorieId'])
                ->setParameter('isDeleteFilter', false);
        }

        // Filtre par types de biens
        if (!empty($filters['assetTypeIds'])) {
            $qb->innerJoin("{$alias}.assetTypes", 't_filter')
                ->andWhere('t_filter.id IN (:assetTypeIds)')
                ->andWhere('t_filter.isDelete = :isDeleteFilter')
                ->setParameter('assetTypeIds', $filters['assetTypeIds'])
                ->setParameter('isDeleteFilter', false);
        }

        // Filtre par projets
        if (!empty($filters['projectIds'])) {
            $qb->innerJoin("{$alias}.projects", 'p_filter')
                ->andWhere('p_filter.id IN (:projectIds)')
                ->andWhere('p_filter.isDelete = :isDeleteFilter')
                ->setParameter('projectIds', $filters['projectIds'])
                ->setParameter('isDeleteFilter', false);
        }

        // ✅ NOUVEAU : Filtre par états de biens
        if (!empty($filters['etatBienIds'])) {
            $qb->innerJoin("{$alias}.etatBiens", 'e_filter')
                ->andWhere('e_filter.id IN (:etatBienIds)')
                ->andWhere('e_filter.isDelete = :isDeleteFilter')
                ->setParameter('etatBienIds', $filters['etatBienIds'])
                ->setParameter('isDeleteFilter', false);
        }

        // Filtre par statut de gestion (Actif / En maintenance / Sortis). Reproduit exactement la
        // logique par relation de getGeneralStatistics() (AssetExit / AssetMaintenance), pas le
        // champ libre `a.statut` : ce champ peut être écrasé librement via PATCH /assets et n'est
        // pas la source de vérité du cycle de vie (cf. commentaire de getGeneralStatistics()).
        if (!empty($filters['statuts'])) {
            $statutConditions = [];

<<<<<<< HEAD
            if (in_array('SORTIS', $filters['statuts'], true) || in_array('SORTIE', $filters['statuts'], true)) {
=======
            if (in_array('SORTIE', $filters['statuts'], true)) {
>>>>>>> origin/marceldev
                $qb->leftJoin("{$alias}.sortie", 'statut_sortie_filter');
                $statutConditions[] = '(statut_sortie_filter.id IS NOT NULL AND statut_sortie_filter.isDelete = false)';
            }

            if (in_array('EN MAINTENANCE', $filters['statuts'], true)) {
                $qb->leftJoin("{$alias}.maintenances", 'statut_maint_filter', Join::WITH, 'statut_maint_filter.dateRecuperation IS NULL');
                $statutConditions[] = 'statut_maint_filter.id IS NOT NULL';
            }

            if (in_array('ACTIF', $filters['statuts'], true)) {
                $qb->leftJoin("{$alias}.sortie", 'statut_sortie_actif_filter');
                $qb->leftJoin("{$alias}.maintenances", 'statut_maint_actif_filter', Join::WITH, 'statut_maint_actif_filter.dateRecuperation IS NULL');
                $statutConditions[] = '((statut_sortie_actif_filter.id IS NULL OR statut_sortie_actif_filter.isDelete = true) AND statut_maint_actif_filter.id IS NULL)';
            }

            if (!empty($statutConditions)) {
                $qb->andWhere('(' . implode(' OR ', $statutConditions) . ')');
            }
        }

        // Filtre par région / département / arrondissement (via le service du bien)
        if (!empty($filters['regionIds'])) {
            $qb->innerJoin("{$alias}.services", 'reg_s_filter')
                ->innerJoin('reg_s_filter.region', 'reg_filter')
                ->andWhere('reg_filter.id IN (:regionIdsFilter)')
                ->setParameter('regionIdsFilter', $filters['regionIds']);
        }

        if (!empty($filters['departementIds'])) {
            $qb->innerJoin("{$alias}.services", 'dep_s_filter')
                ->innerJoin('dep_s_filter.departement', 'dep_filter')
                ->andWhere('dep_filter.id IN (:departementIdsFilter)')
                ->setParameter('departementIdsFilter', $filters['departementIds']);
        }

        if (!empty($filters['arrondissementIds'])) {
            $qb->innerJoin("{$alias}.services", 'arr_s_filter')
                ->innerJoin('arr_s_filter.arrondissement', 'arr_filter')
                ->andWhere('arr_filter.id IN (:arrondissementIdsFilter)')
                ->setParameter('arrondissementIdsFilter', $filters['arrondissementIds']);
        }

        // Filtre par sous-type de bien
        if (!empty($filters['assetSubTypeIds'])) {
            $qb->innerJoin("{$alias}.assetSubTypes", 'st_filter')
                ->andWhere('st_filter.id IN (:assetSubTypeIdsFilter)')
                ->andWhere('st_filter.isDelete = :isDeleteFilter')
                ->setParameter('assetSubTypeIdsFilter', $filters['assetSubTypeIds'])
                ->setParameter('isDeleteFilter', false);
        }

        // Filtre par exercice (année de dateAcquisition) — remplace l'ancien couple anneeDebut/anneeFin.
        if (!empty($filters['exercice'])) {
            $qb->andWhere("SUBSTRING({$alias}.dateAcquisition, 1, 4) = :exerciceFilter")
                ->setParameter('exerciceFilter', (string) $filters['exercice']);
        }

        // Security Data Isolation (Voter / Role mapping)
        if (!empty($filters['secured_service_id'])) {
            // Join is already conditionally done earlier, but to be sure we do it again exclusively
            $qb->innerJoin("{$alias}.services", 'sec_s_filter')
                ->andWhere('sec_s_filter.id = :securedServiceId')
                ->setParameter('securedServiceId', $filters['secured_service_id']);
        }

        if (!empty($filters['secured_region_id'])) {
            $qb->innerJoin("{$alias}.services", 'sec_s_reg_filter')
                ->innerJoin("sec_s_reg_filter.region", 'sec_reg_filter')
                ->andWhere('sec_reg_filter.id = :securedRegionId')
                ->setParameter('securedRegionId', $filters['secured_region_id']);
        }
    }


}