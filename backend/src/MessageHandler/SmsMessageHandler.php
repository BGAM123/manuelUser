<?php

namespace App\MessageHandler;

use App\Message\SmsMessage;
use App\Service\SmsService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SmsMessageHandler
{
    public function __construct(
        private SmsService $smsService,
        private LoggerInterface $logger
    ) {}

    public function __invoke(SmsMessage $message): void
    {
        $context = $message->getContext();
        $contextLog = !empty($context) ? json_encode($context) : '';

        try {
            $this->logger->info('[SMS Handler] Traitement SMS asynchrone', [
                'phone' => $message->getPhone(),
                'message_length' => mb_strlen($message->getMessage(), 'UTF-8'),
                'context' => $context
            ]);

            $result = $this->smsService->sendSms(
                $message->getPhone(),
                $message->getMessage(),
                $message->shouldNormalize()
            );

            if ($result['success']) {
                $this->logger->info('[SMS Handler] SMS envoyé avec succès (asynchrone)', [
                    'phone' => $message->getPhone(),
                    'context' => $context,
                    'api_response' => $result['response']
                ]);
            } else {
                $this->logger->error('[SMS Handler] Échec envoi SMS (asynchrone)', [
                    'phone' => $message->getPhone(),
                    'error' => $result['message'],
                    'context' => $context
                ]);
                
                // Optionnel : relancer une exception pour retry automatique
                throw new \RuntimeException('Échec envoi SMS: ' . $result['message']);
            }
        } catch (\Exception $e) {
            $this->logger->error('[SMS Handler] Exception lors du traitement SMS', [
                'phone' => $message->getPhone(),
                'exception' => $e->getMessage(),
                'context' => $context
            ]);
            
            // Re-lance l'exception pour déclencher le mécanisme de retry
            throw $e;
        }
    }
}