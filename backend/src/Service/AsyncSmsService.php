<?php

namespace App\Service;

use App\Message\SmsMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Service SMS asynchrone utilisant Messenger
 */
class AsyncSmsService
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private SmsService $smsService, // Pour les envois synchrones si nécessaire
        private LoggerInterface $logger
    ) {}

    /**
     * Envoie un SMS de manière asynchrone
     *
     * @param string $phone Numéro de téléphone
     * @param string $message Contenu du message
     * @param bool $normalize Normaliser le numéro (défaut: true)
     * @param array $context Contexte additionnel pour les logs
     * @param bool $async Si true utilise l'envoi asynchrone, sinon synchrone (défaut: true)
     * 
     * @return array Résultat immédiat pour l'async ou complet pour le sync
     */
    public function sendSms(
        string $phone, 
        string $message, 
        bool $normalize = true, 
        array $context = [],
        bool $async = true
    ): array {
        // Validation basique
        if (empty(trim($phone))) {
            return [
                'success' => false,
                'message' => 'Numéro de téléphone requis',
                'async' => false
            ];
        }

        if (empty(trim($message))) {
            return [
                'success' => false,
                'message' => 'Message requis',
                'async' => false
            ];
        }

        if ($async) {
            // 🚀 ENVOI ASYNCHRONE
            try {
                $smsMessage = new SmsMessage($phone, $message, $normalize, $context);
                $this->messageBus->dispatch($smsMessage);

                $this->logger->info('[Async SMS] SMS mis en file d\'attente', [
                    'phone' => $phone,
                    'message_length' => mb_strlen($message, 'UTF-8'),
                    'context' => $context
                ]);

                return [
                    'success' => true,
                    'message' => 'SMS mis en file d\'attente pour envoi asynchrone',
                    'async' => true
                ];
            } catch (\Exception $e) {
                $this->logger->error('[Async SMS] Erreur mise en file d\'attente', [
                    'phone' => $phone,
                    'exception' => $e->getMessage(),
                    'context' => $context
                ]);

                return [
                    'success' => false,
                    'message' => 'Erreur lors de la mise en file d\'attente: ' . $e->getMessage(),
                    'async' => false
                ];
            }
        } else {
            // 📞 ENVOI SYNCHRONE (fallback)
            return array_merge(
                $this->smsService->sendSms($phone, $message, $normalize),
                ['async' => false]
            );
        }
    }

    /**
     * Envoie plusieurs SMS de manière asynchrone
     */
    public function sendBulkSms(array $recipients, array $globalContext = []): array
    {
        $results = [
            'total' => count($recipients),
            'queued' => 0,
            'failed' => 0,
            'details' => []
        ];

        foreach ($recipients as $index => $recipient) {
            if (!isset($recipient['phone']) || !isset($recipient['message'])) {
                $results['failed']++;
                $results['details'][$index] = [
                    'success' => false,
                    'message' => 'Phone ou message manquant',
                    'phone' => $recipient['phone'] ?? 'N/A'
                ];
                continue;
            }

            $context = array_merge($globalContext, $recipient['context'] ?? [], [
                'bulk_index' => $index
            ]);

            $result = $this->sendSms(
                $recipient['phone'],
                $recipient['message'],
                $recipient['normalize'] ?? true,
                $context,
                true // Force asynchrone
            );

            $results['details'][$index] = array_merge($result, [
                'phone' => $recipient['phone']
            ]);

            if ($result['success']) {
                $results['queued']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Envoie synchrone direct (bypass async)
     */
    public function sendSmsSync(string $phone, string $message, bool $normalize = true): array
    {
        return $this->sendSms($phone, $message, $normalize, [], false);
    }

    /**
     * Vérifie la configuration SMS
     */
    public function checkConfiguration(): array
    {
        return $this->smsService->checkConfiguration();
    }

    /**
     * Test de connectivité
     */
    public function testConnection(): array
    {
        return $this->smsService->testConnection();
    }

    /**
     * Normalise un numéro (utilitaire)
     */
    public function normalizePhoneNumber(string $phone): array
    {
        return $this->smsService->normalizePhoneNumber($phone);
    }
}