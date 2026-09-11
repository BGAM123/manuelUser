<?php

namespace App\Controller\Core\statistique;

use App\Entity\Core\Service;
use App\Entity\Core\User;
use App\Service\Core\StatistiqueService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;
use Nelmio\ApiDocBundle\Annotation\Model;
use Symfony\Component\HttpFoundation\Response;

#[Route('/core/statistics', name: 'api_core_statistics_')]
class StatistiqueController extends AbstractController
{
    public function __construct(
        private StatistiqueService $statistiqueService
    ) {
    }

    #[Route('/global', name: 'global', methods: ['GET'])]
    #[OA\Get(
        path: '/core/statistics/global',
        operationId: 'getGlobalStatistics',
        summary: 'Récupérer les statistiques globales du système',
        security: [["bearerAuth" => []]],
        tags: ['Statistics']
    )]
    #[OA\Parameter(
        name: 'date_type',
        in: 'query',
        description: 'Type de date pour le filtrage',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['arrivee', 'enregistrement', 'signature', 'instruction', 'reponse'])
    )]
    #[OA\Parameter(
        name: 'date_debut',
        in: 'query',
        description: 'Date de début (YYYY-MM-DD)',
        required: false,
        schema: new OA\Schema(type: 'string', format: 'date')
    )]
    #[OA\Parameter(
        name: 'date_fin',
        in: 'query',
        description: 'Date de fin (YYYY-MM-DD)',
        required: false,
        schema: new OA\Schema(type: 'string', format: 'date')
    )]
    #[OA\Parameter(
        name: 'service_id',
        in: 'query',
        description: 'ID du service',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'user_id',
        in: 'query',
        description: 'ID de l\'utilisateur',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'correspondant_id',
        in: 'query',
        description: 'ID du correspondant',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'type_courrier_id',
        in: 'query',
        description: 'ID du type de courrier',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'statut',
        in: 'query',
        description: 'Statut du courrier',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'priorite',
        in: 'query',
        description: 'Priorité du courrier',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['haute', 'normal', 'basse'])
    )]
    #[OA\Parameter(
        name: 'is_confidentiel',
        in: 'query',
        description: 'Filtrer les courriers confidentiels',
        required: false,
        schema: new OA\Schema(type: 'boolean')
    )]
    #[OA\Parameter(
        name: 'categorie_id',
        in: 'query',
        description: 'ID de la catégorie',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Statistiques globales récupérées avec succès',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                'success' => new OA\Property(property: 'success', type: 'boolean', example: true),
                'filters_applied' => new OA\Property(
                    property: 'filters_applied',
                    type: 'object',
                    properties: [
                        'date_type' => new OA\Property(property: 'date_type', type: 'string', example: 'arrivee'),
                        'date_debut' => new OA\Property(property: 'date_debut', type: 'string', example: '2025-01-01'),
                        'date_fin' => new OA\Property(property: 'date_fin', type: 'string', example: '2025-12-31'),
                        'service_id' => new OA\Property(property: 'service_id', type: 'integer', example: 5),
                        'user_id' => new OA\Property(property: 'user_id', type: 'integer', example: 12),
                        'correspondant_id' => new OA\Property(property: 'correspondant_id', type: 'integer', example: 3),
                        'type_courrier_id' => new OA\Property(property: 'type_courrier_id', type: 'integer', example: 2),
                        'statut' => new OA\Property(property: 'statut', type: 'string', example: 'En attente'),
                        'priorite' => new OA\Property(property: 'priorite', type: 'string', example: 'haute'),
                        'is_confidentiel' => new OA\Property(property: 'is_confidentiel', type: 'boolean', example: false),
                        'categorie_id' => new OA\Property(property: 'categorie_id', type: 'integer', example: 1)
                    ]
                ),
                'data' => new OA\Property(
                    property: 'data',
                    type: 'object',
                    properties: [
                        'courrier_arrive' => new OA\Property(
                            property: 'courrier_arrive',
                            type: 'object',
                            properties: [
                                'total' => new OA\Property(property: 'total', type: 'integer', example: 1245),
                                'par_statut' => new OA\Property(property: 'par_statut', type: 'object'),
                                'par_priorite' => new OA\Property(property: 'par_priorite', type: 'object'),
                                'par_service' => new OA\Property(property: 'par_service', type: 'object'),
                                'par_type_courrier' => new OA\Property(property: 'par_type_courrier', type: 'object'),
                                'par_provenance' => new OA\Property(property: 'par_provenance', type: 'object'),
                                'confidentiels' => new OA\Property(property: 'confidentiels', type: 'integer', example: 78),
                                'geles' => new OA\Property(property: 'geles', type: 'integer', example: 12),
                                'par_mois' => new OA\Property(property: 'par_mois', type: 'object'),
                                'details' => new OA\Property(property: 'details', type: 'array', items: new OA\Items())
                            ]
                        ),
                        'courrier_depart' => new OA\Property(property: 'courrier_depart', type: 'object'),
                        'transmissions' => new OA\Property(property: 'transmissions', type: 'object'),
                        'reponses' => new OA\Property(property: 'reponses', type: 'object'),
                        'utilisateurs' => new OA\Property(property: 'utilisateurs', type: 'object'),
                        'roles' => new OA\Property(property: 'roles', type: 'object'),
                        'services' => new OA\Property(property: 'services', type: 'object'),
                        'correspondants' => new OA\Property(property: 'correspondants', type: 'object'),
                        'categories' => new OA\Property(property: 'categories', type: 'object'),
                        'types_courrier' => new OA\Property(property: 'types_courrier', type: 'object'),
                        'relances' => new OA\Property(property: 'relances', type: 'object')
                    ]
                ),
                'generated_at' => new OA\Property(property: 'generated_at', type: 'string', format: 'date-time')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Erreur dans les paramètres de filtrage',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                'success' => new OA\Property(property: 'success', type: 'boolean', example: false),
                'message' => new OA\Property(property: 'message', type: 'string', example: 'Paramètres de filtrage invalides')
            ]
        )
    )]
    #[OA\Response(
        response: 500,
        description: 'Erreur serveur lors du calcul des statistiques',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                'success' => new OA\Property(property: 'success', type: 'boolean', example: false),
                'message' => new OA\Property(property: 'message', type: 'string', example: 'Erreur lors du calcul des statistiques')
            ]
        )
    )]
    public function globalStatistics(Request $request): JsonResponse
    {
        $debug = $request->query->getBoolean('debug', false);
        $previousHandler = null;
        if ($debug) {
            $previousHandler = set_error_handler(function (int $severity, string $message, string $file, int $line): never {
                throw new \ErrorException($message, 0, $severity, $file, $line);
            });
        }

        try {
            // RÃ©cupÃ©ration des filtres depuis les query parameters
            $filters = $request->query->all();
            $filters = $this->normalizeScalarFilters($filters);

            // Validation et nettoyage des filtres
            $validatedFilters = $this->validateAndCleanFilters($filters);

            // Scope des statistiques par service de l'utilisateur connecte + services enfants
            $user = $this->getUser();
            if ($user instanceof User) {
                $serviceIds = $this->getServiceScopeIdsForUser($user);
                if (!empty($serviceIds)) {
                    if (isset($validatedFilters['service_id'])) {
                        $requestedServiceId = (int) $validatedFilters['service_id'];
                        if (in_array($requestedServiceId, $serviceIds, true)) {
                            $validatedFilters['service_ids'] = [$requestedServiceId];
                        } else {
                            $validatedFilters['service_ids'] = $serviceIds;
                        }
                        unset($validatedFilters['service_id']);
                    } else {
                        $validatedFilters['service_ids'] = $serviceIds;
                    }
                }
            }
            
            // GÃ©nÃ©ration des statistiques
            $statistics = $this->statistiqueService->generateGlobalStatistics($validatedFilters);
            
            return new JsonResponse([
                'success' => true,
                'filters_applied' => $validatedFilters,
                'data' => $statistics,
                'generated_at' => (new \DateTime())->format('c')
            ], Response::HTTP_OK);
            
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
            
        } catch (\Exception $e) {
            if ($debug && $e instanceof \ErrorException) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Erreur lors du calcul des statistiques: ' . $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors du calcul des statistiques: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        } finally {
            if ($debug && $previousHandler !== null) {
                restore_error_handler();
            }
        }
    }
    
    /**
     * Valide et nettoie les filtres reÃ§us
     */
    private function validateAndCleanFilters(array $filters): array
    {
        $validatedFilters = [];
        
        // date_type
        if (isset($filters['date_type'])) {
            $validDateTypes = ['arrivee', 'enregistrement', 'signature', 'instruction', 'reponse'];
            if (in_array($filters['date_type'], $validDateTypes)) {
                $validatedFilters['date_type'] = $filters['date_type'];
            } else {
                throw new \InvalidArgumentException('date_type invalide. Valeurs acceptées: ' . implode(', ', $validDateTypes));
            }
        }
        
        // date_debut et date_fin
        if (isset($filters['date_debut'])) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_debut'])) {
                $validatedFilters['date_debut'] = $filters['date_debut'];
            } else {
                throw new \InvalidArgumentException('date_debut doit être au format YYYY-MM-DD');
            }
        }
        
        if (isset($filters['date_fin'])) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_fin'])) {
                $validatedFilters['date_fin'] = $filters['date_fin'];
            } else {
                throw new \InvalidArgumentException('date_fin doit être au format YYYY-MM-DD');
            }
        }
        
        // IDs numériques
        $numericFields = ['service_id', 'user_id', 'correspondant_id', 'type_courrier_id', 'categorie_id'];
        foreach ($numericFields as $field) {
            if (isset($filters[$field])) {
                if (is_numeric($filters[$field]) && (int)$filters[$field] > 0) {
                    $validatedFilters[$field] = (int)$filters[$field];
                } else {
                    throw new \InvalidArgumentException("$field doit être un entier positif");
                }
            }
        }
        
        // statut (string libre)
        if (isset($filters['statut']) && !empty(trim($filters['statut']))) {
            $validatedFilters['statut'] = trim($filters['statut']);
        }
        
        // priorite
        if (isset($filters['priorite'])) {
            $validPriorites = ['haute', 'normal', 'basse'];
            if (in_array($filters['priorite'], $validPriorites)) {
                $validatedFilters['priorite'] = $filters['priorite'];
            } else {
                throw new \InvalidArgumentException('priorite invalide. Valeurs acceptées: ' . implode(', ', $validPriorites));
            }
        }
        
        // is_confidentiel
        if (isset($filters['is_confidentiel'])) {
            $validatedFilters['is_confidentiel'] = filter_var($filters['is_confidentiel'], FILTER_VALIDATE_BOOLEAN);
        }
        
        return $validatedFilters;
    }

    /**
     * Normalise les filtres issus de la requÃƒÂªte pour ÃƒÂ©viter les valeurs tableaux
     */
    private function normalizeScalarFilters(array $filters): array
    {
        foreach ($filters as $key => $value) {
            if ($key === 'service_ids') {
                continue;
            }
            if (is_array($value)) {
                $filters[$key] = reset($value);
            }
        }

        return $filters;
    }

    /**
     * Retourne les IDs du service de l'utilisateur et de tous ses services enfants
     */
    private function getServiceScopeIdsForUser(User $user): array
    {
        $service = $user->getIdService();
        if (!$service instanceof Service) {
            return [];
        }

        $ids = [];
        $stack = [$service];

        while (!empty($stack)) {
            /** @var Service $current */
            $current = array_pop($stack);
            $id = $current->getId();
            if ($id === null || isset($ids[$id])) {
                continue;
            }
            $ids[$id] = true;

            foreach ($current->getServiceEnfants() as $child) {
                $stack[] = $child;
            }
        }

        return array_values(array_map('intval', array_keys($ids)));
    }
}

