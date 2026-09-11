<?php

namespace App\Service;

use App\Message\UserActionLogMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Service pour enregistrer les actions des utilisateurs sur les API
 * Supporte le mode synchrone et asynchrone (via Messenger)
 */
class UserActionLoggerService
{
    public function __construct(
        private LoggerInterface $logger,
        private TokenStorageInterface $tokenStorage,
        private MessageBusInterface $messageBus,
        private bool $asyncLogging = true // Par défaut en mode asynchrone
    ) {}

    /**
     * Log une action utilisateur
     * 
     * @param string $action Type d'action (create, update, delete, view, login, etc.)
     * @param string $message Message descriptif de l'action
     * @param string|null $resource Type de ressource concernée (Courrier, User, etc.)
     * @param int|null $resourceId ID de la ressource concernée
     * @param array $details Détails supplémentaires
     */
    public function logAction(
        string $action, 
        string $message, 
        ?string $resource = null, 
        ?int $resourceId = null, 
        array $details = []
    ): void {
        $token = $this->tokenStorage->getToken();
        $user = $token ? $token->getUser() : null;

        if (!$user) {
            return; // Ne pas logger si pas d'utilisateur connecté
        }

        $userIdentifier = $user->getUserIdentifier();
        $userId = method_exists($user, 'getId') ? $user->getId() : null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // Mode asynchrone : dispatch le message vers le worker
        if ($this->asyncLogging) {
            $logMessage = new UserActionLogMessage(
                action: $action,
                message: $message,
                resource: $resource,
                resourceId: $resourceId,
                details: $details,
                userIdentifier: $userIdentifier,
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );
            
            $this->messageBus->dispatch($logMessage);
            return;
        }

        // Mode synchrone : log directement
        $context = [
            'user' => $userIdentifier,
            'user_id' => $userId,
            'action' => $action,
            'resource' => $resource,
            'resource_id' => $resourceId,
            'details' => $details,
            'ip' => $ip,
            'user_agent' => $userAgent
        ];

        $this->logger->info($message, $context);
    }

    /**
     * Active ou désactive le logging asynchrone
     */
    public function setAsyncLogging(bool $async): void
    {
        $this->asyncLogging = $async;
    }

    /**
     * Log une création
     */
    public function logCreate(string $resource, ?int $resourceId, string $message, array $details = []): void
    {
        $this->logAction('create', $message, $resource, $resourceId, $details);
    }

    /**
     * Log une mise à jour
     */
    public function logUpdate(string $resource, ?int $resourceId, string $message, array $details = []): void
    {
        $this->logAction('update', $message, $resource, $resourceId, $details);
    }

    /**
     * Log une suppression
     */
    public function logDelete(string $resource, ?int $resourceId, string $message, array $details = []): void
    {
        $this->logAction('delete', $message, $resource, $resourceId, $details);
    }

    /**
     * Log une consultation
     */
    public function logView(string $resource, ?int $resourceId, string $message, array $details = []): void
    {
        $this->logAction('view', $message, $resource, $resourceId, $details);
    }

    /**
     * Log une connexion
     */
    public function logLogin(string $message, array $details = []): void
    {
        $this->logAction('login', $message, null, null, $details);
    }

    /**
     * Log une déconnexion
     */
    public function logLogout(string $message, array $details = []): void
    {
        $this->logAction('logout', $message, null, null, $details);
    }

    /**
     * Log un téléchargement
     */
    public function logDownload(string $resource, ?int $resourceId, string $message, array $details = []): void
    {
        $this->logAction('download', $message, $resource, $resourceId, $details);
    }

    /**
     * Log un envoi
     */
    public function logSend(string $resource, ?int $resourceId, string $message, array $details = []): void
    {
        $this->logAction('send', $message, $resource, $resourceId, $details);
    }

    /**
     * Log une validation
     */
    public function logApprove(string $resource, ?int $resourceId, string $message, array $details = []): void
    {
        $this->logAction('approve', $message, $resource, $resourceId, $details);
    }

    /**
     * Log un rejet
     */
    public function logReject(string $resource, ?int $resourceId, string $message, array $details = []): void
    {
        $this->logAction('reject', $message, $resource, $resourceId, $details);
    }
}
