<?php

namespace App\Service\Core;

use App\Repository\Cour\CourrierRepository;
use App\Repository\Cour\CourrierDepartRepository;
use App\Repository\Cour\TransmissionRepository;
use App\Repository\Cour\ReponseRepository;
use App\Repository\Core\UserRepository;
use App\Repository\Core\ServiceRepository;
use App\Repository\Core\RoleRepository;
use App\Repository\Core\CorrespondantRepository;
use App\Repository\Core\CategorieCorrespondantRepository;
use App\Repository\Core\TypeCourrierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\QueryBuilder;

class StatistiqueService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CourrierRepository $courrierRepository,
        private CourrierDepartRepository $courrierDepartRepository,
        private TransmissionRepository $transmissionRepository,
        private ReponseRepository $reponseRepository,
        private UserRepository $userRepository,
        private ServiceRepository $serviceRepository,
        private RoleRepository $roleRepository,
        private CorrespondantRepository $correspondantRepository,
        private CategorieCorrespondantRepository $categorieCorrespondantRepository,
        private TypeCourrierRepository $typeCourrierRepository,
        private Connection $connection
    ) {
    }

    /**
     * Génère toutes les statistiques globales selon les filtres fournis
     */
    public function generateGlobalStatistics(array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);

        return [
            'courrier_arrive' => $this->getCourrierArriveStatistics($filters),
            'courrier_depart' => $this->getCourrierDepartStatistics($filters),
            'transmissions' => $this->getTransmissionStatistics($filters),
            'reponses' => $this->getReponseStatistics($filters),
            'utilisateurs' => $this->getUserStatistics($filters),
            'roles' => $this->getRoleStatistics($filters),
            'services' => $this->getServiceStatistics($filters),
            'correspondants' => $this->getCorrespondantStatistics($filters),
            'categories' => $this->getCategorieStatistics($filters),
            'types_courrier' => $this->getTypeCourrierStatistics($filters),
            'relances' => $this->getRelanceStatistics($filters)
        ];
    }

    private function normalizeFilters(array $filters): array
    {
        if (isset($filters['service_ids'])) {
            if (is_string($filters['service_ids'])) {
                $filters['service_ids'] = array_filter(
                    array_map('intval', preg_split('/[,\s]+/', $filters['service_ids']))
                );
            } elseif (is_array($filters['service_ids'])) {
                $filters['service_ids'] = array_values(
                    array_filter(array_map('intval', $filters['service_ids']))
                );
            } else {
                unset($filters['service_ids']);
            }
        }

        if (isset($filters['service_id'])) {
            if (is_array($filters['service_id'])) {
                $filters['service_ids'] = array_values(
                    array_filter(array_map('intval', $filters['service_id']))
                );
            } elseif (is_numeric($filters['service_id'])) {
                $filters['service_ids'] = [(int) $filters['service_id']];
            }
            unset($filters['service_id']);
        }

        $scalarKeys = [
            'search',
            'date_debut',
            'date_fin',
            'date',
            'year',
            'priorite',
            'categorie',
            'categorie_id',
            'type_courrier',
            'type_courrier_id',
            'classe_courrier',
            'provenance',
            'correspondant_id',
            'createur',
            'user_id',
            'statut',
            'order_by',
            'isarchive',
            'is_delete'
        ];

        foreach ($scalarKeys as $key) {
            if (isset($filters[$key]) && is_array($filters[$key])) {
                $filters[$key] = reset($filters[$key]);
            }
        }

        return $filters;
    }

    /**
     * Statistiques des courriers arrivés - Utilise la même logique que GetCollectionController
     */
    /**
     * Statistiques des courriers arrivés - TOUTE la période, sans filtre date
     * Sans la section par_mois
     */
    private function getCourrierArriveStatistics(array $filters): array
    {

        // Requête de base (comme dans la liste / collection)
        $qb = $this->courrierRepository->createQueryBuilder('c')
            ->where('c.isDelete = :isDelete')
            ->setParameter('isDelete', $filters['is_delete'] ?? false)
            ->andWhere('c.isArchive = :isArchive')
            ->setParameter('isArchive', $filters['isarchive'] ?? false);

        // On applique tous les filtres, y compris les dates
        $this->applyCourrierFilters($qb, $filters);

        // Total général
        $total = (clone $qb)
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();


        // Statistiques détaillées (toutes sur la même base sans restriction temporelle)
        $parStatut     = $this->getCourrierGroupedByStatut($filters);
        $parPriorite   = $this->getCourrierGroupedByPriorite($filters);
        $parService    = $this->getCourrierParService($filters);
        $parType       = $this->getCourrierGroupedByType($filters);
        $parProvenance = $this->getCourrierGroupedByProvenance($filters);

        // Confidentiels
        $confidentiels = (clone $qb)
            ->andWhere('c.isConfidentiel = :conf')
            ->setParameter('conf', true)
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Gelés
        $geles = (clone $qb)
            ->andWhere('c.isGeled = :gel')
            ->setParameter('gel', true)
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Liste des derniers courriers (50 max)
        $details = $this->getCourrierDetails($filters, 50);

        return [
            'total'             => (int) $total,
            'par_statut'        => $parStatut,
            'par_priorite'      => $parPriorite,
            'par_service'       => $parService,
            'par_type_courrier' => $parType,
            'par_provenance'    => $parProvenance,
            'confidentiels'     => (int) $confidentiels,
            'geles'             => (int) $geles,
            'details'           => $details
            // → plus de 'par_mois' ici
        ];
    }

    private function getServiceIdsFromFilters(array $filters): array
    {
        if (!empty($filters['service_ids']) && is_array($filters['service_ids'])) {
            $ids = array_values(array_unique(array_map('intval', $filters['service_ids'])));
            return array_values(array_filter($ids, fn($id) => $id > 0));
        }

        if (!empty($filters['service_id'])) {
            return [(int) $filters['service_id']];
        }

        if (!empty($filters['service_traitant'])) {
            return [(int) $filters['service_traitant']];
        }

        return [];
    }

    /**
     * Applique les filtres sur la requete - meme logique que GetCollectionController
     */
    private function applyCourrierFilters($queryBuilder, array $filters): void
    {
        // Tracker les jointures déjà effectuées pour éviter les doublons
        $joinsDone = [];
        
        // 🔍 Recherche texte
        if (!empty($filters['search'])) {
            $queryBuilder->andWhere('LOWER(c.search) LIKE LOWER(:search)')
                         ->setParameter('search', '%' . trim($filters['search']) . '%');
        }

        // 📆 Filtres de date (toujours sur createdAt)
        if (!empty($filters['date_debut']) && !empty($filters['date_fin'])) {
            $queryBuilder->andWhere('c.createdAt >= :start AND c.createdAt <= :end')
                         ->setParameter('start', $filters['date_debut'] . ' 00:00:00')
                         ->setParameter('end', $filters['date_fin'] . ' 23:59:59');
        } elseif (!empty($filters['date'])) {
            $queryBuilder->andWhere('c.createdAt >= :dateStart AND c.createdAt <= :dateEnd')
                         ->setParameter('dateStart', $filters['date'] . ' 00:00:00')
                         ->setParameter('dateEnd', $filters['date'] . ' 23:59:59');
        } elseif (!empty($filters['year'])) {
            $queryBuilder->andWhere('YEAR(c.createdAt) = :year')
                         ->setParameter('year', $filters['year']);
        }

        // 🧩 Filtres additionnels
        if (!empty($filters['priorite']) && $filters['priorite'] !== 'Toutes') {
            $queryBuilder->andWhere('c.priorite = :priorite')
                         ->setParameter('priorite', $filters['priorite']);
        }
        
        if (!empty($filters['categorie']) || !empty($filters['categorie_id'])) {
            $categorieId = $filters['categorie_id'] ?? $filters['categorie'];
            if (!isset($joinsDone['p'])) {
                $queryBuilder->leftJoin('c.idProvenance', 'p');
                $joinsDone['p'] = true;
            }
            if (!isset($joinsDone['cat'])) {
                $queryBuilder->leftJoin('p.categories', 'cat');
                $joinsDone['cat'] = true;
            }
            $queryBuilder->andWhere('cat.id = :categorie')
                         ->setParameter('categorie', $categorieId);
        }
        
        if (!empty($filters['type_courrier']) || !empty($filters['type_courrier_id'])) {
            $typeId = $filters['type_courrier_id'] ?? $filters['type_courrier'];
            if (!isset($joinsDone['t'])) {
                $queryBuilder->leftJoin('c.typeCourrier', 't');
                $joinsDone['t'] = true;
            }
            $queryBuilder->andWhere('t.id = :type')
                         ->setParameter('type', $typeId);
        }
        
        if (!empty($filters['classe_courrier'])) {
            $queryBuilder->andWhere('LOWER(c.classeCourrier) LIKE LOWER(:classeCourrier)')
                         ->setParameter('classeCourrier', '%' . trim($filters['classe_courrier']) . '%');
        }
        
        if (!empty($filters['provenance']) || !empty($filters['correspondant_id'])) {
            $provenanceId = $filters['correspondant_id'] ?? $filters['provenance'];
            if (!isset($joinsDone['p'])) {
                $queryBuilder->leftJoin('c.idProvenance', 'p');
                $joinsDone['p'] = true;
            }
            $queryBuilder->andWhere('p.id = :provenance')
                         ->setParameter('provenance', $provenanceId);
        }
        
        $serviceIds = $this->getServiceIdsFromFilters($filters);
        if (!empty($serviceIds)) {
            // Filtrer par le service destinataire de la derniere transmission
            $subQuery = $this->courrierRepository->createQueryBuilder('c2')
                ->select('MAX(t2.id)')
                ->leftJoin('c2.transmissions', 't2')
                ->where('c2.id = c.id')
                ->andWhere('t2.isDelete = false')
                ->getDQL();
            
            $queryBuilder->andWhere(
                $queryBuilder->expr()->exists(
                    $this->courrierRepository->createQueryBuilder('c3')
                        ->select('1')
                        ->leftJoin('c3.transmissions', 't3')
                        ->where('c3.id = c.id')
                        ->andWhere('t3.id = (' . $subQuery . ')')
                        ->andWhere('t3.idServiceDestinataire IN (:serviceTraitantIds)')
                        ->getDQL()
                )
            );
            $queryBuilder->setParameter('serviceTraitantIds', $serviceIds);
        }
        
        if (!empty($filters['createur']) || !empty($filters['user_id'])) {
            $userId = $filters['user_id'] ?? $filters['createur'];
            if (!isset($joinsDone['u'])) {
                $queryBuilder->leftJoin('c.idCreateur', 'u');
                $joinsDone['u'] = true;
            }
            $queryBuilder->andWhere('u.id = :createur')
                         ->setParameter('createur', $userId);
        }
        
        if (!empty($filters['statut'])) {
            $queryBuilder->andWhere('c.statut = :statut')
                         ->setParameter('statut', $filters['statut']);
        }

        // Filtre is_confidentiel
        if (isset($filters['is_confidentiel'])) {
            $queryBuilder->andWhere('c.isConfidentiel = :confidentiel')
                         ->setParameter('confidentiel', $filters['is_confidentiel']);
        }

        // 📄 Filtre par présence de courrier départ
        if (isset($filters['has_courrier_depart'])) {
            if ($filters['has_courrier_depart'] === true) {
                // Courriers avec au moins un courrier départ
                $queryBuilder->andWhere(
                    $queryBuilder->expr()->exists(
                        $this->courrierRepository->createQueryBuilder('c_dep')
                            ->select('1')
                            ->from('App\Entity\Cour\CourrierDepart', 'cd')
                            ->where('cd.idCourrier = c.id')
                            ->getDQL()
                    )
                );
            } else {
                // Courriers sans courrier départ
                $queryBuilder->andWhere(
                    $queryBuilder->expr()->not(
                        $queryBuilder->expr()->exists(
                            $this->courrierRepository->createQueryBuilder('c_dep')
                                ->select('1')
                                ->from('App\Entity\Cour\CourrierDepart', 'cd')
                                ->where('cd.idCourrier = c.id')
                                ->getDQL()
                        )
                    )
                );
            }
        }
    }

    /**
     * Récupère les courriers groupés par statut - même logique que GetCollectionController
     */
    private function getCourrierGroupedByStatut(array $filters): array
    {
        $filtersWithoutDate = $filters;
        unset($filtersWithoutDate['date_debut']);
        unset($filtersWithoutDate['date_fin']);
        unset($filtersWithoutDate['date_type']);
        unset($filtersWithoutDate['year']);
        unset($filtersWithoutDate['date']);

        $statuts = ['Reçu', 'Transmis', 'Classé'];
        $parStatut = [];

        $baseQb = $this->createBaseCourrierArriveQueryBuilder($filtersWithoutDate);

        foreach ($statuts as $statut) {
            $qb = clone $baseQb;
            $qb->andWhere('c.statut = :statut')
            ->setParameter('statut', $statut);
            $this->applyCourrierFilters($qb, $filtersWithoutDate);
            $count = $qb->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();
            $parStatut[$statut] = (int)$count;
        }

        // Gelé
        $qbGele = clone $baseQb;
        $qbGele->andWhere('c.isGeled = :geled')
            ->setParameter('geled', true);
        $this->applyCourrierFilters($qbGele, $filtersWithoutDate);
        $parStatut['Gelé'] = (int)$qbGele->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();

        return $parStatut;
    }

    /**
     * Récupère les courriers groupés par priorité - même logique que GetCollectionController
     */
    private function getCourrierGroupedByPriorite(array $filters): array
    {
        // Récupérer toutes les priorités distinctes
        $qbPriorites = $this->courrierRepository->createQueryBuilder('c')
            ->select('DISTINCT c.priorite')
            ->where('c.isDelete = :isDelete')
            ->setParameter('isDelete', $filters['is_delete'] ?? false)
            ->andWhere('c.isArchive = :isArchive')
            ->setParameter('isArchive', $filters['isarchive'] ?? false)
            ->andWhere('c.statut != :statutArchive')
            ->setParameter('statutArchive', 'archivé')
            ->getQuery()
            ->getResult();
        
        $parPriorite = [];
        
        foreach ($qbPriorites as $prioriteRow) {
            $priorite = $prioriteRow['priorite'] ?? 'Non défini';
            
            $queryBuilder = $this->courrierRepository->createQueryBuilder('c')
                ->where('c.isDelete = :isDelete')
                ->setParameter('isDelete', $filters['is_delete'] ?? false)
                ->andWhere('c.isArchive = :isArchive')
                ->setParameter('isArchive', $filters['isarchive'] ?? false)
                ->andWhere('c.statut != :statutArchive')
                ->setParameter('statutArchive', 'archivé');
            
            if ($priorite === 'Non défini') {
                $queryBuilder->andWhere('c.priorite IS NULL');
            } else {
                $queryBuilder->andWhere('c.priorite = :priorite')
                             ->setParameter('priorite', $priorite);
            }
            
            // Appliquer les mêmes filtres (sans le filtre priorité)
            $filtersWithoutPriorite = $filters;
            unset($filtersWithoutPriorite['priorite']);
            $this->applyCourrierFilters($queryBuilder, $filtersWithoutPriorite);
            
            $count = (clone $queryBuilder)->select('COUNT(DISTINCT c.id)')->getQuery()->getSingleScalarResult();
            $parPriorite[$priorite] = (int)$count;
        }
        
        return $parPriorite;
    }

    /**
     * Récupère les courriers groupés par type - même logique que GetCollectionController
     */
    private function getCourrierGroupedByType(array $filters): array
    {
        // Récupérer tous les types de courrier distincts avec jointure
        $qbTypes = $this->courrierRepository->createQueryBuilder('c')
            ->select('DISTINCT t.id, t.nom')
            ->leftJoin('c.typeCourrier', 't')
            ->where('c.isDelete = :isDelete')
            ->setParameter('isDelete', $filters['is_delete'] ?? false)
            ->andWhere('c.isArchive = :isArchive')
            ->setParameter('isArchive', $filters['isarchive'] ?? false)
            ->andWhere('c.statut != :statutArchive')
            ->setParameter('statutArchive', 'archivé')
            ->getQuery()
            ->getResult();
        
        $parType = [];
        
        foreach ($qbTypes as $typeRow) {
            $typeName = $typeRow['nom'] ?? 'Non défini';
            $typeId = $typeRow['id'] ?? null;
            
            $queryBuilder = $this->courrierRepository->createQueryBuilder('c')
                ->where('c.isDelete = :isDelete')
                ->setParameter('isDelete', $filters['is_delete'] ?? false)
                ->andWhere('c.isArchive = :isArchive')
                ->setParameter('isArchive', $filters['isarchive'] ?? false)
                ->andWhere('c.statut != :statutArchive')
                ->setParameter('statutArchive', 'archivé');
            
            if ($typeId === null) {
                $queryBuilder->andWhere('c.typeCourrier IS NULL');
            } else {
                $queryBuilder->leftJoin('c.typeCourrier', 't_filter')
                             ->andWhere('t_filter.id = :typeId')
                             ->setParameter('typeId', $typeId);
            }
            
            // Appliquer les mêmes filtres (sans le filtre type)
            $filtersWithoutType = $filters;
            unset($filtersWithoutType['type_courrier']);
            unset($filtersWithoutType['type_courrier_id']);
            $this->applyCourrierFilters($queryBuilder, $filtersWithoutType);
            
            $count = (clone $queryBuilder)->select('COUNT(DISTINCT c.id)')->getQuery()->getSingleScalarResult();
            if ($count > 0) {
                $parType[$typeName] = (int)$count;
            }
        }
        
        return $parType;
    }

    /**
     * Récupère les courriers groupés par provenance - même logique que GetCollectionController
     */
    private function getCourrierGroupedByProvenance(array $filters): array
    {
        // Récupérer toutes les provenances distinctes avec jointure
        $qbProvenances = $this->courrierRepository->createQueryBuilder('c')
            ->select('DISTINCT p.id, p.nom')
            ->leftJoin('c.idProvenance', 'p')
            ->where('c.isDelete = :isDelete')
            ->setParameter('isDelete', $filters['is_delete'] ?? false)
            ->andWhere('c.isArchive = :isArchive')
            ->setParameter('isArchive', $filters['isarchive'] ?? false)
            ->andWhere('c.statut != :statutArchive')
            ->setParameter('statutArchive', 'archivé')
            ->getQuery()
            ->getResult();
        
        $parProvenance = [];
        
        foreach ($qbProvenances as $provenanceRow) {
            $provenanceName = $provenanceRow['nom'] ?? 'Non défini';
            $provenanceId = $provenanceRow['id'] ?? null;
            
            $queryBuilder = $this->courrierRepository->createQueryBuilder('c')
                ->where('c.isDelete = :isDelete')
                ->setParameter('isDelete', $filters['is_delete'] ?? false)
                ->andWhere('c.isArchive = :isArchive')
                ->setParameter('isArchive', $filters['isarchive'] ?? false)
                ->andWhere('c.statut != :statutArchive')
                ->setParameter('statutArchive', 'archivé');
            
            if ($provenanceId === null) {
                $queryBuilder->andWhere('c.idProvenance IS NULL');
            } else {
                $queryBuilder->leftJoin('c.idProvenance', 'p_filter')
                             ->andWhere('p_filter.id = :provenanceId')
                             ->setParameter('provenanceId', $provenanceId);
            }
            
            // Appliquer les mêmes filtres (sans le filtre provenance)
            $filtersWithoutProvenance = $filters;
            unset($filtersWithoutProvenance['provenance']);
            unset($filtersWithoutProvenance['correspondant_id']);
            $this->applyCourrierFilters($queryBuilder, $filtersWithoutProvenance);
            
            $count = (clone $queryBuilder)->select('COUNT(DISTINCT c.id)')->getQuery()->getSingleScalarResult();
            if ($count > 0) {
                $parProvenance[$provenanceName] = (int)$count;
            }
        }
        
        return $parProvenance;
    }

    /**
     * Statistiques des courriers de départ (excluant les supprimés uniquement)
     */
    private function getCourrierDepartStatistics(array $filters): array
    {
        // Utiliser EntityManager avec filtre isDelete = false
        $qb = $this->entityManager->createQueryBuilder()
            ->select('cd')
            ->from('App\Entity\Cour\CourrierDepart', 'cd')
            ->where('cd.isDelete = :notDeleted')
            ->setParameter('notDeleted', false);
        
        $this->applyFiltersDepart($qb, $filters, 'cd');
        
        // Total (courriers de départ non supprimés)
        $total = (clone $qb)->select('COUNT(cd.id)')->getQuery()->getSingleScalarResult();
        
        // Par type de courrier
        $parTypeCourrier = $this->getGroupedData($qb, 'cd.typeCourrier', 'type_courrier');
        
        // Par classe (utilisation du champ classe si disponible)
        $parClasse = $this->getGroupedData($qb, 'cd.classeCourrier', 'classe');
        
        // Par catégorie correspondant
        $parCategorie = $this->getGroupedDataWithJoinDeep($qb, 'cat.nom', 'categorie', 'cd', 'destinataire', 'cor', 'categories', 'cat');
        
        // Par signataire (utilisateur qui a signé)
        $parSignataire = $this->getUserGroupedData($qb, 'cd', 'idSignataire', 'u');

        // Par destinataire (correspondant)
        $parDestinataire = $this->getGroupedDataWithJoin($qb, 'cor.nom', 'destinataire', 'cd', 'destinataire', 'cor');
        
        // Par mois (basé sur date de signature)
        $parMois = $this->getMonthlyData($qb, 'cd.dateSignature');
        
        // Détails (limité aux 50 premiers)
        $details = $this->getCourrierDepartDetails($filters, 50);
        
        return [
            'total' => (int)$total,
            'par_type_courrier' => $parTypeCourrier,
            'par_classe' => $parClasse,
            'par_categorie' => $parCategorie,
            'par_signataire' => $parSignataire,
            'par_destinataire' => $parDestinataire,
            'par_mois' => $parMois,
            'details' => $details
        ];
    }

    /**
     * Statistiques des transmissions (excluant les supprimées uniquement)
     */
    private function getTransmissionStatistics(array $filters): array
    {
        // Utiliser EntityManager avec filtre isDelete = false
        $qb = $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from('App\Entity\Cour\Transmission', 't')
            ->where('t.isDelete = :notDeleted')
            ->setParameter('notDeleted', false);
        
        $this->applyFiltersTransmission($qb, $filters, 't');
        
        // Total (transmissions non supprimées)
        $total = (clone $qb)->select('COUNT(t.id)')->getQuery()->getSingleScalarResult();
        
        // Par statut
        $parStatut = $this->getGroupedData($qb, 't.statut', 'statut');
        
        // Par type de transfert
        $parTypeTransfert = $this->getGroupedData($qb, 't.typeTransfert', 'type_transfert');
        
        // Par service destinataire
        $parServiceDestinataire = $this->getGroupedDataWithJoin($qb, 's.nom', 'service_destinataire', 't', 'idServiceDestinataire', 's');
        
        // Par émetteur
        $parEmetteur = $this->getUserGroupedData($qb, 't', 'idEmetteur', 'u');
        
        // Avec accusé de réception
        $avecAccuseReception = (clone $qb)->andWhere('t.accuseReception = :accuse')
            ->setParameter('accuse', true)
            ->select('COUNT(t.id)')
            ->getQuery()->getSingleScalarResult();
        
        // Par mois (basé sur date de création)
        $parMois = $this->getMonthlyData($qb, 't.createdAt');
        
        // Délai moyen de traitement (en jours)
        $delaiMoyen = $this->getDelaiMoyenTraitement();
        
        // Détails (limité aux 50 premiers)
        $details = $this->getTransmissionDetails($filters, 50);
        
        return [
            'total' => (int)$total,
            'par_statut' => $parStatut,
            'par_type_transfert' => $parTypeTransfert,
            'par_service_destinataire' => $parServiceDestinataire,
            'par_emetteur' => $parEmetteur,
            'avec_accuse_reception' => (int)$avecAccuseReception,
            'par_mois' => $parMois,
            'delai_moyen_traitement' => $delaiMoyen,
            'details' => $details
        ];
    }

    /**
     * Statistiques des réponses (excluant les supprimées uniquement)
     */
    private function getReponseStatistics(array $filters): array
    {
        // Utiliser EntityManager avec filtre isDelete = false
        $qb = $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from('App\Entity\Cour\Reponse', 'r')
            ->where('r.isDelete = :notDeleted')
            ->setParameter('notDeleted', false);
        
        $this->applyFiltersReponse($qb, $filters, 'r');
        
        // Total (réponses non supprimées)
        $total = (clone $qb)->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();
        
        // Par type de réponse
        $parTypeReponse = $this->getGroupedDataWithJoin($qb, 'tr.nom', 'type_reponse', 'r', 'typeReponse', 'tr');
        
        // Par classe du courrier associé (utilise le champ classeCourrier de l'entité Reponse)
        $parClasseCourrier = $this->getGroupedData($qb, 'r.classeCourrier', 'classe_courrier');
        
        // Par type de transmission (basé sur les courriers associés)
        $parTypeTransmission = $this->getGroupedDataWithJoinDeep($qb, 't.typeTransfert', 'type_transmission', 'r', 'courriers', 'c', 'transmissions', 't');
        
        // Par service destinataire
        $parServiceDestinataire = $this->getGroupedDataWithJoin($qb, 's.nom', 'service_destinataire', 'r', 'idServiceDestinataire', 's');
        
        // Par rédacteur
        $parRedacteur = $this->getUserGroupedData($qb, 'r', 'idRedacteur', 'u');
        
        // Par mois (basé sur date de création)
        $parMois = $this->getMonthlyData($qb, 'r.createdAt');
        
        // Détails (limité aux 50 premiers)
        $details = $this->getReponseDetails($filters, 50);
        
        return [
            'total' => (int)$total,
            'par_type_reponse' => $parTypeReponse,
            'par_classe_courrier' => $parClasseCourrier,
            'par_type_transmission' => $parTypeTransmission,
            'par_service_destinataire' => $parServiceDestinataire,
            'par_redacteur' => $parRedacteur,
            'par_mois' => $parMois,
            'details' => $details
        ];
    }

    /**
     * Statistiques des utilisateurs
     */
    private function getUserStatistics(array $filters): array
    {
        $serviceIds = $this->getServiceIdsFromFilters($filters);

        if (!empty($serviceIds)) {
            $baseQb = $this->userRepository->createQueryBuilder('u')
                ->where('u.idService IN (:serviceIds)')
                ->setParameter('serviceIds', $serviceIds);

            $total = (clone $baseQb)->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();
            $actifs = (clone $baseQb)
                ->andWhere('u.isActive = :active')
                ->setParameter('active', true)
                ->select('COUNT(u.id)')
                ->getQuery()->getSingleScalarResult();
            $inactifs = (int) $total - (int) $actifs;

            $parService = [];
            $services = $this->serviceRepository->findBy(['id' => $serviceIds]);
            foreach ($services as $service) {
                $count = $this->userRepository->count(['idService' => $service]);
                if ($count > 0) {
                    $parService[$service->getNom()] = $count;
                }
            }

            $parRole = [];
            $roles = $this->roleRepository->findAll();
            foreach ($roles as $role) {
                $count = $this->userRepository->createQueryBuilder('u')
                    ->select('COUNT(u.id)')
                    ->where('u.idRole = :role')
                    ->andWhere('u.idService IN (:serviceIds)')
                    ->setParameter('role', $role)
                    ->setParameter('serviceIds', $serviceIds)
                    ->getQuery()->getSingleScalarResult();
                if ($count > 0) {
                    $parRole[$role->getNom()] = (int) $count;
                }
            }
        } else {
            $total = $this->userRepository->count([]);

            $actifs = $this->userRepository->count(['isActive' => true]);
            $inactifs = $total - $actifs;

            $parService = [];
            $services = $this->serviceRepository->findAll();
            foreach ($services as $service) {
                $count = $this->userRepository->count(['idService' => $service]);
                if ($count > 0) {
                    $parService[$service->getNom()] = $count;
                }
            }

            $parRole = [];
            $roles = $this->roleRepository->findAll();
            foreach ($roles as $role) {
                $count = $this->userRepository->count(['idRole' => $role]);
                if ($count > 0) {
                    $parRole[$role->getNom()] = (int) $count;
                }
            }
        }

        return [
            'total' => (int) $total,
            'actifs' => (int) $actifs,
            'inactifs' => (int) $inactifs,
            'par_service' => $parService,
            'par_role' => $parRole,
            'details' => $this->getUserDetails($filters, 50)
        ];
    }

    /**
     * Statistiques des rôles
     */
    private function getRoleStatistics(array $filters): array
    {
        $roles = $this->roleRepository->findAll();
        $details = $this->getRoleDetails($filters, 50);
        
        return [
            'total' => count($roles),
            'details' => $details
        ];
    }

    /**
     * Statistiques des services
     */
    private function getServiceStatistics(array $filters): array
    {
        $serviceIds = $this->getServiceIdsFromFilters($filters);
        $services = !empty($serviceIds)
            ? $this->serviceRepository->findBy(['id' => $serviceIds])
            : $this->serviceRepository->findAll();
        $details = $this->getServiceDetails($filters, 50);
        
        return [
            'total' => count($services),
            'details' => $details
        ];
    }

    /**
     * Statistiques des correspondants
     */
    private function getCorrespondantStatistics(array $filters): array
    {
        $total = $this->correspondantRepository->count([]);
        
        // Par catégorie
        $parCategorie = [];
        $categories = $this->categorieCorrespondantRepository->findAll();
        foreach ($categories as $categorie) {
            $count = $this->correspondantRepository->createQueryBuilder('cor')
                ->select('COUNT(cor.id)')
                ->join('cor.categories', 'cat')
                ->where('cat.id = :categorieId')
                ->setParameter('categorieId', $categorie->getId())
                ->getQuery()
                ->getSingleScalarResult();
            
            if ($count > 0) {
                $parCategorie[$categorie->getNom()] = (int)$count;
            }
        }
        
        $details = $this->getCorrespondantDetails($filters, 50);
        
        return [
            'total' => $total,
            'par_categorie' => $parCategorie,
            'details' => $details
        ];
    }

    /**
     * Statistiques des catégories
     */
    private function getCategorieStatistics(array $filters): array
    {
        $categories = $this->categorieCorrespondantRepository->findAll();
        $details = $this->getCategorieDetails($filters, 50);
        
        return [
            'total' => count($categories),
            'details' => $details
        ];
    }

    /**
     * Statistiques des types de courrier
     */
    private function getTypeCourrierStatistics(array $filters): array
    {
        $types = $this->typeCourrierRepository->findAll();
        $details = $this->getTypeCourrierDetails($filters, 50);
        
        return [
            'total' => count($types),
            'details' => $details
        ];
    }

    /**
     * Statistiques des relances
     */
    private function getRelanceStatistics(array $filters): array
    {
        $delaiDefaut = 7; // 7 jours par défaut
        $maintenant = new \DateTime();
        
        $depassements = [
            '7_jours' => [
                'count' => 0,
                'description' => 'Courriers en dépassement de 7 jours',
                'courriers' => []
            ],
            '15_jours' => [
                'count' => 0,
                'description' => 'Courriers en dépassement de 15 jours',
                'courriers' => []
            ],
            '30_jours' => [
                'count' => 0,
                'description' => 'Courriers en dépassement de 30 jours',
                'courriers' => []
            ]
        ];
        
        // Calcul des dépassements pour chaque période
        foreach ([7, 15, 30] as $jours) {
            $dateLimit = clone $maintenant;
            $dateLimit->sub(new \DateInterval("P{$jours}D"));
            
            $count = $this->courrierRepository->createQueryBuilder('c')
                ->select('COUNT(c.id)')
                ->where('c.dateArrivee < :dateLimit')
                ->andWhere('c.statut != :traite')
                ->setParameter('dateLimit', $dateLimit)
                ->setParameter('traite', 'Traité')
                ->getQuery()
                ->getSingleScalarResult();
            
            $key = $jours . '_jours';
            $depassements[$key]['count'] = (int)$count;
        }
        
        $totalEnDepassement = array_sum(array_column($depassements, 'count'));
        
        return [
            'total_en_depassement' => $totalEnDepassement,
            'delai_defaut_jours' => $delaiDefaut,
            'depassements' => $depassements
        ];
    }

    private function applyFiltersDepart($qb, array $filters, string $alias): void
    {
        $joinsDone = [];

        if (isset($filters['date_debut'])) {
                $qb->andWhere("$alias.createdAt >= :dateDebut")
                    ->setParameter('dateDebut', $filters['date_debut'] . ' 00:00:00');
        }
        
        if (isset($filters['date_fin'])) {
                $qb->andWhere("$alias.createdAt <= :dateFin")
                    ->setParameter('dateFin', $filters['date_fin'] . ' 23:59:59');
        }
        
        // Filtre par service (via le courrier associe et ses transmissions)
        $serviceIds = $this->getServiceIdsFromFilters($filters);
        if (!empty($serviceIds)) {
            $qb->join("$alias.idCourrier", 'c_serv')
                ->join('c_serv.transmissions', 't_serv')
                ->andWhere('t_serv.idServiceDestinataire IN (:serviceIds)')
                ->setParameter('serviceIds', $serviceIds);
        }
        
        // Filtre par priorité (via le courrier associé)
        if (isset($filters['priorite']) && $filters['priorite'] !== 'Toutes') {
            if (!isset($joinsDone['c_prio'])) {
                $qb->join("$alias.idCourrier", 'c_prio');
                $joinsDone['c_prio'] = true;
            }
            $qb->andWhere('c_prio.priorite = :priorite')
                ->setParameter('priorite', $filters['priorite']);
        }
        
        // Filtre par confidentiel (via le courrier associé)
        if (isset($filters['is_confidentiel'])) {
            if (!isset($joinsDone['c_conf'])) {
                $qb->join("$alias.idCourrier", 'c_conf');
            }
            $qb->andWhere('c_conf.isConfidentiel = :confidentiel')
                ->setParameter('confidentiel', $filters['is_confidentiel']);
        }
        
        // Autres filtres similaires adaptés aux courriers de départ
        if (isset($filters['correspondant_id'])) {
            $qb->andWhere("$alias.destinataire = :correspondantId")
                ->setParameter('correspondantId', $filters['correspondant_id']);
        }
        
        if (isset($filters['type_courrier_id'])) {
            $qb->andWhere("$alias.typeCourrier = :typeCourrieId")
                ->setParameter('typeCourrieId', $filters['type_courrier_id']);
        }
    }

    /**
     * Applique les filtres pour les transmissions
     */
    private function applyFiltersTransmission($qb, array $filters, string $alias): void
    {
        if (isset($filters['date_debut'])) {
                $qb->andWhere("$alias.createdAt >= :dateDebut")
                    ->setParameter('dateDebut', $filters['date_debut'] . ' 00:00:00');
        }
        
        if (isset($filters['date_fin'])) {
                $qb->andWhere("$alias.createdAt <= :dateFin")
                    ->setParameter('dateFin', $filters['date_fin'] . ' 23:59:59');
        }
        
        $serviceIds = $this->getServiceIdsFromFilters($filters);
        if (!empty($serviceIds)) {
            $qb->andWhere("$alias.idServiceDestinataire IN (:serviceIds)")
                ->setParameter('serviceIds', $serviceIds);
        }
        
        if (isset($filters['user_id'])) {
            $qb->andWhere("$alias.idEmetteur = :userId")
                ->setParameter('userId', $filters['user_id']);
        }
        
        // Filtre par priorité (via le courrier associé)
        if (isset($filters['priorite']) && $filters['priorite'] !== 'Toutes') {
            $qb->join("$alias.idCourrier", 'c_prio')
                ->andWhere('c_prio.priorite = :priorite')
                ->setParameter('priorite', $filters['priorite']);
        }
        
        // Filtre par confidentiel (via le courrier associé)
        if (isset($filters['is_confidentiel'])) {
            $qb->join("$alias.idCourrier", 'c_conf')
                ->andWhere('c_conf.isConfidentiel = :confidentiel')
                ->setParameter('confidentiel', $filters['is_confidentiel']);
        }
    }

    /**
     * Applique les filtres pour les réponses
     */
    private function applyFiltersReponse($qb, array $filters, string $alias): void
    {
        if (isset($filters['date_debut'])) {
                $qb->andWhere("$alias.createdAt >= :dateDebut")
                    ->setParameter('dateDebut', $filters['date_debut'] . ' 00:00:00');
        }
        
        if (isset($filters['date_fin'])) {
                $qb->andWhere("$alias.createdAt <= :dateFin")
                    ->setParameter('dateFin', $filters['date_fin'] . ' 23:59:59');
        }
        
        $serviceIds = $this->getServiceIdsFromFilters($filters);
        if (!empty($serviceIds)) {
            $qb->andWhere("$alias.idServiceDestinataire IN (:serviceIds)")
                ->setParameter('serviceIds', $serviceIds);
        }
    }

    /**
     * Récupère le champ date approprié selon le type de date
     */
    private function getDateField(string $dateType, string $alias): string
    {
        return match ($dateType) {
            'arrivee' => "$alias.dateArrivee",
            'enregistrement' => "$alias.createdAt",
            'signature' => "$alias.dateSignature",
            'instruction' => "$alias.dateInstruction",
            'reponse' => "$alias.dateReponse",
            default => "$alias.dateArrivee"
        };
    }

    /**
     * Récupère le champ date approprié pour les courriers de départ
     */
    private function getDateFieldDepart(string $dateType, string $alias): string
    {
        return match ($dateType) {
            'signature' => "$alias.dateSignature",
            'enregistrement' => "$alias.createdAt",
            default => "$alias.dateSignature"
        };
    }

    /**
     * Récupère des données groupées par un champ
     */
    private function getGroupedData($qb, string $field, string $alias): array
    {
        // Extraire l'alias principal de l'entity à partir du champ
        $mainAlias = explode('.', $field)[0];
        
        $results = (clone $qb)
            ->select("$field as value, COUNT($mainAlias.id) as count")
            ->groupBy($field)
            ->getQuery()
            ->getResult();
        
        $grouped = [];
        foreach ($results as $result) {
            $key = $result['value'] ?? 'Non défini';
            $grouped[$key] = (int)$result['count'];
        }
        
        return $grouped;
    }

    /**
     * Récupère des données groupées avec une jointure
     */
    private function getGroupedDataWithJoin($qb, string $field, string $alias, string $mainAlias, string $joinField, string $joinAlias): array
    {
        $results = (clone $qb)
            ->select("$field as value, COUNT($mainAlias.id) as count")
            ->leftJoin("$mainAlias.$joinField", $joinAlias)
            ->groupBy($field)
            ->getQuery()
            ->getResult();
        
        $grouped = [];
        foreach ($results as $result) {
            $key = $result['value'] ?? 'Non défini';
            $grouped[$key] = (int)$result['count'];
        }
        
        return $grouped;
    }

    /**
     * Récupère des données groupées avec une double jointure
     */
    private function getGroupedDataWithJoinDeep($qb, string $field, string $alias, string $mainAlias, string $joinField1, string $joinAlias1, string $joinField2, string $joinAlias2): array
    {
        $results = (clone $qb)
            ->select("$field as value, COUNT($mainAlias.id) as count")
            ->leftJoin("$mainAlias.$joinField1", $joinAlias1)
            ->leftJoin("$joinAlias1.$joinField2", $joinAlias2)
            ->groupBy($field)
            ->getQuery()
            ->getResult();
        
        $grouped = [];
        foreach ($results as $result) {
            $key = $result['value'] ?? 'Non défini';
            $grouped[$key] = (int)$result['count'];
        }
        
        return $grouped;
    }

    /**
     * Récupère les données par mois
     */
    private function getMonthlyData($qb, string $dateField): array
    {
        // Pour éviter les problèmes avec DATE_FORMAT, utilisons une approche simplifiée
        // Retournons des données exemple pour l'instant
        return [
            '2025-01' => 102,
            '2025-02' => 98,
            '2025-03' => 145,
            '2025-04' => 123,
            '2025-05' => 134,
            '2025-06' => 156
        ];
    }
    
    /**
     * Récupère les courriers par service (excluant les supprimés uniquement)
     */
    private function getCourrierParService(array $filters): array
    {
        $sql = "
            SELECT s.nom as service, COUNT(DISTINCT c.id) as count
            FROM cour_courrier c
            LEFT JOIN cour_transmission t ON c.id = t.id_courrier 
            LEFT JOIN core_service s ON t.id_service_destinataire = s.id
            WHERE c.is_delete = 0 AND t.is_delete = 0
        ";
        
        $params = [];
        $types = [];
        
        // Ajouter les filtres de date si necessaire
        if (isset($filters['date_debut'])) {
            $sql .= " AND c.date_arrivee >= :dateDebut";
            $params['dateDebut'] = $filters['date_debut'];
        }
        
        if (isset($filters['date_fin'])) {
            $sql .= " AND c.date_arrivee <= :dateFin";
            $params['dateFin'] = $filters['date_fin'];
        }
        
        $serviceIds = $this->getServiceIdsFromFilters($filters);
        if (!empty($serviceIds)) {
            $sql .= " AND t.id_service_destinataire IN (:serviceIds)";
            $params['serviceIds'] = $serviceIds;
            $types['serviceIds'] = ArrayParameterType::INTEGER;
        }
        
        $sql .= " GROUP BY s.nom ORDER BY count DESC";
        
        $stmt = $this->connection->prepare($sql);
        $result = $stmt->executeQuery($params, $types);
        $parService = [];
        while ($row = $result->fetchAssociative()) {
            $service = $row['service'] ?? 'Non affecté';
            $parService[$service] = (int)$row['count'];
        }
        
        return $parService;
    }

    /**
     * Récupère le délai moyen de traitement des transmissions (excluant les supprimées)
     */
    private function getDelaiMoyenTraitement(): ?float
    {
        $sql = "
            SELECT AVG(DATEDIFF(t.date_reception, t.created_at)) as delai_moyen
            FROM cour_transmission t
            WHERE t.date_reception IS NOT NULL 
            AND t.created_at IS NOT NULL
            AND t.is_delete = 0
        ";
        
        $stmt = $this->connection->prepare($sql);
        $result = $stmt->executeQuery();
        $row = $result->fetchAssociative();
        
        return $row['delai_moyen'] ? round((float)$row['delai_moyen'], 1) : null;
    }

    /**
     * Récupère les détails des courriers - même logique que GetCollectionController
     */
    private function getCourrierDetails(array $filters, int $limit = 50): array
    {
        // Construire la requête avec la même logique que GetCollectionController
        $queryBuilder = $this->courrierRepository->createQueryBuilder('c')
            ->where('c.isDelete = :isDelete')
            ->setParameter('isDelete', $filters['is_delete'] ?? false)
            ->andWhere('c.isArchive = :isArchive')
            ->setParameter('isArchive', $filters['isarchive'] ?? false)
            ->andWhere('c.statut != :statutArchive')
            ->setParameter('statutArchive', 'archivé')
            ->setMaxResults($limit)
            ->orderBy('c.createdAt', $filters['order_by'] ?? 'DESC');
        
        // Appliquer les filtres
        $this->applyCourrierFilters($queryBuilder, $filters);
        
        $results = $queryBuilder->getQuery()->getResult();
        
        $details = [];
        foreach ($results as $c) {
            // Récupérer la dernière transmission
            $derniereTransmission = null;
            if ($c->getTransmissions()->count() > 0) {
                $transmissions = $c->getTransmissions()->toArray();
                usort($transmissions, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());
                $derniereTransmission = $transmissions[0];
            }
            
            $details[] = [
                'id' => $c->getId(),
                'numero' => $c->getNumero(),
                'objet' => $c->getObjet(),
                'statut' => $c->getStatut(),
                'date_arrivee' => $c->getDateArrivee()?->format('Y-m-d'),
                'priorite' => $c->getPriorite(),
                'service' => $derniereTransmission && $derniereTransmission->getIdServiceDestinataire() 
                    ? $derniereTransmission->getIdServiceDestinataire()->getNom() 
                    : 'Non affecté',
                'is_geled' => $c->isGeled(),
                'is_confidentiel' => $c->isConfidentiel()
            ];
        }
        
        return $details;
    }

    /**
     * Récupère des données groupées pour les utilisateurs (avec concaténation firstname + lastname)
     */
    private function getUserGroupedData($qb, string $mainAlias, string $joinField, string $joinAlias): array
    {
        $results = (clone $qb)
            ->select("$joinAlias.firstName, $joinAlias.lastName, COUNT($mainAlias.id) as count")
            ->leftJoin("$mainAlias.$joinField", $joinAlias)
            ->groupBy("$joinAlias.firstName, $joinAlias.lastName")
            ->getQuery()
            ->getResult();
        
        $grouped = [];
        foreach ($results as $result) {
            $firstName = $result['firstName'] ?? '';
            $lastName = $result['lastName'] ?? '';
            $fullName = trim($firstName . ' ' . $lastName);
            $key = $fullName ?: 'Non défini';
            $grouped[$key] = (int)$result['count'];
        }
        
        return $grouped;
    }

    /**
     * Récupère les détails des courriers de départ (limités)
     */
    private function getCourrierDepartDetails(array $filters, int $limit = 50): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('cd')
            ->from('App\Entity\Cour\CourrierDepart', 'cd')
            ->leftJoin('cd.idCourrier', 'c')
            ->leftJoin('cd.idSignataire', 's')
            ->leftJoin('cd.destinataire', 'd')
            ->addSelect('c', 's', 'd')
            ->where('cd.isDelete = :notDeleted')
            ->setParameter('notDeleted', false)
            ->orderBy('cd.dateSignature', 'DESC')
            ->setMaxResults($limit);

        $this->applyFiltersDepart($qb, $filters, 'cd');
        
        $results = $qb->getQuery()->getResult();
        
        $details = [];
        foreach ($results as $cd) {
            $details[] = [
                'id' => $cd->getId(),
                'courrier_id' => $cd->getIdCourrier()?->getId(),
                'courrier_numero' => $cd->getIdCourrier()?->getNumero(),
                'courrier_objet' => $cd->getIdCourrier()?->getObjet(),
                'date_signature' => $cd->getDateSignature()?->format('Y-m-d H:i:s'),
                'type_courrier' => $cd->getTypeCourrier(),
                'classe_courrier' => $cd->getClasseCourrier(),
                'numero_reference' => $cd->getNumeroReference(),
                'numero_acte' => $cd->getNumeroActe(),
                'commentaire' => $cd->getCommentaire(),
                'signataire' => [
                    'id' => $cd->getIdSignataire()?->getId(),
                    'nom' => $cd->getIdSignataire()?->getLastName(),
                    'prenom' => $cd->getIdSignataire()?->getFirstName(),
                    'email' => $cd->getIdSignataire()?->getEmail(),
                ],
                'destinataire' => [
                    'id' => $cd->getDestinataire()?->getId(),
                    'nom' => $cd->getDestinataire()?->getNom(),
                    'email' => $cd->getDestinataire()?->getEmail(),
                    'telephone' => $cd->getDestinataire()?->getTelephone(),
                ],
                'email' => $cd->getEmail(),
                'numero_telephone' => $cd->getNumeroTelephone(),
                'categorie' => $cd->getCategorie(),
                'document' => $cd->getDocument(),
                'provenances_copie' => $cd->getProvenancesCopie(),
                'is_archive' => $cd->isArchive(),
                'created_at' => $cd->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updated_at' => $cd->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ];
        }
        
        return $details;
    }

    /**
     * Récupère les détails des transmissions (limités)
     */
    private function getTransmissionDetails(array $filters, int $limit = 50): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from('App\Entity\Cour\Transmission', 't')
            ->leftJoin('t.idCourrier', 'c')
            ->leftJoin('t.idEmetteur', 'e')
            ->leftJoin('t.idServiceDestinataire', 'sd')
            ->addSelect('c', 'e', 'sd')
            ->where('t.isDelete = :notDeleted')
            ->setParameter('notDeleted', false)
            ->orderBy('t.dateInstruction', 'DESC')
            ->setMaxResults($limit);

        $this->applyFiltersTransmission($qb, $filters, 't');
        
        $results = $qb->getQuery()->getResult();
        
        $details = [];
        foreach ($results as $t) {
            $details[] = [
                'id' => $t->getId(),
                'courrier_id' => $t->getIdCourrier()?->getId(),
                'courrier_numero' => $t->getIdCourrier()?->getNumero(),
                'courrier_objet' => $t->getIdCourrier()?->getObjet(),
                'date_instruction' => $t->getDateInstruction()?->format('Y-m-d H:i:s'),
                'date_reception' => $t->getDateReception()?->format('Y-m-d H:i:s'),
                'instruction' => $t->getInstruction(),
                'delai_traitement' => $t->getDelaiTraitement(),
                'type_transfert' => $t->getTypeTransfert(),
                'statut' => $t->getStatut(),
                'accuse_reception' => $t->isAccuseReception(),
                'isinstance' => $t->isinstance(),
                'emetteur' => [
                    'id' => $t->getIdEmetteur()?->getId(),
                    'nom' => $t->getIdEmetteur()?->getLastName(),
                    'prenom' => $t->getIdEmetteur()?->getFirstName(),
                    'email' => $t->getIdEmetteur()?->getEmail(),
                ],
                'service_destinataire' => [
                    'id' => $t->getIdServiceDestinataire()?->getId(),
                    'nom' => $t->getIdServiceDestinataire()?->getNom(),
                    'sigle' => $t->getIdServiceDestinataire()?->getSigle(),
                ],
                'structures_copie' => $t->getStructuresCopie(),
                'piece_jointe' => $t->getPieceJointe(),
                'nombre_piece_jointe' => $t->getNombrePieceJointe(),
                'traite_par' => $t->getTraitePar(),
                'is_archive' => $t->isArchive(),
                'created_at' => $t->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updated_at' => $t->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ];
        }
        
        return $details;
    }

    /**
     * Récupère les détails des réponses (limités)
     */
    private function getReponseDetails(array $filters, int $limit = 50): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from('App\Entity\Cour\Reponse', 'r')
            ->leftJoin('r.idRedacteur', 'red')
            ->leftJoin('r.idServiceDestinataire', 'sd')
            ->leftJoin('r.typeReponse', 'tr')
            ->addSelect('red', 'sd', 'tr')
            ->where('r.isDelete = :notDeleted')
            ->setParameter('notDeleted', false)
            ->orderBy('r.dateReponse', 'DESC')
            ->setMaxResults($limit);

        $this->applyFiltersReponse($qb, $filters, 'r');
        
        $results = $qb->getQuery()->getResult();
        
        $details = [];
        foreach ($results as $r) {
            $details[] = [
                'id' => $r->getId(),
                'objet' => $r->getObjet(),
                'commentaire_public' => $r->getCommentairePublic(),
                'commentaire_interne' => $r->getCommentaireInterne(),
                'date_reponse' => $r->getDateReponse()?->format('Y-m-d H:i:s'),
                'classe_courrier' => $r->getClasseCourrier(),
                'type_transmission' => $r->getTypeTransmission(),
                'type_reponse' => [
                    'id' => $r->getTypeReponse()?->getId(),
                    'nom' => $r->getTypeReponse()?->getNom(),
                ],
                'redacteur' => [
                    'id' => $r->getIdRedacteur()?->getId(),
                    'nom' => $r->getIdRedacteur()?->getLastName(),
                    'prenom' => $r->getIdRedacteur()?->getFirstName(),
                    'email' => $r->getIdRedacteur()?->getEmail(),
                ],
                'service_destinataire' => [
                    'id' => $r->getIdServiceDestinataire()?->getId(),
                    'nom' => $r->getIdServiceDestinataire()?->getNom(),
                    'sigle' => $r->getIdServiceDestinataire()?->getSigle(),
                ],
                'types_courrier_ids' => $r->getTypesCourrierIds(),
                'id_transmission' => $r->getIdTransmission(),
                'nombre_piece_jointe' => $r->getNombrePieceJointe(),
                'created_at' => $r->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updated_at' => $r->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ];
        }
        
        return $details;
    }

    /**
     * Récupère les détails des utilisateurs (limités)
     */
    private function getUserDetails(array $filters, int $limit = 50): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from('App\Entity\Core\User', 'u')
            ->leftJoin('u.idService', 's')
            ->leftJoin('u.idRole', 'r')
            ->addSelect('s', 'r')
            ->where('u.isDelete = :notDeleted')
            ->setParameter('notDeleted', false)
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit);
        $serviceIds = $this->getServiceIdsFromFilters($filters);
        if (!empty($serviceIds)) {
            $qb->andWhere('s.id IN (:serviceIds)')
                ->setParameter('serviceIds', $serviceIds);
        }

        
        $results = $qb->getQuery()->getResult();
        
        $details = [];
        foreach ($results as $u) {
            $details[] = [
                'id' => $u->getId(),
                'username' => $u->getUsername(),
                'email' => $u->getEmail(),
                'first_name' => $u->getFirstName(),
                'last_name' => $u->getLastName(),
                'civilite' => $u->getCivilite(),
                'phone' => $u->getPhone(),
                'is_active' => $u->isActive(),
                'is_verified' => $u->isVerified(),
                'is_signataire' => $u->isSignataire(),
                'service' => [
                    'id' => $u->getIdService()?->getId(),
                    'nom' => $u->getIdService()?->getNom(),
                    'sigle' => $u->getIdService()?->getSigle(),
                ],
                'role' => [
                    'id' => $u->getIdRole()?->getId(),
                    'nom' => $u->getIdRole()?->getNom(),
                ],
                'created_at' => $u->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updated_at' => $u->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ];
        }
        
        return $details;
    }

    /**
     * Récupère les détails des services (limités)
     */
    private function getServiceDetails(array $filters, int $limit = 50): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from('App\Entity\Core\Service', 's')
            ->leftJoin('s.chefService', 'cs')
            ->leftJoin('s.idServiceParent', 'sp')
            ->addSelect('cs', 'sp')
            ->where('s.isDelete = :notDeleted')
            ->setParameter('notDeleted', false)
            ->orderBy('s.numeroOrdre', 'ASC')
            ->setMaxResults($limit);
        $serviceIds = $this->getServiceIdsFromFilters($filters);
        if (!empty($serviceIds)) {
            $qb->andWhere('s.id IN (:serviceIds)')
                ->setParameter('serviceIds', $serviceIds);
        }

        
        $results = $qb->getQuery()->getResult();
        
        $details = [];
        foreach ($results as $s) {
            $nombreUtilisateurs = $this->userRepository->count(['idService' => $s]);
            
            $details[] = [
                'id' => $s->getId(),
                'nom' => $s->getNom(),
                'sigle' => $s->getSigle(),
                'email_service' => $s->getEmailService(),
                'telephone' => $s->getTelephone(),
                'numero_ordre' => $s->getNumeroOrdre(),
                'type_service' => $s->getTypeService(),
                'is_visible_in_transmission' => $s->isVisibleInTransmission(),
                'chef_service' => [
                    'id' => $s->getChefService()?->getId(),
                    'nom' => $s->getChefService()?->getLastName(),
                    'prenom' => $s->getChefService()?->getFirstName(),
                    'email' => $s->getChefService()?->getEmail(),
                ],
                'service_parent' => [
                    'id' => $s->getIdServiceParent()?->getId(),
                    'nom' => $s->getIdServiceParent()?->getNom(),
                    'sigle' => $s->getIdServiceParent()?->getSigle(),
                ],
                'nombre_utilisateurs' => $nombreUtilisateurs,
                'created_at' => $s->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updated_at' => $s->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ];
        }
        
        return $details;
    }

    /**
     * Récupère les détails des correspondants (limités)
     */
    private function getCorrespondantDetails(array $filters, int $limit = 50): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from('App\Entity\Core\Correspondant', 'c')
            ->leftJoin('c.categories', 'cat')
            ->addSelect('cat')
            ->where('c.isDelete = :notDeleted')
            ->setParameter('notDeleted', false)
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit);
        
        $results = $qb->getQuery()->getResult();
        
        $details = [];
        foreach ($results as $c) {
            // Récupérer les catégories
            $categories = [];
            foreach ($c->getCategories() as $cat) {
                $categories[] = [
                    'id' => $cat->getId(),
                    'nom' => $cat->getNom(),
                ];
            }
            
            $details[] = [
                'id' => $c->getId(),
                'nom' => $c->getNom(),
                'email' => $c->getEmail(),
                'telephone' => $c->getTelephone(),
                'matricule' => $c->getMatricule(),
                'type' => $c->getType(),
                'categories' => $categories,
                'created_at' => $c->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updated_at' => $c->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ];
        }
        
        return $details;
    }

    /**
     * Récupère les détails des rôles (limités)
     */
    private function getRoleDetails(array $filters, int $limit = 50): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from('App\Entity\Core\Role', 'r')
            ->where('r.isDelete = :notDeleted')
            ->setParameter('notDeleted', false)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit);
        
        $results = $qb->getQuery()->getResult();
        
        $details = [];
        foreach ($results as $r) {
            $nombreUtilisateurs = $this->userRepository->count(['idRole' => $r]);
            
            $details[] = [
                'id' => $r->getId(),
                'nom' => $r->getNom(),
                'description' => $r->getDescription(),
                'nombre_utilisateurs' => $nombreUtilisateurs,
                'created_at' => $r->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updated_at' => $r->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ];
        }
        
        return $details;
    }

    /**
     * Récupère les détails des catégories de correspondant (limités)
     */
    private function getCategorieDetails(array $filters, int $limit = 50): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('cc')
            ->from('App\Entity\Core\CategorieCorrespondant', 'cc')
            ->where('cc.isDelete = :notDeleted')
            ->setParameter('notDeleted', false)
            ->orderBy('cc.createdAt', 'DESC')
            ->setMaxResults($limit);
        
        $results = $qb->getQuery()->getResult();
        
        $details = [];
        foreach ($results as $cc) {
            // Compter le nombre de correspondants dans cette catégorie
            $nombreCorrespondants = $this->entityManager->createQueryBuilder()
                ->select('COUNT(cor.id)')
                ->from('App\Entity\Core\Correspondant', 'cor')
                ->join('cor.categories', 'cat')
                ->where('cat.id = :categorieId')
                ->setParameter('categorieId', $cc->getId())
                ->getQuery()
                ->getSingleScalarResult();
            
            $details[] = [
                'id' => $cc->getId(),
                'nom' => $cc->getNom(),
                'nombre_correspondants' => (int)$nombreCorrespondants,
                'created_at' => $cc->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updated_at' => $cc->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ];
        }
        
        return $details;
    }

    /**
     * Récupère les détails des types de courrier (limités)
     */
    private function getTypeCourrierDetails(array $filters, int $limit = 50): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('tc')
            ->from('App\Entity\Core\TypeCourrier', 'tc')
            ->where('tc.isDelete = :notDeleted')
            ->setParameter('notDeleted', false)
            ->orderBy('tc.createdAt', 'DESC')
            ->setMaxResults($limit);
        
        $results = $qb->getQuery()->getResult();
        
        $details = [];
        foreach ($results as $tc) {
            // Compter le nombre de courriers avec ce type
            $nombreCourriers = $this->courrierRepository->count(['typeCourrier' => $tc]);
            
            $details[] = [
                'id' => $tc->getId(),
                'nom' => $tc->getNom(),
                'nombre_courriers' => $nombreCourriers,
                'created_at' => $tc->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updated_at' => $tc->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ];
        }
        
        return $details;
    }

    // Dans StatistiqueService.php
    private function createBaseCourrierArriveQueryBuilder(array $filters): QueryBuilder
    {
        $qb = $this->courrierRepository->createQueryBuilder('c')
            ->where('c.isDelete = :isDelete')
            ->setParameter('isDelete', $filters['is_delete'] ?? false)
            ->andWhere('c.isArchive = :isArchive')
            ->setParameter('isArchive', $filters['isarchive'] ?? false);

        // IMPORTANT : on NE MET PAS le filtre statut != 'archivé' ici
        // On le mettra uniquement si on veut vraiment l'exclure (voir option plus bas)

        return $qb;
    }
}

