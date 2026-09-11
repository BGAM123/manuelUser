<?php

namespace App\MessageHandler;

use App\Message\UserActionLogMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler pour traiter les messages de log asynchrones
 */
#[AsMessageHandler]
class UserActionLogMessageHandler
{
    public function __construct(
        private LoggerInterface $userActionLogger
    ) {}

    public function __invoke(UserActionLogMessage $message): void
    {
        $context = [
            'user' => $message->getUserIdentifier(),
            'user_id' => $message->getUserId(),
            'action' => $message->getAction(),
            'resource' => $message->getResource(),
            'resource_id' => $message->getResourceId(),
            'details' => $message->getDetails(),
            'ip' => $message->getIp(),
            'user_agent' => $message->getUserAgent()
        ];

        // Logger sur le channel user_action
        $this->userActionLogger->info($message->getMessage(), $context);
    }
}
