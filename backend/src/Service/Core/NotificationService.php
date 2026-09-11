<?php

namespace App\Service\Core;

use App\Entity\Core\Notification;
use App\Entity\Core\Service;
use App\Entity\Core\User;
use App\Repository\Core\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service pour créer et gérer les notifications
 */
class NotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
    ) {}

    /**
     * Crée une notification pour un utilisateur spécifique
     *
     * @param User $user Utilisateur destinataire
     * @param string $titre Titre de la notification
     * @param string $message Message de la notification
     * @param string|null $type Type de notification (optionnel)
     * @param array|null $data Données supplémentaires (optionnel)
     * @param Service|null $service Service associé (optionnel)
     * @return Notification
     */
    public function createNotificationForUser(
        User $user,
        string $titre,
        string $message,
        ?string $type = null,
        ?array $data = null,
        ?Service $service = null
    ): Notification {
        $notification = new Notification();
        $notification->setTitre($titre);
        $notification->setMessage($message);
        $notification->setType($type);
        $notification->setData($data);
        $notification->setUser($user);
        $notification->setService($service);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }

    /**
     * Crée des notifications pour tous les utilisateurs d'un service
     *
     * @param Service $service Service dont les membres recevront la notification
     * @param string $titre Titre de la notification
     * @param string $message Message de la notification
     * @param string|null $type Type de notification (optionnel)
     * @param array|null $data Données supplémentaires (optionnel)
     * @param bool $activeOnly Si true, n'envoie qu'aux utilisateurs actifs
     * @return int Nombre de notifications créées
     */
    public function createNotificationForService(
        Service $service,
        string $titre,
        string $message,
        ?string $type = null,
        ?array $data = null,
        bool $activeOnly = true
    ): int {
        $criteria = [
            'idService' => $service,
            'isDelete' => false,
        ];

        if ($activeOnly) {
            $criteria['isActive'] = true;
        }

        $users = $this->userRepository->findBy($criteria);
        $count = 0;

        foreach ($users as $user) {
            $notification = new Notification();
            $notification->setTitre($titre);
            $notification->setMessage($message);
            $notification->setType($type);
            $notification->setData($data);
            $notification->setUser($user);
            $notification->setService($service);

            $this->entityManager->persist($notification);
            $count++;
        }

        $this->entityManager->flush();

        return $count;
    }

    /**
     * Crée des notifications pour plusieurs utilisateurs
     *
     * @param array $users Tableau d'utilisateurs
     * @param string $titre Titre de la notification
     * @param string $message Message de la notification
     * @param string|null $type Type de notification (optionnel)
     * @param array|null $data Données supplémentaires (optionnel)
     * @param Service|null $service Service associé (optionnel)
     * @return int Nombre de notifications créées
     */
    public function createNotificationForMultipleUsers(
        array $users,
        string $titre,
        string $message,
        ?string $type = null,
        ?array $data = null,
        ?Service $service = null
    ): int {
        $count = 0;

        foreach ($users as $user) {
            if ($user instanceof User) {
                $notification = new Notification();
                $notification->setTitre($titre);
                $notification->setMessage($message);
                $notification->setType($type);
                $notification->setData($data);
                $notification->setUser($user);
                $notification->setService($service);

                $this->entityManager->persist($notification);
                $count++;
            }
        }

        $this->entityManager->flush();

        return $count;
    }

    /**
     * Crée une notification de type "courrier" pour un utilisateur
     *
     * @param User $user
     * @param int $courrierId
     * @param string $action
     * @return Notification
     */
    public function notifyNewCourrier(User $user, int $courrierId, string $action = 'reçu'): Notification
    {
        return $this->createNotificationForUser(
            user: $user,
            titre: "Nouveau courrier {$action}",
            message: "Un nouveau courrier a été {$action}. Veuillez le consulter.",
            type: 'courrier',
            data: ['courrier_id' => $courrierId, 'action' => $action]
        );
    }

    /**
     * Crée une notification de type "transmission" pour un utilisateur
     *
     * @param User $user
     * @param int $transmissionId
     * @return Notification
     */
    public function notifyNewTransmission(User $user, int $transmissionId): Notification
    {
        return $this->createNotificationForUser(
            user: $user,
            titre: "Nouvelle transmission reçue",
            message: "Une nouvelle transmission vous a été adressée. Veuillez la consulter.",
            type: 'transmission',
            data: ['transmission_id' => $transmissionId]
        );
    }

    /**
     * Crée une notification de type "reponse" pour un utilisateur
     *
     * @param User $user
     * @param int $reponseId
     * @return Notification
     */
    public function notifyNewReponse(User $user, int $reponseId): Notification
    {
        return $this->createNotificationForUser(
            user: $user,
            titre: "Nouvelle réponse reçue",
            message: "Une nouvelle réponse a été enregistrée. Veuillez la consulter.",
            type: 'reponse',
            data: ['reponse_id' => $reponseId]
        );
    }

    /**
     * Notifie tous les membres d'un service d'un nouveau courrier
     *
     * @param Service $service
     * @param int $courrierId
     * @return int Nombre de notifications créées
     */
    public function notifyServiceNewCourrier(Service $service, int $courrierId): int
    {
        return $this->createNotificationForService(
            service: $service,
            titre: "Nouveau courrier pour votre service",
            message: "Un nouveau courrier a été enregistré pour votre service. Veuillez le consulter.",
            type: 'courrier',
            data: ['courrier_id' => $courrierId]
        );
    }

    /**
     * Notifie tous les membres d'un service d'une nouvelle transmission
     *
     * @param Service $service
     * @param int $transmissionId
     * @return int Nombre de notifications créées
     */
    public function notifyServiceNewTransmission(Service $service, int $transmissionId): int
    {
        return $this->createNotificationForService(
            service: $service,
            titre: "Nouvelle transmission pour votre service",
            message: "Une nouvelle transmission a été adressée à votre service. Veuillez la consulter.",
            type: 'transmission',
            data: ['transmission_id' => $transmissionId]
        );
    }
}
