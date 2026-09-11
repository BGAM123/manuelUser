<?php

namespace App\Controller\Core;

use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[OA\Tag(name: "Logs")]
class LogController extends AbstractController
{
    #[Route('/core/logs', name: 'app_core_logs_list', methods: ['GET'])]
    #[OA\Get(
        path: '/core/logs',
        summary: 'Liste les logs des actions de tous les utilisateurs',
        tags: ['Logs'],
        description: "Retourne les logs des actions effectuées par tous les utilisateurs sur les API (création, modification, suppression, consultation, etc.). Par dÃ©faut, affiche tous les logs historiques (pas limitÃ© Ã  aujourd'hui). Vous pouvez filtrer par utilisateur, date, action, etc.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'page',
                in: 'query',
                required: false,
                description: 'Numéro de la page (défaut: 1)',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
            new OA\Parameter(
                name: 'limit',
                in: 'query',
                required: false,
                description: 'Nombre de logs par page (défaut: 10, max: 500)',
                schema: new OA\Schema(type: 'integer', example: 10)
            ),
            new OA\Parameter(
                name: 'level',
                in: 'query',
                required: false,
                description: 'Filtrer par niveau de log (INFO, WARNING, ERROR, etc.)',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Rechercher dans le message d\'action',
                schema: new OA\Schema(type: 'string', example: 'courrier')
            ),
            new OA\Parameter(
                name: 'action',
                in: 'query',
                required: false,
                description: 'Filtrer par type d\'action (create, update, delete, view, login, etc.)',
                schema: new OA\Schema(type: 'string', example: 'create')
            ),
            new OA\Parameter(
                name: 'user',
                in: 'query',
                required: false,
                description: 'Filtrer par utilisateur spécifique (username). Par défaut, affiche les logs de tous les utilisateurs.',
                schema: new OA\Schema(type: 'string', example: 'test1')
            ),
            new OA\Parameter(
                name: 'date_start',
                in: 'query',
                required: false,
                description: 'Date de début pour filtrer les logs (format: Y-m-d ou Y-m-d H:i:s)',
                schema: new OA\Schema(type: 'string', example: '2025-11-01')
            ),
            new OA\Parameter(
                name: 'date_end',
                in: 'query',
                required: false,
                description: 'Date de fin pour filtrer les logs (format: Y-m-d ou Y-m-d H:i:s)',
                schema: new OA\Schema(type: 'string', example: '2025-11-28')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des logs récupérée avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'datetime', type: 'string', example: '2025-11-18 14:30:25'),
                                    new OA\Property(property: 'action', type: 'string', example: 'create'),
                                    new OA\Property(property: 'message', type: 'string', example: 'Création d\'un courrier'),
                                    new OA\Property(property: 'resource', type: 'string', nullable: true, example: 'Courrier'),
                                    new OA\Property(property: 'resource_id', type: 'integer', nullable: true, example: 123),
                                    new OA\Property(property: 'details', type: 'object', nullable: true)
                                ],
                                type: 'object'
                            )
                        ),
                        new OA\Property(
                            property: 'pagination',
                            properties: [
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'per_page', type: 'integer', example: 50),
                                new OA\Property(property: 'total', type: 'integer', example: 150)
                            ],
                            type: 'object'
                        )
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 404, description: 'Aucun log trouvé')
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        // RÃ©cupÃ©rer l'utilisateur connectÃ©
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse([
                'error' => 'Utilisateur non authentifié'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // RÃ©cupÃ©ration des paramÃ¨tres de requÃªte
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(500, max(1, (int) $request->query->get('limit', 10))); // AugmentÃ© de 50 Ã  100 par dÃ©faut
        $levelFilter = $request->query->get('level');
        $searchFilter = $request->query->get('search');
        $actionFilter = $request->query->get('action');
        $userFilter = $request->query->get('user');
        $dateStartFilter = $request->query->get('date_start');
        $dateEndFilter = $request->query->get('date_end');

        // DÃ©terminer le fichier de log Ã  lire
        $environment = $this->getParameter('kernel.environment');
        $logDir = $this->getParameter('kernel.logs_dir');
        $logFile = $logDir . '/' . $environment . '.log';

        // VÃ©rifier que le fichier existe
        if (!file_exists($logFile)) {
            return new JsonResponse([
                'data' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $limit,
                    'total' => 0,
                    'total_pages' => 0
                ],
                'message' => 'Le fichier de log n\'existe pas encore'
            ]);
        }

        try {
            // DÃ©terminer le filtre utilisateur Ã  appliquer
            $userIdentifier = null;
            
            // Par dÃ©faut : afficher tous les logs de tous les utilisateurs (pas de filtre)
            // L'utilisateur peut filtrer explicitement avec le paramÃ¨tre 'user'
            if ($userFilter) {
                // Filtrer par un utilisateur spÃ©cifique
                $userIdentifier = $userFilter;
            }
            
            // Lire le fichier de log (derniÃ¨res entrÃ©es en premier)
            $logs = $this->parseLogFileReverse($logFile, $levelFilter, $searchFilter, $userIdentifier, $actionFilter, $page, $limit, $dateStartFilter, $dateEndFilter);
            
            $total = $logs['total'];
            $paginatedLogs = $logs['data'];

            $response = [
                'data' => $paginatedLogs,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => (int) ceil($total / $limit)
                ]
            ];

            // Ajouter un message informatif si aucun log trouvé avec des filtres actifs
            if ($total === 0 && ($dateStartFilter || $dateEndFilter || $userFilter || $actionFilter || $searchFilter)) {
                $response['message'] = 'Aucun log trouvé pour les filtres appliqués';
                $appliedFilters = [];
                if ($dateStartFilter) $appliedFilters['date_start'] = $dateStartFilter;
                if ($dateEndFilter) $appliedFilters['date_end'] = $dateEndFilter;
                if ($userFilter) $appliedFilters['user'] = $userFilter;
                if ($actionFilter) $appliedFilters['action'] = $actionFilter;
                if ($searchFilter) $appliedFilters['search'] = $searchFilter;
                $response['applied_filters'] = $appliedFilters;
            }

            return new JsonResponse($response);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de la lecture des logs',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Parse le fichier de log en mode inversÃ© (tail) pour gÃ©rer les gros fichiers
     * Lit seulement les lignes nÃ©cessaires pour la page demandÃ©e
     */
    private function parseLogFileReverse(string $logFile, ?string $levelFilter, ?string $searchFilter, ?string $userFilter, ?string $actionFilter, int $page, int $limit, ?string $dateStartFilter = null, ?string $dateEndFilter = null): array
    {
        $bufferSize = 8192; // Taille du buffer de lecture
        $handle = fopen($logFile, 'r');
        
        if (!$handle) {
            throw new \RuntimeException('Impossible d\'ouvrir le fichier de log');
        }

        // Obtenir la taille du fichier
        fseek($handle, 0, SEEK_END);
        $fileSize = ftell($handle);

        // Lire les derniÃ¨res lignes du fichier (approximativement 5x ce qu'on veut afficher)
        // AugmentÃ© Ã  50000 lignes pour garder l'historique complet des logs
        $maxLines = max(50000, $page * $limit * 2);
        $lines = $this->readLastLines($logFile, $maxLines);
        
        fclose($handle);

        // Parser les lignes
        $logs = $this->parseLines($lines, $levelFilter, $searchFilter, $userFilter, $actionFilter, $dateStartFilter, $dateEndFilter);
        
        // Les logs sont dÃ©jÃ  dans l'ordre inverse (les plus rÃ©cents en premier)
        $total = count($logs);
        $offset = ($page - 1) * $limit;
        $paginatedLogs = array_slice($logs, $offset, $limit);

        // Nettoyer les donnÃ©es avant de les retourner (supprimer datetime_raw)
        foreach ($paginatedLogs as &$log) {
            unset($log['datetime_raw']);
        }

        return [
            'data' => $paginatedLogs,
            'total' => $total
        ];
    }

    /**
     * Lit les N derniÃ¨res lignes d'un fichier de maniÃ¨re efficace
     */
    private function readLastLines(string $file, int $numLines): array
    {
        $handle = fopen($file, 'r');
        if (!$handle) {
            throw new \RuntimeException('Impossible d\'ouvrir le fichier');
        }

        fseek($handle, 0, SEEK_END);
        $fileSize = ftell($handle);
        
        $lines = [];
        $buffer = '';
        $position = $fileSize;
        $chunkSize = 8192;

        while ($position > 0 && count($lines) < $numLines) {
            $readSize = min($chunkSize, $position);
            $position -= $readSize;
            fseek($handle, $position);
            $chunk = fread($handle, $readSize);
            
            $buffer = $chunk . $buffer;
            $lineArray = explode("\n", $buffer);
            
            // Garder la premiÃ¨re partie comme buffer pour la prochaine itÃ©ration
            if ($position > 0) {
                $buffer = array_shift($lineArray);
            } else {
                $buffer = '';
            }
            
            // Ajouter les lignes complÃ¨tes (en ordre inverse)
            $lines = array_merge(array_reverse($lineArray), $lines);
            
            // Limiter le nombre de lignes en mÃ©moire
            if (count($lines) > $numLines * 2) {
                $lines = array_slice($lines, -$numLines * 2);
            }
        }

        fclose($handle);
        
        // Retourner les N derniÃ¨res lignes dans l'ordre inverse (plus rÃ©cent en premier)
        return array_slice(array_reverse($lines), 0, $numLines);
    }

    /**
     * Parse un tableau de lignes en logs structurÃ©s
     */
    private function parseLines(array $lines, ?string $levelFilter, ?string $searchFilter, ?string $userFilter, ?string $actionFilter, ?string $dateStartFilter = null, ?string $dateEndFilter = null): array
    {
        $logs = [];
        $currentLog = null;
        $lineNumber = 0;

        foreach ($lines as $line) {
            $lineNumber++;
            
            if (empty(trim($line))) {
                continue;
            }
            
            // Pattern pour dÃ©tecter une nouvelle ligne de log Symfony avec channel "user_action"
            if (preg_match('/^\[([^\]]+)\]\s+user_action\.(\w+):\s+(.+)$/', $line, $matches)) {
                // Si on avait un log en cours, on l'ajoute
                if ($currentLog !== null && $this->matchesFilters($currentLog, $levelFilter, $searchFilter, $userFilter, $actionFilter, $dateStartFilter, $dateEndFilter)) {
                    $logs[] = $currentLog;
                }

                // Nouveau log
                $datetime = $matches[1];
                $level = $matches[2];
                $rest = $matches[3];

                // Extraire le message et le contexte JSON
                $message = '';
                $context = [];
                $user = null;
                $action = null;
                $resource = null;
                $resourceId = null;

                // Chercher le contexte JSON dans la ligne
                if (preg_match('/^(.+?)\s+(\{.+\})\s*(\[\])?$/', $rest, $contentMatches)) {
                    $message = trim($contentMatches[1]);
                    try {
                        $contextData = json_decode($contentMatches[2], true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $context = $contextData;
                            $user = $context['user'] ?? $context['email'] ?? null;
                            $action = $context['action'] ?? null;
                            $resource = $context['resource'] ?? null;
                            $resourceId = $context['resource_id'] ?? null;
                        }
                    } catch (\Exception $e) {
                        // Ignore les erreurs de parsing JSON
                    }
                } else {
                    $message = trim($rest);
                }

                $currentLog = [
                    'datetime' => $this->formatDatetime($datetime),
                    'datetime_raw' => $datetime, // Conserver la datetime brute pour le filtrage
                    'action' => $action,
                    'message' => substr($message, 0, 500),
                    'resource' => $resource,
                    'resource_id' => $resourceId,
                    'details' => $context['details'] ?? null,
                    'user' => $user,
                    'line' => $lineNumber
                ];
            }
        }

        // Ajouter le dernier log s'il existe
        if ($currentLog !== null && $this->matchesFilters($currentLog, $levelFilter, $searchFilter, $userFilter, $actionFilter, $dateStartFilter, $dateEndFilter)) {
            $logs[] = $currentLog;
        }

        return $logs;
    }

    /**
     * Parse le fichier de log Symfony et retourne un tableau structurÃ©
     * OptimisÃ© pour lire les fichiers volumineux sans Ã©puiser la mÃ©moire
     */
    private function parseLogFile(string $logFile, ?string $levelFilter, ?string $searchFilter, ?string $userFilter, ?string $actionFilter = null): array
    {
        $logs = [];
        $handle = fopen($logFile, 'r');
        
        if (!$handle) {
            throw new \RuntimeException('Impossible d\'ouvrir le fichier de log');
        }

        $currentLog = null;
        $lineNumber = 0;
        $maxLogs = 10000; // Limite de sÃ©curitÃ© pour Ã©viter l'Ã©puisement mÃ©moire
        $logCount = 0;

        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            
            // Limite de sÃ©curitÃ©
            if ($logCount >= $maxLogs) {
                break;
            }
            
            // Pattern pour dÃ©tecter une nouvelle ligne de log Symfony
            // Format: [2025-11-18T14:30:25.123456+00:00] channel.LEVEL: message {"context":"data"} []
            if (preg_match('/^\[([^\]]+)\]\s+(\w+)\.(\w+):\s+(.+)$/', $line, $matches)) {
                // Si on avait un log en cours, on l'ajoute
                if ($currentLog !== null && $this->matchesFilters($currentLog, $levelFilter, $searchFilter, $userFilter, $actionFilter)) {
                    $logs[] = $currentLog;
                    $logCount++;
                }

                // Nouveau log
                $datetime = $matches[1];
                $channel = $matches[2];
                $level = $matches[3];
                $rest = $matches[4];

                // Extraire le message et le contexte JSON
                $message = '';
                $context = [];
                $user = null;

                // Chercher le contexte JSON dans la ligne
                if (preg_match('/^(.+?)\s+(\{.+\})\s*(\[\])?$/', $rest, $contentMatches)) {
                    $message = trim($contentMatches[1]);
                    try {
                        $contextData = json_decode($contentMatches[2], true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $context = $contextData;
                            // Extraire l'utilisateur si prÃ©sent dans le contexte
                            $user = $this->extractUserFromContext($context);
                        }
                    } catch (\Exception $e) {
                        // Ignore les erreurs de parsing JSON
                    }
                } else {
                    $message = trim($rest);
                }

                $currentLog = [
                    'datetime' => $this->formatDatetime($datetime),
                    'channel' => $channel,
                    'level' => $level,
                    'message' => substr($message, 0, 1000), // Limite la taille du message
                    'context' => $context,
                    'user' => $user,
                    'line' => $lineNumber
                ];
            } elseif ($currentLog !== null && strlen($currentLog['message']) < 5000) {
                // Ligne de continuation (stack trace, etc.) - limiter la taille
                $currentLog['message'] .= "\n" . substr($line, 0, 500);
            }
        }

        // Ajouter le dernier log s'il existe
        if ($currentLog !== null && $this->matchesFilters($currentLog, $levelFilter, $searchFilter, $userFilter, $actionFilter)) {
            $logs[] = $currentLog;
        }

        fclose($handle);

        return $logs;
    }

    /**
     * Extrait l'information utilisateur du contexte
     */
    private function extractUserFromContext(array $context): ?string
    {
        // Chercher les clÃ©s courantes pour l'utilisateur
        $userKeys = ['user', 'username', 'email', 'user_id', 'userId', 'user_email'];
        
        foreach ($userKeys as $key) {
            if (isset($context[$key])) {
                return is_array($context[$key]) 
                    ? ($context[$key]['email'] ?? $context[$key]['username'] ?? json_encode($context[$key]))
                    : (string) $context[$key];
            }
        }

        return null;
    }

    /**
     * Formate la datetime pour un affichage lisible
     */
    private function formatDatetime(string $datetime): string
    {
        try {
            $dt = new \DateTime($datetime);
            return $dt->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return $datetime;
        }
    }

    /**
     * VÃ©rifie si un log correspond aux filtres
     */
    private function matchesFilters(array $log, ?string $levelFilter, ?string $searchFilter, ?string $userFilter, ?string $actionFilter, ?string $dateStartFilter = null, ?string $dateEndFilter = null): bool
    {
        // Filtre par utilisateur (si spÃ©cifiÃ©)
        if ($userFilter !== null && (!isset($log['user']) || strcasecmp($log['user'], $userFilter) !== 0)) {
            return false;
        }

        // Filtre par type d'action
        if ($actionFilter && isset($log['action']) && strcasecmp($log['action'], $actionFilter) !== 0) {
            return false;
        }

        // Filtre par recherche dans le message ou resource
        if ($searchFilter) {
            $messageMatch = isset($log['message']) && stripos($log['message'], $searchFilter) !== false;
            $resourceMatch = isset($log['resource']) && stripos($log['resource'], $searchFilter) !== false;
            
            if (!$messageMatch && !$resourceMatch) {
                return false;
            }
        }

        // Filtre par date de dÃ©but
        if ($dateStartFilter && isset($log['datetime_raw'])) {
            try {
                $logDate = new \DateTime($log['datetime_raw']);
                $startDate = new \DateTime($dateStartFilter);
                
                if ($logDate < $startDate) {
                    return false;
                }
            } catch (\Exception $e) {
                // Si erreur de parsing, on ignore ce filtre
            }
        }

        // Filtre par date de fin
        if ($dateEndFilter && isset($log['datetime_raw'])) {
            try {
                $logDate = new \DateTime($log['datetime_raw']);
                $endDate = new \DateTime($dateEndFilter);
                // Ajouter 23:59:59 Ã  la date de fin si seulement la date est fournie
                if (strlen($dateEndFilter) === 10) {
                    $endDate->setTime(23, 59, 59);
                }
                
                if ($logDate > $endDate) {
                    return false;
                }
            } catch (\Exception $e) {
                // Si erreur de parsing, on ignore ce filtre
            }
        }

        return true;
    }
}
