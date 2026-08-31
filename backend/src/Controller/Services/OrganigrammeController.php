<?php

namespace App\Controller\Services;

use App\Entity\Service;
use App\Entity\User;
use App\Repository\ServiceRepository;
use App\Repository\UserRepository;
use App\Service\ApiResponseFactory;
use App\Service\ServiceHierarchyBuilder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/organigramme', methods: ['GET'])]
#[OA\Tag(name: 'Services')]
final class OrganigrammeController extends AbstractController
{
    public function __construct(
        private readonly ServiceRepository $serviceRepository,
        private readonly UserRepository $userRepository,
        private readonly ServiceHierarchyBuilder $hierarchyBuilder,
        private readonly ApiResponseFactory $apiResponse
    ) {
    }
    #[OA\Get(
        path: '/organigramme',
        summary: 'Récupérer l\'organigramme des services',
        description: "Retourne la structure hiérarchique des services. Filtres : type_organigramme_id, type_service (POSTE, SERVICE...), search (nom et sigle)."
    )]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10, maximum: 200))]
    #[OA\Parameter(name: 'service_id', in: 'query', schema: new OA\Schema(type: 'string', description: 'Filtrer par un ou plusieurs services spécifiques - séparés par des virgules pour multiples IDs (ex: 1,2,3)'))]
    #[OA\Parameter(name: 'is_active', in: 'query', schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'type_organigramme_id', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(
        name: 'type_service',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string'),
        description: 'Filtrer par type de service (ex. POSTE, SERVICE)',
        example: 'POSTE'
    )]
    #[OA\Parameter(
        name: 'search',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string'),
        description: 'Recherche sur nom et sigle',
        example: 'compta'
    )]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Services list returned successfully.',
                'data' => [
                    'meta' => ['current_page' => 1, 'limit' => 10, 'total_items' => 1, 'total_pages' => 1],
                    'data' => [
                        [
                            'id' => 2,
                            'nom' => 'Direction Générale',
                            'sigle' => 'DG',
                            'code' => 'DG',
                            'type_service' => 'SERVICE',
                            'ordre' => 1,
                            'is_active' => true,
                            'children' => [],
                        ],
                    ],
                ],
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => null])
    )]
    public function __invoke(
        Request $request
    ): JsonResponse {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 10);
        $limit = $limit < 1 ? 10 : ($limit > 200 ? 200 : $limit);

        $isActiveParam = $request->query->get('is_active');
        $typeOrganigrammeIdParam = $request->query->get('type_organigramme_id');
        $typeService = $request->query->get('type_service');
        $search = $request->query->get('search');
        $serviceIdParam = $request->query->get('service_id');

        $isActive = null;
        if (null !== $isActiveParam) {
            $isActive = filter_var($isActiveParam, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        $typeOrganigrammeId = null;
        if (null !== $typeOrganigrammeIdParam) {
            $typeOrganigrammeId = (int) $typeOrganigrammeIdParam;
        }

        // Traiter service_id pour accepter plusieurs IDs séparés par des virgules
        $serviceIds = null;
        if (null !== $serviceIdParam && $serviceIdParam !== '') {
            $parts = array_filter(array_map('trim', explode(',', $serviceIdParam)));
            if (!empty($parts)) {
                $serviceIds = array_filter(array_map('intval', $parts), function($id) {
                    return $id > 0;
                });
                $serviceIds = !empty($serviceIds) ? array_values($serviceIds) : null;
            }
        }

        // Si des service_ids sont spécifiés, on récupère ces services et leurs descendants
        if ($serviceIds) {
            $allServices = $this->getServicesWithDescendants($serviceIds, $isActive);
        } elseif ($typeOrganigrammeId) {
            $allServices = $this->serviceRepository->findAllByTypeOrganigramme($typeOrganigrammeId, $isActive);
        } else {
            $allServices = $this->serviceRepository->findRootServicesWithDescendants($isActive);
        }

                $serviceId = null;
        if (null !== $serviceIdParam) {
            $serviceId = (int) $serviceIdParam;
        }

        $allServices = $this->filterServices(
            $allServices,
            is_string($search) ? $search : null,
            is_string($typeService) ? $typeService : null
        );

        $rootServices = array_filter(
            $allServices,
            static function (Service $s) use ($allServices): bool {
                $parent = $s->getParent();
                if (null === $parent) {
                    return true;
                }
                foreach ($allServices as $candidate) {
                    if ($candidate->getId() === $parent->getId()) {
                        return false;
                    }
                }

                return true;
            }
        );
        $totalRoots = count($rootServices);

        $hierarchyData = $this->hierarchyBuilder->buildHierarchy($allServices, $isActive);
        $paginatedHierarchy = array_slice($hierarchyData, ($page - 1) * $limit, $limit);

        $payload = [
            'meta' => [
                'current_page' => $page,
                'limit' => $limit,
                'total_items' => $totalRoots,
                'total_pages' => (int) ceil($totalRoots / max(1, $limit)),
            ],
            'data' => $paginatedHierarchy,
        ];

        return $this->apiResponse->success($payload, Response::HTTP_OK, 'Services list returned successfully.');
    }

    /**
     * Récupère plusieurs services avec tous leurs descendants
     * 
     * @param array<int> $serviceIds Les IDs des services à récupérer
     * @param bool|null $isActive Filtrer par statut actif
     * @return Service[] Tableau contenant les services et tous leurs descendants
     */
    private function getServicesWithDescendants(array $serviceIds, ?bool $isActive = null): array
    {
        $result = [];
        $processedIds = [];

        foreach ($serviceIds as $serviceId) {
            // Récupérer le service principal
            $service = $this->serviceRepository->getServiceById($serviceId);
            if (!$service) {
                continue;
            }

            // Vérifier si le service est actif (si le filtre est appliqué)
            if (null !== $isActive && $service->isActive() !== $isActive) {
                continue;
            }

            // Éviter les doublons
            if (in_array($service->getId(), $processedIds)) {
                continue;
            }

            $result[] = $service;
            $processedIds[] = $service->getId();

            // Récupérer tous les descendants récursivement
            $descendants = $this->getAllDescendants($serviceId, $isActive);
            
            foreach ($descendants as $descendant) {
                if (!in_array($descendant->getId(), $processedIds)) {
                    $result[] = $descendant;
                    $processedIds[] = $descendant->getId();
                }
            }
        }
        
        return $result;
    }

    /**
     * Récupère récursivement tous les descendants d'un service
     * 
     * @param int $serviceId L'ID du service parent
     * @param bool|null $isActive Filtrer par statut actif
     * @return Service[] Liste des descendants
     */
    private function getAllDescendants(int $serviceId, ?bool $isActive = null): array
    {
        $descendants = [];
        
        // Récupérer les enfants directs
        $children = $this->serviceRepository->getDirectChildren($serviceId);
        
        foreach ($children as $child) {
            // Vérifier le filtre is_active si applicable
            if (null !== $isActive && $child->isActive() !== $isActive) {
                continue;
            }
            
            $descendants[] = $child;
            
            // Récursion pour les petits-enfants
            $grandChildren = $this->getAllDescendants($child->getId(), $isActive);
            $descendants = array_merge($descendants, $grandChildren);
        }
        
        return $descendants;
    }

    /**
     * @param Service[] $services
     *
     * @return Service[]
     */
    private function filterServices(array $services, ?string $search, ?string $typeService): array
    {
        $needle = null !== $search && '' !== trim($search) ? mb_strtolower(trim($search)) : null;
        $typeFilter = null !== $typeService && '' !== trim($typeService) ? mb_strtolower(trim($typeService)) : null;

        if (null === $needle && null === $typeFilter) {
            return $services;
        }

        // Récupérer tous les utilisateurs pour la recherche étendue
        $usersByService = [];
        if (null !== $needle) {
            $allUsers = $this->userRepository->findBy(['isDelete' => false]);
            foreach ($allUsers as $user) {
                if ($user->getService()) {
                    $serviceId = $user->getService()->getId();
                    if (!isset($usersByService[$serviceId])) {
                        $usersByService[$serviceId] = [];
                    }
                    $usersByService[$serviceId][] = $user;
                }
            }
        }

        return array_values(array_filter($services, static function (Service $service) use ($needle, $typeFilter, $usersByService): bool {
            if (null !== $typeFilter) {
                $current = mb_strtolower((string) $service->getTypeService());
                if ($current !== $typeFilter) {
                    return false;
                }
            }

            if (null !== $needle) {
                $nom = mb_strtolower((string) $service->getNom());
                $sigle = mb_strtolower((string) $service->getSigle());
                
                // Vérifier si le service correspond
                if (str_contains($nom, $needle) || str_contains($sigle, $needle)) {
                    return true;
                }

                // Vérifier si un utilisateur rattaché correspond
                if (isset($usersByService[$service->getId()])) {
                    foreach ($usersByService[$service->getId()] as $user) {
                        $firstName = mb_strtolower((string) $user->getFirstName());
                        $lastName = mb_strtolower((string) $user->getLastName());
                        $matricule = mb_strtolower((string) $user->getMatricule());
                        
                        if (str_contains($firstName, $needle) || 
                            str_contains($lastName, $needle) || 
                            str_contains($matricule, $needle)) {
                            return true;
                        }
                    }
                }

                return false;
            }

            return true;
        }));
    }
}
