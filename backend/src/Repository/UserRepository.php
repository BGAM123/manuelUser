<?php

// src/Repository/UserRepository.php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Service;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Fetches a paginated list of users.
     * Uses Doctrine Result Cache to optimize database performance.
     *
     * @return array<int, User>
     */
    public function findPaginatedUsers(int $page, int $limit, ?bool $isActive = null, ?int $serviceId = null, ?bool $twoFactorEnabled = null, ?bool $isDelete = false): array
    {
        $queryBuilder = $this->createQueryBuilder('u')
            ->leftJoin('u.service', 's')
            ->addSelect('s')
            ->andWhere('u.isDelete = :isDelete')
            ->setParameter('isDelete', false);

        if (null !== $isActive) {
            $queryBuilder->andWhere('u.isActive = :isActive')
                ->setParameter('isActive', $isActive);
        }

        // ✅ Filtre is_delete (par défaut: false)
        if (null !== $isDelete) {
            $queryBuilder->andWhere('u.isDelete = :isDelete')
                ->setParameter('isDelete', $isDelete);
        } else {
            // Par défaut, on exclut les utilisateurs supprimés
            $queryBuilder->andWhere('u.isDelete = :isDelete')
                ->setParameter('isDelete', false);
        }

        if (null !== $serviceId) {
            $queryBuilder->andWhere('s.id = :serviceId')
                ->setParameter('serviceId', $serviceId);
        }

        if (null !== $twoFactorEnabled) {
            $queryBuilder->andWhere('u.twoFactorEnabled = :twoFactorEnabled')
                ->setParameter('twoFactorEnabled', $twoFactorEnabled);
        }

        /** @var array<int, User> $result */
        $result = $queryBuilder
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
 * Génère un nouveau matricule au format ECI suivi d'un nombre incrémenté
 * Exemple: ECI001, ECI002, ECI003, ...
 *
 * @return string
 */
public function generateMatricule(): string
{
    // Récupérer le dernier matricule utilisé
    $result = $this->createQueryBuilder('u')
        ->select('u.matricule')
        ->andWhere('u.matricule IS NOT NULL')
        ->andWhere('u.matricule != :empty')
        ->setParameter('empty', '')
        ->andWhere('u.matricule LIKE :prefix')
        ->setParameter('prefix', 'ECI%')
        ->orderBy('u.id', 'DESC')
        ->setMaxResults(1)
        ->getQuery()
        ->getResult(); // Utiliser getResult() au lieu de getOneOrNullResult()

    // Si aucun matricule n'existe, commencer à ECI001
    if (empty($result)) {
        return 'ECI001';
    }

    // Le résultat est un tableau de tableaux, accéder au premier élément
    $lastMatricule = $result[0]['matricule'] ?? null;
    
    if (empty($lastMatricule)) {
        return 'ECI001';
    }

    // Si le matricule n'est pas au format ECIXXX, commencer à ECI001
    if (!preg_match('/^ECI(\d{3})$/', $lastMatricule, $matches)) {
        return 'ECI001';
    }
    
    $number = (int) $matches[1];
    
    // Incrémenter et formater avec 3 chiffres
    $newNumber = $number + 1;
    $formattedNumber = str_pad($newNumber, 3, '0', STR_PAD_LEFT);
    
    return 'ECI' . $formattedNumber;
}
        /**
     * Vérifie si un matricule existe déjà
     *
     * @param string $matricule
     * @return bool
     */
    public function isMatriculeExists(string $matricule): bool
    {
        $result = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.matricule = :matricule')
            ->setParameter('matricule', $matricule)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result > 0;
    }


    /**
     * Computes total number of user records.
     *
     * @return int
     */
    public function countAllUsers(?bool $isActive = null, ?int $serviceId = null, ?bool $twoFactorEnabled = null, ?bool $isDelete = false): int
    {
        $queryBuilder = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.isDelete = :isDelete')
            ->setParameter('isDelete', false);

        if (null !== $isActive) {
            $queryBuilder->andWhere('u.isActive = :isActive')
                ->setParameter('isActive', $isActive);
        }

        // ✅ Filtre is_delete (par défaut: false)
        if (null !== $isDelete) {
            $queryBuilder->andWhere('u.isDelete = :isDelete')
                ->setParameter('isDelete', $isDelete);
        } else {
            // Par défaut, on exclut les utilisateurs supprimés
            $queryBuilder->andWhere('u.isDelete = :isDelete')
                ->setParameter('isDelete', false);
        }

        if (null !== $serviceId) {
            $queryBuilder->leftJoin('u.service', 's')
                ->andWhere('s.id = :serviceId')
                ->setParameter('serviceId', $serviceId);
        }

        if (null !== $twoFactorEnabled) {
            $queryBuilder->andWhere('u.twoFactorEnabled = :twoFactorEnabled')
                ->setParameter('twoFactorEnabled', $twoFactorEnabled);
        }

        return (int) $queryBuilder
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Convenience wrapper to retrieve a single user by id.
     */
    public function getUserById(int $id): ?User
    {
        return $this->find($id);
    }
    

    /**
     * Validate service_id assignment: only "poste" type allowed, not "service".
     * Case-insensitive comparison. Un poste (type_service = 'poste') ne peut être occupé
     * que par un seul utilisateur actif à la fois ; $excludeUserId permet à un update de
     * re-sauvegarder le poste déjà détenu par l'utilisateur en cours de modification.
     *
     * @return array ['valid' => bool, 'error' => ?string]
     */
    public function validateServiceAssignment(?int $serviceId, ServiceRepository $serviceRepo, ?int $excludeUserId = null): array
    {
        if (null === $serviceId) {
            return ['valid' => true, 'error' => null];
        }

        $service = $serviceRepo->getServiceById($serviceId);
        if (!$service) {
            return ['valid' => false, 'error' => 'Service not found.'];
        }

        $typeService = strtolower($service->getTypeService() ?? '');
        if ($typeService === 'service') {
            return ['valid' => false, 'error' => 'Cannot assign a service of type "service" to a user. Only type "poste" is allowed.'];
        }

        if ($typeService === 'poste' && $this->isPosteOccupied($serviceId, $excludeUserId)) {
            return ['valid' => false, 'error' => 'Ce poste est déjà occupé par un autre utilisateur.'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Un autre utilisateur actif (non supprimé) occupe-t-il déjà ce poste ?
     */
    private function isPosteOccupied(int $serviceId, ?int $excludeUserId): bool
    {
        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.service = :serviceId')
            ->andWhere('u.isDelete = false')
            ->setParameter('serviceId', $serviceId);

        if (null !== $excludeUserId) {
            $qb->andWhere('u.id != :excludeUserId')->setParameter('excludeUserId', $excludeUserId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Build a User entity from a raw payload. Does NOT persist.
     *
     * Note: Les rôles métier doivent être assignés via role_ids (voir CreateUserController).
     * Le champ 'roles' est réservé à Symfony Security (ROLE_USER, ROLE_ADMIN, etc.)
     */
    public function buildUserFromPayload(array $data, UserPasswordHasherInterface $passwordHasher): User
    {
        $user = new User();

        if (isset($data['firstName'])) {
            $user->setFirstName((string) $data['firstName']);
        }
        if (isset($data['lastName'])) {
            $user->setLastName((string) $data['lastName']);
        }
        if (isset($data['email'])) {
            $user->setEmail((string) $data['email']);
        }
        // Gestion du matricule : si non fourni ou vide, génération automatique
        if (isset($data['matricule']) && $data['matricule'] !== '' && $data['matricule'] !== null) {
            $user->setMatricule((string) $data['matricule']);
        } else {
            // Générer automatiquement un matricule
            $user->setMatricule($this->generateMatricule());
        }
        if (isset($data['cni'])) {
            $user->setCni($data['cni'] ? (string) $data['cni'] : null);
        }

        $user->setCreatedAt(new \DateTimeImmutable());
        // Les rôles Symfony Security sont définis par défaut à ROLE_USER
        $user->setRoles(['ROLE_USER']);

        if (isset($data['is_active'])) {
            $isActive = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if (null !== $isActive) {
                $user->setIsActive($isActive);
            }
        }

        if (isset($data['service_id'])) {
            $user->setService(null);
        }

        $rawPassword = (string) ($data['password'] ?? '');
        $user->setPassword($passwordHasher->hashPassword($user, $rawPassword));

        return $user;
    }

    /**
     * Apply payload updates to an existing User entity. Does NOT persist.
     *
     * Note: Les rôles métier doivent être mis à jour via role_ids (voir UpdateUserController).
     * Le champ 'roles' est réservé à Symfony Security.
     */
    public function applyPayloadToUser(User $user, array $data, UserPasswordHasherInterface $passwordHasher): User
    {
        if (isset($data['firstName'])) {
            $user->setFirstName((string) $data['firstName']);
        }
        if (isset($data['lastName'])) {
            $user->setLastName((string) $data['lastName']);
        }
        if (isset($data['email'])) {
            $user->setEmail((string) $data['email']);
        }
        if (isset($data['matricule'])) {
            $user->setMatricule((string) $data['matricule']);
        }
        if (isset($data['cni'])) {
            $user->setCni($data['cni'] ? (string) $data['cni'] : null);
        }
        if (!empty($data['password'])) {
            $user->setPassword($passwordHasher->hashPassword($user, (string) $data['password']));
        }

        if (isset($data['is_active'])) {
            $isActive = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if (null !== $isActive) {
                $user->setIsActive($isActive);
            }
        }

        if (isset($data['service_id'])) {
            $user->setService(null);
        }

        return $user;
    }

    /**
     * Persist and flush a User entity.
     */
    public function save(User $user): void
    {
        $em = $this->getEntityManager();
        $em->persist($user);
        $em->flush();
    }

    /**
     * Soft-delete : passe isDelete à true, l'utilisateur reste en base.
     */
    public function removeUser(User $user): void
    {
        $user->setIsDelete(true);
        $this->save($user);
    }

    /**
     * Suppression physique et irréversible.
     */
    public function remove(User $user): void
    {
        $em = $this->getEntityManager();
        $em->remove($user);
        $em->flush();
    }

    /**
     * Update only the password of a user.
     */
    public function updatePassword(User $user, string $hashedPassword): void
    {
        $user->setPassword($hashedPassword);
        $this->save($user);
    }

    /**
     * Premier utilisateur actif rattaché à un service (pour affichage affectation bien).
     */
    public function findActiveByServiceId(int $serviceId): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('IDENTITY(u.service) = :serviceId')
            ->andWhere('u.isDelete = false')
            ->andWhere('u.isActive = true')
            ->setParameter('serviceId', $serviceId)
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Retourne une liste paginée de matricules d'utilisateurs.
     *
     * @return array<int, User>
     */
    public function findMatriculesPaginated(int $page, int $limit, ?string $search = null): array
    {
        $queryBuilder = $this->createQueryBuilder('u')
            ->select('partial u.{id,matricule,firstName,lastName,email,isActive}')
            ->andWhere('u.isDelete = false')
            ->andWhere('u.matricule IS NOT NULL');

        if ($search !== null && $search !== '') {
            $queryBuilder->andWhere('u.matricule LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        /** @var array<int, User> $result */
        $result = $queryBuilder
            ->orderBy('u.matricule', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Compte le nombre total de matricules (avec filtre de recherche optionnel).
     */
    public function countMatricules(?string $search = null): int
    {
        $queryBuilder = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.isDelete = false')
            ->andWhere('u.matricule IS NOT NULL');

        if ($search !== null && $search !== '') {
            $queryBuilder->andWhere('u.matricule LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return (int) $queryBuilder
            ->getQuery()
            ->getSingleScalarResult();
    }
}
