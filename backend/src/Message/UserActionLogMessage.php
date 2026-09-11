<?php

namespace App\Message;

/**
 * Message asynchrone pour logger les actions utilisateurs
 * Ce message est traité de manière asynchrone pour ne pas bloquer l'exécution de la requête
 */
class UserActionLogMessage
{
    public function __construct(
        private string $action,
        private string $message,
        private ?string $resource = null,
        private ?int $resourceId = null,
        private array $details = [],
        private ?string $userIdentifier = null,
        private ?int $userId = null,
        private ?string $ip = null,
        private ?string $userAgent = null
    ) {}

    public function getAction(): string
    {
        return $this->action;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getResource(): ?string
    {
        return $this->resource;
    }

    public function getResourceId(): ?int
    {
        return $this->resourceId;
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    public function getUserIdentifier(): ?string
    {
        return $this->userIdentifier;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }
}
