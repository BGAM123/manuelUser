<?php

namespace App\Service\Core;

use Doctrine\ORM\EntityManagerInterface;
use App\Exception\EntityNotFoundException;
use App\Exception\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Service CrudService
 *
 * Service utilitaire pour gérer les opérations CRUD sur des entités dans l'application.
 * Ce service fournit des méthodes pour créer, lire, mettre à jour et supprimer des enregistrements.
 */
class CrudService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator
    ) {
    }

    /**
     * Récupère une entité spécifique par son identifiant.
     *
     * @param string $entityClass Le nom complet de la classe de l'entité (FQCN).
     * @param int    $id          L'identifiant de l'entité.
     *
     * @return object|null L'entité trouvée ou null si elle n'existe pas.
     * 
     * @throws NotFoundHttpException Si l'entité n'est pas trouvée
     */
    public function getEntity(string $entityClass, int $id): object
    {
        /* Utilise le gestionnaire d'entités Doctrine ($this->em) pour récupérer le repository de l'entité spécifiée
            et rechercher une entité par son identifiant ($id).
            Retourne l'entité ou null
        */
        $entity = $this->em->getRepository($entityClass)->find($id);

        if (!$entity) {
            throw new EntityNotFoundException();
        }

        return $entity;
    }

    /**
     * Récupère une collection d'entités avec prise en charge des filtres, de la recherche et de la pagination.
     *
     * @param string  $entityClass   La classe de l'entité à interroger.
     * @param Request $request       La requête HTTP contenant les paramètres de recherche et de pagination.
     * @param array   $searchFields  Les champs à inclure dans la recherche par mot-clé.
     *
     * @return array Un tableau contenant les éléments récupérés, le nombre total d'éléments, la page actuelle, et le nombre total de pages.
     */
    public function getCollectionEntity(string $entityClass, Request $request, array $searchFields = []): array
    {
        // Récupère les paramètres de pagination et de filtrage depuis la requête
        $page = max((int) $request->query->get('page', 1), 1);  // Page actuelle (minimum 1)
        $limit = (int) $request->query->get('limit', 10);       // Nombre d'éléments par page
        $search = (string) $request->query->get('search');           // Recherche par mot-clé
        $startDate = (string) $request->query->get('start_date');        // Filtrage par date de début
        $endDate = (string) $request->query->get('end_date');            // Filtrage par date de fin
        $year = (string) $request->query->get('year');            // Filtrage par date de fin
        $date = (string) $request->query->get('date');            // Filtrage par date
        $userId = (int) $request->query->get('user_id');              // Filtrage par utilisateur
        $orderBy = (string) $request->query->get('order_by', 'DESC');    // Tri (ASC ou DESC)
        $isDelete = $this->parseBooleanFilter($request->query->get('is_delete'), 'is_delete'); // Filtrage par suppression
        $isVerified = $this->parseBooleanFilter($request->query->get('is_verified'), 'is_verified'); // Filtrage par vérification

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

        // Crée un QueryBuilder pour construire dynamiquement la requête
        $queryBuilder = $this->em->getRepository($entityClass)->createQueryBuilder('e');

        // Recherche par mot-clé sur les champs spécifiés
        if ($search && $searchFields) {
            foreach ($searchFields as $field) {
                $queryBuilder->orWhere("e.$field LIKE :search");
            }
            $queryBuilder->setParameter('search', "%$search%");
        }

        // Filtrage par date de début (start_date)
        if ($startDate) {
            $queryBuilder->andWhere('e.createdAt >= :startDate');
            $queryBuilder->setParameter('startDate', new \DateTime($startDate));
        }

        // Filtrage par date de fin (end_date)
        if ($endDate) {
            $queryBuilder->andWhere('e.createdAt <= :endDate');
            $queryBuilder->setParameter('endDate', new \DateTime($endDate));
        }

        // Filtrage par date (date)
        if ($date) {
            $date1 = $date . ' 00:00:01';
            $queryBuilder->andWhere('e.createdAt >= :date1');
            $queryBuilder->setParameter('date1', new \DateTime($date1));
            $date2 = $date . ' 23:59:59';
            $queryBuilder->andWhere('e.createdAt <= :date2');
            $queryBuilder->setParameter('date2', new \DateTime($date2));
        }

        // Filtrage par utilisateur (user_id)
        if ($userId) {
            $queryBuilder->andWhere('e.user = :userId');
            $queryBuilder->setParameter('userId', $userId);
        }

        // Filtrage par isDelete
        if ($isDelete !== null) {
            $queryBuilder->andWhere('e.isDelete = :isDelete');
            $queryBuilder->setParameter('isDelete', $isDelete);
        }

        if ($year) {
            $startYear = new \DateTime("$year-01-01 00:00:00");
            $endYear = new \DateTime("$year-12-31 23:59:59");

            $queryBuilder
                ->andWhere('e.createdAt BETWEEN :startYear AND :endYear')
                ->setParameter('startYear', $startYear)
                ->setParameter('endYear', $endYear);
        }

        // Filtrage par isVerified
        if ($isVerified !== null) {
            $queryBuilder->andWhere('e.isVerified = :isVerified');
            $queryBuilder->setParameter('isVerified', $isVerified);
        }

        // Clonage du QueryBuilder pour calculer le nombre total d'éléments sans pagination
        $totalItems = (clone $queryBuilder)->select('COUNT(e.id)')->getQuery()->getSingleScalarResult();

        // Gestion de la pagination
        if ($limit !== null) {
            // Récupération des éléments paginés
            $items = $queryBuilder
                ->setFirstResult(($page - 1) * $limit)  // Index de départ
                ->setMaxResults($limit)                 // Nombre maximum d'éléments
                ->orderBy('e.createdAt', $orderBy)      // Tri par date de création
                ->getQuery()
                ->getResult();
        } else {
            // Récupération sans pagination (tous les éléments)
            $items = $queryBuilder
                ->orderBy('e.createdAt', $orderBy)
                ->getQuery()
                ->getResult();
        }

        // Calcul du nombre total de pages (si la pagination est activée)
        $totalPages = $limit !== null ? (int) ceil($totalItems / $limit) : 1;

        // Retourne les résultats au format tableau
        return [
            'items' => $items,              // Les entités récupérées
            'total_items' => $totalItems,   // Les entités récupérées
            'current_page' => $page,        // Page actuelle
            'total_pages' => $totalPages,   // Nombre total de pages
        ];
    }

    /**
     * Crée une entité avec les données fournies.
     *
     * @param object $entity L'entité à persister.
     * @param array  $data   Les données à utiliser pour peupler l'entité.
     *
     * @return object L'entité persistée après l'opération.
     */
    public function postEntity(object $entity, array $data): object
    {
        // Remplir l'entité avec les données
        $this->populateEntity($entity, $data, false);

        // Valider l'entité
        $errors = $this->validator->validate($entity);

        // Si des erreurs de validation sont présentes, lever une exception
        if (count($errors) > 0) {
            throw new BadRequestHttpException((string) $errors);
        }

        // Persiste l'entité dans la base de données
        $this->em->persist($entity);
        $this->em->flush();

        // Retourne l'entité après l'opération
        return $entity;
    }

    /**
     * Met à jour partiellement une entité existante avec les données fournies.
     *
     * @param object $entity L'entité à mettre à jour.
     * @param array  $data   Les données partielles à utiliser pour mettre à jour l'entité.
     *
     * @return object L'entité mise à jour après l'opération.
     */
    public function patchEntity(object $entity, array $data): object
    {
        // Met à jour l'entité avec les données partielles
        $this->populateEntity($entity, $data, true);

        // Valider l'entité
        $errors = $this->validator->validate($entity);

        // Si des erreurs de validation sont présentes, lever une exception
        if (count($errors) > 0) {
            throw new BadRequestHttpException((string) $errors);
        }

        // Applique les modifications à la base de données
        $this->em->flush();

        // Retourne l'entité après l'opération
        return $entity;
    }

    /**
     * Supprime une entité spécifique par son identifiant.
     *
     * @param string $entityClass Le nom complet de la classe de l'entité (FQCN).
     * @param int    $id          L'identifiant de l'entité à supprimer.
     *
     * @return bool Retourne `true` si l'entité a été supprimée avec succès.
     */
    public function deleteEntity(string $entityClass, int $id): bool
    {
        // Récupère l'entité par son ID
        $entity = $this->getEntity($entityClass, $id);

        // Supprime l'entité
        $this->em->remove($entity);
        $this->em->flush();

        // Renvoie vrai si l'entité a été supprimée
        return true;
    }

    /**
     * Vérifie si une date est dans un format valide.
     *
     * @param string|null $date La date à valider.
     *
     * @return bool Retourne true si la date est valide, sinon false.
     */
    private function isValidDateFormat(?string $date): bool
    {
        if ($date === null) {
            return false;
        }

        // Regex pour valider le format YYYY-MM-DD ou YYYY-MM-DD HH:MM:SS
        $regex = '/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/';
        return preg_match($regex, $date) === 1;
    }

    /**
     * Valide et convertit une chaîne de caractères en un booléen ou retourne `null` si la valeur est absente.
     *
     * @param string|null $value      La valeur à valider et convertir (attendue : 'true', 'false', ou NULL).
     * @param string      $fieldName  Le nom du champ en cours de traitement (utilisé pour les exceptions).
     *
     * @return bool|null  Retourne `true`, `false`, ou `null` si la valeur est valide.
     *
     * @throws InvalidArgumentException Si la valeur est invalide (ni 'true', ni 'false', ni NULL).
     */
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

    /**
     * Remplit une entité avec les données fournies.
     *
     * @param object $entity   L'entité à peupler.
     * @param array  $data     Les données à utiliser pour remplir l'entité (tableau associatif).
     * @param bool   $isPatch  Indique si l'opération est une mise à jour partielle (PATCH).
     *
     * @return void
     */
    private function populateEntity(object $entity, array $data, ?bool $isPatch = false): void
    {
        foreach ($data as $property => $value) {
            // ✅ Convertir les chaînes vides en null
            if ($value === '') {
                $value = null;
            }

            // Traitement spécifique : hacher le mot de passe
            if ($property === 'password' && $value !== null) {
                $value = $this->passwordHasher->hashPassword($entity, $value);
            }

            // Génère le nom du setter correspondant
            $setter = 'set' . ucfirst($property);

            // Vérifie si le setter existe et applique les règles de PATCH
            if (method_exists($entity, $setter) && ($isPatch && $value !== null || !$isPatch)) {
                $entity->$setter($value);
            }
        }
    }
}
