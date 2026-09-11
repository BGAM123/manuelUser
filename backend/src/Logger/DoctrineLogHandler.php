<?php

namespace App\Logger;

use Monolog\Logger;
use Monolog\LogRecord;
use App\Entity\Core\Log;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Logger DoctrineLogHandler
 *
 * Gère l'enregistrement des logs dans une base de données via Doctrine ORM.
 * Cette classe étend `AbstractProcessingHandler` de Monolog pour traiter les messages de log et les stocker dans une table de base de données.
 */
class DoctrineLogHandler extends AbstractProcessingHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private TokenStorageInterface $tokenStorage,
        private AuthorizationCheckerInterface $authChecker,
        int $level = Logger::DEBUG,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);
    }

    /**
     * Écrit une entrée de journal dans la base de données.
     *
     * @param LogRecord $record L'enregistrement de journal contenant les informations à loguer.
     */
    protected function write(LogRecord $record): void
    {
        // // Récupère le token d'authentification à partir du stockage de jetons
        // $token = $this->tokenStorage->getToken();
        // // Si un token existe, récupère l'utilisateur authentifié ; sinon, l'utilisateur est `null`
        // $user = $token ? $token->getUser() : null;

        // // Crée une nouvelle instance de l'entité Log pour enregistrer les informations
        // $log = new Log();
        // // Associe l'utilisateur au log si disponible (peut être null si aucun utilisateur n'est connecté)
        // $log->setUser($user);
        // // Définit le niveau du journal (par exemple : "INFO", "ERROR", "DEBUG")
        // $log->setLevel($record->level->getName());
        // // Définit le message de journalisation
        // $log->setMessage($record->message);
        // // Définit le contexte du log (généralement un tableau contenant des métadonnées supplémentaires)
        // $log->setContext($record->context);
        // // Définit le canal de journalisation (par exemple : "security", "database", "application")
        // $log->setChannel($record->channel);

        // // Persiste l'objet Log dans la base de données
        // $this->em->persist($log);
        // // Enregistre les changements dans la base de données
        // $this->em->flush();
    }
}
