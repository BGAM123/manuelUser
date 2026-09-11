<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

class SmsService
{
    private string $apiUrl;
    private string $apiKey;
    private string $sender;
    private ?LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        // Configuration depuis les variables d'environnement
        $this->apiUrl = $_ENV['SMS_API_URL'] ?? 'https://devcodesms.com/developpeur/Send_sms_dev';
        $this->apiKey = $_ENV['SMS_API_KEY'] ?? 'GuESKhMezKLPNkYUII01TXZQR3pHSlQ1eEdRS015dVpSZmlXcGFJejZMc2h5aExBSGRnbHNwMXBhRFE9';
        $this->sender = $_ENV['SMS_SENDER'] ?? 'MINEPIA';
        $this->logger = $logger;
    }

    /**
     * Envoie un SMS à  un seul destinataire
     *
     * @param string $phone Numéro de téléphone (sera normalisé automatiquement)
     * @param string $message Contenu du message (max 260 caractères)
     * @param bool $normalize Si true, normalise le numéro au format camerounais
     * 
     * @return array ['success' => bool, 'message' => string, 'response' => mixed]
     */
    public function sendSms(string $phone, string $message, bool $normalize = true): array
    {
        try {
            // Vérification de la configuration
            if (!$this->isConfigured()) {
                return [
                    'success' => false,
                    'message' => 'Configuration SMS manquante (API_KEY, API_URL ou SENDER)',
                    'response' => null
                ];
            }

            // Normalisation du numéro si demandé
            if ($normalize) {
                $normalizedPhone = $this->normalizePhoneNumber($phone);
                if (!$normalizedPhone['valid']) {
                    return [
                        'success' => false,
                        'message' => 'Numéro invalide: ' . $normalizedPhone['error'],
                        'response' => null
                    ];
                }
                $phone = $normalizedPhone['phone'];
            }

            // Validation du message
            if (empty(trim($message))) {
                return [
                    'success' => false,
                    'message' => 'Le message ne peut pas être vide',
                    'response' => null
                ];
            }

            // Decoupage en segments de 260 caracteres (standard SMS)
            $segments = $this->splitMessage($message, 260);

            if (count($segments) === 1) {
                $result = $this->sendSingleSms($phone, $segments[0]);

                if ($result['success']) {
                    $this->log('info', 'SMS envoyé avec succès', [
                        'phone' => $phone,
                        'message_length' => mb_strlen($segments[0], 'UTF-8')
                    ]);
                } else {
                    $this->log('error', 'Échec envoi SMS', [
                        'phone' => $phone,
                        'error' => $result['message']
                    ]);
                }

                return $result;
            }

            $segmentResults = [];
            $sentCount = 0;
            $totalSegments = count($segments);

            foreach ($segments as $index => $segment) {
                $result = $this->sendSingleSms($phone, $segment);
                $segmentResults[] = [
                    'segment' => $index + 1,
                    'success' => $result['success'],
                    'message' => $result['message'],
                    'response' => $result['response']
                ];

                if ($result['success']) {
                    $sentCount++;
                } else {
                    break;
                }
            }

            if ($sentCount === $totalSegments) {
                $this->log('info', 'SMS envoyé en plusieurs segments', [
                    'phone' => $phone,
                    'segments' => $totalSegments,
                    'message_length' => mb_strlen($message, 'UTF-8')
                ]);

                return [
                    'success' => true,
                    'message' => 'SMS envoyé en ' . $totalSegments . ' segments',
                    'response' => $segmentResults,
                    'segments' => $totalSegments,
                    'sent_segments' => $sentCount
                ];
            }

            $this->log('error', 'Échec envoi SMS sur un segment', [
                'phone' => $phone,
                'segments' => $totalSegments,
                'sent_segments' => $sentCount
            ]);

            return [
                'success' => false,
                'message' => 'Échec envoi segment ' . ($sentCount + 1) . '/' . $totalSegments,
                'response' => $segmentResults,
                'segments' => $totalSegments,
                'sent_segments' => $sentCount
            ];

        } catch (\Exception $e) {
            $this->log('error', 'Exception lors de l\'envoi SMS', [
                'phone' => $phone,
                'exception' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Erreur technique: ' . $e->getMessage(),
                'response' => null
            ];
        }
    }

    /**
     * Decoupe un message en segments
     */
    private function splitMessage(string $message, int $limit = 260): array
    {
        $segments = [];
        $length = mb_strlen($message, 'UTF-8');

        for ($offset = 0; $offset < $length; $offset += $limit) {
            $segments[] = mb_substr($message, $offset, $limit, 'UTF-8');
        }

        return $segments;
    }

    /**
     * Envoie des SMS a plusieurs destinataires
     *
     * @param array $recipients [['phone' => string, 'message' => string], ...]
     * @param bool $stopOnError Si true, arrete au premier echec
     * @param bool $normalize Normalise les numeros
     *
     * @return array Statistiques et details des envois
     */
    public function sendBulkSms(array $recipients, bool $stopOnError = false, bool $normalize = true): array
    {
        $results = [
            'total' => count($recipients),
            'success_count' => 0,
            'failed_count' => 0,
            'details' => []
        ];

        foreach ($recipients as $index => $recipient) {
            // Validation des donnees
            if (!isset($recipient['phone']) || !isset($recipient['message'])) {
                $results['failed_count']++;
                $results['details'][$index] = [
                    'phone' => $recipient['phone'] ?? 'N/A',
                    'success' => false,
                    'message' => 'Telephone ou message manquant',
                    'response' => null
                ];
                continue;
            }

            // Envoi du SMS
            $result = $this->sendSms($recipient['phone'], $recipient['message'], $normalize);
            $results['details'][$index] = array_merge($result, [
                'phone' => $recipient['phone']
            ]);

            if ($result['success']) {
                $results['success_count']++;
            } else {
                $results['failed_count']++;
                if ($stopOnError) {
                    $this->log('info', 'Arret de l\'envoi en lot suite a un echec', [
                        'stopped_at_index' => $index,
                        'total_processed' => $index + 1
                    ]);
                    break;
                }
            }

            // Petit delai pour eviter le spam
            usleep(100000); // 0.1 seconde
        }

        $this->log('info', 'Envoi en lot termine', [
            'total' => $results['total'],
            'success' => $results['success_count'],
            'failed' => $results['failed_count']
        ]);

        return $results;
    }

    /**
     * Envoie un SMS simple avec le mÃªme message Ã  plusieurs numéros
     *
     * @param array $phones Liste des numéros de télephone
     * @param string $message Message unique à  envoyer
     * @param bool $stopOnError Arrêter au premier échec
     * @param bool $normalize Normaliser les numéros
     * 
     * @return array Statistiques des envois
     */
    public function sendSameSmsToMultiple(array $phones, string $message, bool $stopOnError = false, bool $normalize = true): array
    {
        $recipients = array_map(function($phone) use ($message) {
            return ['phone' => $phone, 'message' => $message];
        }, $phones);

        return $this->sendBulkSms($recipients, $stopOnError, $normalize);
    }

    /**
     * Normalise un numéro de téléphone camerounais
     * Formats acceptés: 6XXXXXXXX, 237XXXXXXXX, +237XXXXXXXX, 00237XXXXXXXX
     */
    public function normalizePhoneNumber(string $phone): array
    {
        // Nettoyage du numéro
        $phone = preg_replace('/[\s\-\.]/', '', $phone);
        $phone = preg_replace('/[^\d+]/', '', $phone);

        if (empty($phone)) {
            return [
                'valid' => false,
                'phone' => '',
                'error' => 'Numéro vide après nettoyage'
            ];
        }

        // Format local camerounais (6XXXXXXXX)
        if (preg_match('/^6[0-9]{8}$/', $phone)) {
            return [
                'valid' => true,
                'phone' => '+237' . $phone,
                'error' => null
            ];
        }

        // Format avec indicatif sans + (2376XXXXXXXX)
        if (preg_match('/^2376[0-9]{8}$/', $phone)) {
            return [
                'valid' => true,
                'phone' => '+' . $phone,
                'error' => null
            ];
        }

        // Format international avec + (+2376XXXXXXXX)
        if (preg_match('/^\+2376[0-9]{8}$/', $phone)) {
            return [
                'valid' => true,
                'phone' => $phone,
                'error' => null
            ];
        }

        // Format international avec 00 (002376XXXXXXXX)
        if (preg_match('/^002376[0-9]{8}$/', $phone)) {
            return [
                'valid' => true,
                'phone' => '+' . substr($phone, 2),
                'error' => null
            ];
        }

        return [
            'valid' => false,
            'phone' => $phone,
            'error' => 'Format non reconnu. Utilisez: 6XXXXXXXX, 2376XXXXXXXX, +2376XXXXXXXX'
        ];
    }

    /**
     * Envoie effectif d'un SMS via cURL (basé sur votre SmStext.php)
     */
    private function sendSingleSms(string $phone, string $message): array
    {
        $data_to_send = [
            'api_key' => $this->apiKey,
            'sender'  => $this->sender,
            'phone'   => $phone,
            'message' => $message
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data_to_send));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'MINEPIA-SMS-Service/1.0');

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return [
                'success' => false,
                'message' => 'Erreur cURL: ' . $curlError,
                'response' => null
            ];
        }

        if ($httpCode !== 200) {
            return [
                'success' => false,
                'message' => 'Code HTTP: ' . $httpCode,
                'response' => $response
            ];
        }

        // Tentative de dÃ©codage JSON de la rÃ©ponse
        $decodedResponse = json_decode($response, true);

        return [
            'success' => true,
            'message' => 'SMS envoyé avec succès',
            'response' => $decodedResponse ?: $response
        ];
    }

    /**
     * Vérifie si la configuration SMS est complète
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiUrl) && !empty($this->apiKey) && !empty($this->sender);
    }

    /**
     * Test de connectivité avec l'API SMS
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Configuration SMS incomplète'
            ];
        }

        try {
            // Test avec un numéro factice
            $result = $this->sendSingleSms('+2376050327091', 'Test MINEPIA');
            return [
                'success' => true,
                'message' => 'Test de connectivité réussi',
                'response' => $result['response']
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur de connectivité: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Récupère le solde de SMS restants
     * 
     * @return array ['success' => bool, 'balance' => int|null, 'message' => string, 'response' => mixed]
     */
    public function getSmsBalance(): array
    {
        try {
            // Vérification de la configuration
            if (!$this->isConfigured()) {
                return [
                    'success' => false,
                    'balance' => null,
                    'message' => 'Configuration SMS manquante (API_KEY, API_URL ou SENDER)',
                    'response' => null,
                    'error_type' => 'configuration'
                ];
            }

            // URL de l'API pour vérifier le solde (endpoint Devcode SMS)
            $balanceUrl = $_ENV['SMS_BALANCE_URL'] ?? 'https://devcodesms.com/developpeur/Solde_sms_dev';

            // Données à envoyer en POST (dans le body)
            $data_to_send = [
                'api_key' => $this->apiKey
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $balanceUrl);
            curl_setopt($ch, CURLOPT_POST, true); // POST avec body
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data_to_send)); // Données dans le body
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            
            // En-tÃªtes HTTP
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'User-Agent: MINEPIA-SMS-Service/1.0',
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                $this->log('error', 'Erreur lors de la récupération du solde SMS', [
                    'error' => $curlError
                ]);
                return [
                    'success' => false,
                    'balance' => null,
                    'message' => 'Erreur cURL: ' . $curlError,
                    'response' => null,
                    'error_type' => 'curl',
                    'help' => 'Vérifiez votre connexion internet et réessayez.'
                ];
            }

            // Gestion spécifique de l'erreur 406 (endpoint non disponible)
            if ($httpCode === 406) {
                $this->log('warning', 'Endpoint de solde SMS non disponible (406)', [
                    'url' => $balanceUrl,
                    'http_code' => $httpCode
                ]);
                return [
                    'success' => false,
                    'balance' => null,
                    'message' => 'L\'endpoint pour consulter le solde SMS n\'est pas disponible ou nécessite une configuration spéciale.',
                    'response' => null,
                    'error_type' => 'endpoint_unavailable',
                    'http_code' => 406,
                    'endpoint' => $balanceUrl,
                    'help' => 'Veuillez contacter le support Devcode SMS (https://devcodesms.com) pour obtenir l\'endpoint correct de consultation du solde, ou configurez SMS_BALANCE_URL dans votre fichier .env avec le bon endpoint.'
                ];
            }

            // Gestion des autres codes HTTP non-200
            if ($httpCode !== 200) {
                $this->log('error', 'Code HTTP incorrect lors de la récupération du solde', [
                    'http_code' => $httpCode,
                    'response' => substr($response, 0, 500),
                    'endpoint' => $balanceUrl
                ]);
                return [
                    'success' => false,
                    'balance' => null,
                    'message' => 'Erreur HTTP lors de la récupération du solde',
                    'response' => null,
                    'error_type' => 'http_error',
                    'http_code' => $httpCode,
                    'endpoint' => $balanceUrl,
                    'help' => $httpCode === 404 
                        ? 'L\'endpoint n\'existe pas. Vérifiez l\'URL dans la documentation Devcode SMS.'
                        : 'Code HTTP ' . $httpCode . ' reçu. Contactez le support Devcode SMS.'
                ];
            }

            // Tentative de décodage JSON de la réponse
            $decodedResponse = json_decode($response, true);
            
            // Gestion des erreurs de décodage JSON
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->log('error', 'Erreur de décodage JSON du solde SMS', [
                    'json_error' => json_last_error_msg(),
                    'response_preview' => substr($response, 0, 200)
                ]);
                return [
                    'success' => false,
                    'balance' => null,
                    'message' => 'Format de réponse invalide: ' . json_last_error_msg(),
                    'response' => null,
                    'error_type' => 'invalid_response',
                    'help' => 'La réponse de l\'API n\'est pas au format attendu.'
                ];
            }

            // Extraction du solde selon le format exact de Devcode SMS
            $balance = null;
            $qtyAcheter = null;
            $qtyEnvoye = null;
            
            if (is_array($decodedResponse)) {
                // Vérifier d'abord si la requête a réussi
                if (isset($decodedResponse['success']) && $decodedResponse['success'] === false) {
                    $errorMsg = $decodedResponse['msg'] ?? $decodedResponse['message'] ?? 'Erreur inconnue';
                    $this->log('error', 'Erreur API SMS lors de la récupération du solde', [
                        'error' => $errorMsg,
                        'response' => $decodedResponse
                    ]);
                    return [
                        'success' => false,
                        'balance' => null,
                        'message' => 'Erreur API: ' . $errorMsg,
                        'response' => $decodedResponse,
                        'error_type' => 'api_error'
                    ];
                }

                // Format de la documentation Devcode SMS (réponse que vous avez reçue)
                if (isset($decodedResponse['data']['solde'])) {
                    $balance = (int) $decodedResponse['data']['solde'];
                    $qtyAcheter = isset($decodedResponse['data']['qty_acheter']) ? (int) $decodedResponse['data']['qty_acheter'] : null;
                    $qtyEnvoye = isset($decodedResponse['data']['qty_envoye']) ? (int) $decodedResponse['data']['qty_envoye'] : null;
                    
                    $this->log('info', 'Solde SMS récupéré avec succès', [
                        'balance' => $balance,
                        'qty_acheter' => $qtyAcheter,
                        'qty_envoye' => $qtyEnvoye,
                        'api_message' => $decodedResponse['msg'] ?? null
                    ]);
                    
                    return [
                        'success' => true,
                        'balance' => $balance,
                        'qty_acheter' => $qtyAcheter,
                        'qty_envoye' => $qtyEnvoye,
                        'message' => $decodedResponse['msg'] ?? 'Solde récupéré avec succès',
                        'response' => $decodedResponse
                    ];
                }
                // Autres formats possibles (fallback)
                elseif (isset($decodedResponse['solde'])) {
                    $balance = (int) $decodedResponse['solde'];
                } elseif (isset($decodedResponse['balance'])) {
                    $balance = (int) $decodedResponse['balance'];
                }
            }

            // Si on arrive ici, le format n'est pas reconnu
            if ($balance !== null) {
                $this->log('info', 'Solde SMS récupéré (format alternatif)', [
                    'balance' => $balance
                ]);
                return [
                    'success' => true,
                    'balance' => $balance,
                    'qty_acheter' => $qtyAcheter,
                    'qty_envoye' => $qtyEnvoye,
                    'message' => 'Solde récupéré avec succès',
                    'response' => $decodedResponse
                ];
            } else {
                $this->log('warning', 'Format de réponse inattendu pour le solde SMS', [
                    'response' => $response
                ]);
                return [
                    'success' => false,
                    'balance' => null,
                    'message' => 'Format de réponse non reconnu',
                    'response' => $decodedResponse,
                    'error_type' => 'format_unknown',
                    'help' => 'La réponse a été reçue mais le format ne correspond pas aux formats attendus.'
                ];
            }

        } catch (\Exception $e) {
            $this->log('error', 'Exception lors de la récupération du solde SMS', [
                'exception' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'balance' => null,
                'message' => 'Erreur technique: ' . $e->getMessage(),
                'response' => null,
                'error_type' => 'exception'
            ];
        }
    }

    /**
     * Log des événements
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->log($level, '[SMS Service] ' . $message, $context);
        }
    }

    // Getters pour debug
    public function getApiUrl(): string { return $this->apiUrl; }
    public function getSender(): string { return $this->sender; }
    public function getApiKey(): string { return substr($this->apiKey, 0, 10) . '...'; } // Masqué pour sécurité
}
