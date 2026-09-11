<?php

namespace App\Repository\Core;

use App\Entity\Core\User;
use App\Exception\InvalidArgumentException;
use App\Exception\EntityNotFoundException;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, User::class);
    }

    public function findOneByUser(int $userId): ?array
    {
        $entity = $this->createQueryBuilder('u')
            ->andWhere('u.id = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult()
        ;

        if (!$entity) {
            throw new EntityNotFoundException();
        }

        return $entity;
    }

    public function findAllData(Request $request, array $searchFields = []): array
    {
        // Récupère les paramètres de pagination et de filtrage depuis la requête
        $compteId = (int) $request->query->get('compte', null);
        $type = (string) $request->query->get('type', null);
        $page = max((int) $request->query->get('page', 1), 1);  // Page actuelle (minimum 1)
        $limit = (int) $request->query->get('limit', 10);       // Nombre d'éléments par page
        $search = (string) $request->query->get('search');           // Recherche par mot-clé
        $orderBy = (string) $request->query->get('order_by', 'DESC');    // Tri (ASC ou DESC)
        $isDelete = $request->query->get('is_delete', 'false'); // Filtrage par suppression
        $startDate = (string) $request->query->get('start_date');        // Filtrage par date de début
        $endDate = (string) $request->query->get('end_date');            // Filtrage par date de fin
        $year = (string) $request->query->get('year');            // Filtrage par date de fin
        $date = (string) $request->query->get('date');            // Filtrage par date
        $isVerified = $request->query->get('is_verified', 'true'); // Filtrage par suppression

        // Si "limit" est égal à 0, désactive la pagination
        if ($limit === 0) {
            $limit = null; // Pas de limite
        }

        // Vérification des formats des dates
        if ($startDate && !$this->isValidDateFormat($startDate)) {
            throw new InvalidArgumentException();
        }
        if ($endDate && !$this->isValidDateFormat($endDate)) {
            throw new InvalidArgumentException();
        }
        if ($date && !$this->isValidDateFormat($date)) {
            throw new InvalidArgumentException();
        }

        $queryBuilder = $this->createQueryBuilder('u')


            ->select('u', 'MIN(uc.compte) as Compte', 'MIN(uc.codeComptes) as codeCompte', 'COALESCE(c.codeCpt, 0) as codeCpt')
            ->leftJoin('u.UserComptes', 'uc')
            ->leftJoin('uc.compte', 'c') // Jointure avec la table des utilisateurs
            ->groupBy('u.id')  // Groupement par utilisateur


            // ->andWhere('u.id = :compteId')
            // ->setParameter('tierId', $compteId)
            // ->getQuery()
            // ->getResult()
        ;

        // Recherche par mot-clé sur les champs spécifiés
        if ($search && $searchFields) {
            foreach ($searchFields as $field) {
                $queryBuilder->orWhere("u.$field LIKE :search");
            }
            $queryBuilder->setParameter('search', "%$search%");
        }

        // Filtrage par date de début (start_date)
        if ($startDate) {
            $queryBuilder->andWhere('u.createdAt >= :startDate');
            $queryBuilder->setParameter('startDate', new \DateTime($startDate));
        }

        // Filtrage par date de fin (end_date)
        if ($endDate) {
            $queryBuilder->andWhere('u.createdAt <= :endDate');
            $queryBuilder->setParameter('endDate', new \DateTime($endDate));
        }

        // Filtrage par date (date)
        if ($date) {
            $date1 = $date . ' 00:00:01';
            $queryBuilder->andWhere('u.createdAt >= :date1');
            $queryBuilder->setParameter('date1', new \DateTime($date1));
            $date2 = $date . ' 23:59:59';
            $queryBuilder->andWhere('u.createdAt <= :date2');
            $queryBuilder->setParameter('date2', new \DateTime($date2));
        }

        if ($year) {
            $startYear = new \DateTime("$year-01-01 00:00:00");
            $endYear = new \DateTime("$year-12-31 23:59:59");

            $queryBuilder
                ->andWhere('u.createdAt BETWEEN :startYear AND :endYear')
                ->setParameter('startYear', $startYear)
                ->setParameter('endYear', $endYear);
        }

        // Filtrage par isDelete
        if ($isDelete == 'false') {
            $queryBuilder->andWhere('u.isDelete = :isDelete')
                ->setParameter('isDelete', false);
        } elseif ($isDelete == 'true') {
            $queryBuilder->andWhere('u.isDelete = :isDelete')
                ->setParameter('isDelete', true);
        }

        // Filtrage par isVerified
        if ($isVerified == 'true') {
            $queryBuilder->andWhere('u.isVerified = :isVerified')
                ->setParameter('isVerified', true);
        } elseif ($isVerified == 'false') {
            $queryBuilder->andWhere('u.isVerified = :isVerified')
                ->setParameter('isVerified', false);
        }

        if ($compteId) {
            $queryBuilder->andWhere('c.codeCpt = :compte')
                ->setParameter('compte', $compteId);
        }
        $idcomptelook = NULL;
        if ($type) {
            if ($type == "Fournisseurs") {
                $idcomptelook = "Fournisseurs";
                $comptelook = [401000, 402000, 403000];
            } elseif ($type == "Clients") {
                $idcomptelook = "Clients";
                $comptelook = [411000];
            } elseif ($type == "Personnels") {
                $idcomptelook = "Personnels";
                $comptelook = [422000];
            } else {
                $comptelook = []; // Valeur par défaut si aucun type ne correspond
            }

            if (!empty($comptelook)) {
                $queryBuilder->andWhere('c.codeCpt IN (:comptelook)')
                    ->setParameter('comptelook', $comptelook);
            }
            if ($type == "Autres") {
                $liste = [401000, 402000, 403000, 411000, 422000];
                $queryBuilder->andWhere('c.codeCpt NOT IN (:liste)')
                    ->setParameter('liste', $liste);

            }
        }

        // Clonage du QueryBuilder pour calculer le nombre total d'éléments sans pagination
        //$totalItems = (clone $queryBuilder)->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();
        $totalItems = count($queryBuilder->getQuery()->getResult());
        // Gestion de la pagination
        if ($limit !== null) {
            // Récupération des éléments paginés
            $items = $queryBuilder
                ->setFirstResult(($page - 1) * $limit)  // Index de départ
                ->setMaxResults($limit)                 // Nombre maximum d'éléments
                ->orderBy('u.createdAt', $orderBy)      // Tri par date de création
                ->getQuery()
                ->getResult()
            ;
        } else {
            // Récupération sans pagination (tous les éléments)
            $items = $queryBuilder
                ->orderBy('u.createdAt', $orderBy)
                ->getQuery()
                ->getResult()
            ;
        }

        // Calcul du nombre total de pages (si la pagination est activée)
        $totalPages = $limit !== null ? (int) ceil($totalItems / $limit) : 1;

        // return $data;
        return [
            'items' => $items,              // Les entités récupérées
            'total_items' => $totalItems,   // Les entités récupérées
            'current_page' => $page,        // Page actuelle
            'total_pages' => $totalPages,   // Nombre total de pages
            'compteTier' => $this->compteRepository->findAllComptesTiersWithNumberOfUsers($idcomptelook),
        ];
    }

    private function parseBooleanFilter(?string $value, string $fieldName): ?bool
    {
        if ($value === null) {
            return null;
        }

        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        throw new InvalidArgumentException();
    }

    public function countDeleteUser(): int
    {
        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.isDelete = :is_delete')
            ->setParameter('is_delete', 1)
            ->getQuery()
            ->getSingleScalarResult();
    }


    public function findAllUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->select('u.id', 'COALESCE(u.lastName, \'N/A\') as lastName') // Utilise 'N/A' si lastName est null
            ->where('u.isDelete = :is_delete') // Filtre sur les utilisateurs non supprimés
            ->setParameter('is_delete', 0)
            ->getQuery()
            ->getResult(); // Retourne un tableau d'utilisateurs
    }


    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }


    //    /**
//     * @return User[] Returns an array of User objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('u.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

    //    public function findOneBySomeField($value): ?User
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
