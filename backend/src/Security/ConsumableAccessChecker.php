<?php

namespace App\Security;

use App\Entity\Consumable;
use App\Entity\ConsumableTransfer;
use App\Entity\Service;
use App\Entity\User;
use App\Repository\ConsumableTransferRepository;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Centralise la règle d'accès aux consomptibles et à leurs transferts : un utilisateur
 * normal n'agit que sur les ressources de son propre service ; un administrateur (même
 * détection de rôle métier que l'ancienne logique ad hoc de ListConsumablesController)
 * agit sur tous les services. Seule exception, sans dérogation admin : la quantité
 * consommée d'un transfert ne peut être renseignée que par le service qui l'a reçu
 * (voir assertCanConsume, utilisé par ConsumeConsumableTransferController).
 *
 * Convention : un ConsumableTransfer "appartient" au service qui l'a reçu (destination),
 * cohérent avec ConsumableTransferRepository::findByService()/findPaginated(), déjà
 * centrés sur la destination plutôt que la source.
 */
final class ConsumableAccessChecker
{
    private const ADMIN_ROLE_NAMES = ['Administrateur', 'Administrateur patrimonial', 'Administrateur système'];

    public function __construct(private readonly ConsumableTransferRepository $transferRepository)
    {
    }

    public function isAdmin(User $user): bool
    {
        foreach ($user->getAssignedRoles() as $role) {
            if (in_array($role->getNom(), self::ADMIN_ROLE_NAMES, true)) {
                return true;
            }
        }

        return false;
    }

    public function canAccessService(User $user, ?Service $service): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        $userServiceId = $user->getService()?->getId();

        return $userServiceId !== null && $service !== null && $userServiceId === $service->getId();
    }

    /**
     * Un consomptible "appartient" à un service soit parce qu'il y est enregistré
     * (Consumable::service, figé à la création), soit parce que ce service en détient du
     * stock reçu par transfert (ConsumableTransfer::serviceDestination) — un service ne
     * peut pas retransférer ce qu'il ne peut pas voir.
     */
    public function canAccessConsumable(User $user, Consumable $consumable): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        $userServiceId = $user->getService()?->getId();
        if ($userServiceId === null) {
            return false;
        }

        if ($consumable->getService()?->getId() === $userServiceId) {
            return true;
        }

        if ($consumable->getId() === null) {
            return false;
        }

        // Requête directe (plutôt qu'une méthode nommée du repository) pour ne pas
        // dépendre de la forme exacte de ses autres méthodes, qui évoluent séparément.
        $hasRelation = $this->transferRepository->createQueryBuilder('ct')
            ->select('1')
            ->where('ct.consumable = :consumableId')
            ->andWhere('(ct.serviceDestination = :serviceId OR ct.serviceSource = :serviceId)')
            ->andWhere('ct.isDelete = false')
            ->setParameter('consumableId', $consumable->getId())
            ->setParameter('serviceId', $userServiceId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $hasRelation !== null;
    }

    public function assertCanAccessConsumable(User $user, Consumable $consumable, string $message = "Vous n'êtes pas autorisé à accéder à ce consomptible."): void
    {
        if (!$this->canAccessConsumable($user, $consumable)) {
            throw new AccessDeniedHttpException($message);
        }
    }

    public function canAccessServiceId(User $user, int $serviceId): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        return $user->getService()?->getId() === $serviceId;
    }

    public function assertCanAccessService(User $user, ?Service $service, string $message = "Vous n'êtes pas autorisé à accéder aux consomptibles de ce service."): void
    {
        if (!$this->canAccessService($user, $service)) {
            throw new AccessDeniedHttpException($message);
        }
    }

    public function assertCanAccessServiceId(User $user, int $serviceId, string $message = "Vous n'êtes pas autorisé à accéder aux données de ce service."): void
    {
        if (!$this->canAccessServiceId($user, $serviceId)) {
            throw new AccessDeniedHttpException($message);
        }
    }

    public function isServiceDestination(User $user, ConsumableTransfer $transfer): bool
    {
        $userServiceId = $user->getService()?->getId();

        return $userServiceId !== null && $userServiceId === $transfer->getServiceDestination()?->getId();
    }

    public function canAccessTransfer(User $user, ConsumableTransfer $transfer): bool
    {
        return $this->isAdmin($user) || $this->isServiceDestination($user, $transfer);
    }

    public function assertCanAccessTransfer(User $user, ConsumableTransfer $transfer, string $message = "Vous n'êtes pas autorisé à accéder à ce transfert."): void
    {
        if (!$this->canAccessTransfer($user, $transfer)) {
            throw new AccessDeniedHttpException($message);
        }
    }

    /**
     * Restriction sans dérogation admin : seul le service qui a reçu le transfert peut
     * renseigner la quantité consommée — chaque service remplit sa propre consommation.
     */
    public function assertCanConsume(User $user, ConsumableTransfer $transfer, string $message = "Seul le service destinataire de ce transfert peut renseigner la quantité consommée."): void
    {
        if (!$this->isServiceDestination($user, $transfer)) {
            throw new AccessDeniedHttpException($message);
        }
    }
}
