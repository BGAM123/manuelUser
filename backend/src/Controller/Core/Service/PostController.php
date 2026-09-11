<?php

namespace App\Controller\Core\Service;

use App\Entity\Core\Service;
use App\Repository\Core\UserRepository;
use App\Repository\Core\ServiceRepository;
use App\Service\Core\CrudService;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Service")]
class PostController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private UserRepository $userRepository,
        private ServiceRepository $serviceRepository,
        private UserActionLoggerService $actionLogger,
    ) {}

    #[Route('/core/service', name: 'app_core_service_post', methods: ['POST'])]
    #[OA\Post(
        path: '/core/service',
        summary: 'Créer un nouveau service',
        description: 'Crée un nouveau service avec ses informations de base et ses liens éventuels.',
        tags: ['Service'],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'Direction Générale'),
                    new OA\Property(property: 'sigle', type: 'string', example: 'DG'),
                    new OA\Property(property: 'emailService', type: 'string', example: 'contact@dg.gov'),
                    new OA\Property(property: 'telephone', type: 'string', example: '0022860000000'),
                    new OA\Property(property: 'numeroOrdre', type: 'integer', example: 1, description: 'Numéro d\'ordre pour le tri'),
                    new OA\Property(property: 'typeService', type: 'string', example: 'service', description: 'Type de service (poste ou service)'),
                    new OA\Property(property: 'isActive', type: 'boolean', example: true),
                    new OA\Property(
                        property: 'isDirection', 
                        type: 'boolean', 
                        example: true,
                        description: 'Indique si le service est une direction (true) ou un simple service (false)'
                    ),
                    new OA\Property(
                        property: 'isVisibleInTransmission', 
                        type: 'boolean', 
                        example: false,
                        description: 'Indique si le service est visible lors de la première transmission de courrier'
                    ),
                    new OA\Property(property: 'idChefService', type: 'integer', example: 4, description: 'ID du chef de service'),
                    new OA\Property(property: 'idServiceParent', type: 'integer', example: 2, description: 'ID du service parent'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Service crée avec succès',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'nom', type: 'string', example: 'Direction Générale'),
                        new OA\Property(property: 'sigle', type: 'string', example: 'DG'),
                        new OA\Property(property: 'emailService', type: 'string', example: 'contact@dg.gov'),
                        new OA\Property(property: 'telephone', type: 'string', example: '0022860000000'),
                        new OA\Property(property: 'numeroOrdre', type: 'integer', example: 1),
                        new OA\Property(property: 'typeService', type: 'string', example: 'service'),
                        new OA\Property(property: 'isActive', type: 'boolean', example: true),
                        new OA\Property(property: 'isDirection', type: 'boolean', example: true),
                        new OA\Property(property: 'isVisibleInTransmission', type: 'boolean', example: false),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Requête invalide'),
            new OA\Response(response: 401, description: 'Accès non autorisé')
        ]
    )]
    public function create(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'PostService');

        $data = json_decode($request->getContent(), true) ?? [];
        $this->functionService->validate($data);

        try {
            $service = new Service();

            // Gestion des relations
            $idChefService = !empty($data['idChefService'])
                ? $this->userRepository->find($data['idChefService'])
                : null;

            $idServiceParent = !empty($data['idServiceParent'])
                ? $this->serviceRepository->find($data['idServiceParent'])
                : null;

            // âœ… Validation : VÃ©rifier qu'un parent n'a pas dÃ©jÃ  un enfant avec le mÃªme nom
            if ($idServiceParent !== null && !empty($data['nom'])) {
                $hasChildWithSameName = $this->serviceRepository->hasChildWithSameName(
                    $idServiceParent,
                    $data['nom']
                );

                if ($hasChildWithSameName) {
                    return $this->json([
                        'code' => 400,
                        'message' => sprintf(
                            'Le service parent "%s" a déjà un enfant nommé "%s". Un parent ne peut pas avoir plusieurs enfants avec le même nom.',
                            $idServiceParent->getNom(),
                            trim($data['nom'])
                        )
                    ], 400);
                }
            }

            $data['chefService'] = $idChefService;
            $data['idServiceParent'] = $idServiceParent;

            // âœ… Gestion des champs boolÃ©ens
            // Gestion du champ isActive
            if (isset($data['isActive'])) {
                $data['active'] = (bool) $data['isActive'];
            }

            // Si non fournis, on garde les valeurs par dÃ©faut de l'entitÃ©
            if (isset($data['isDirection'])) {
                $data['direction'] = (bool) $data['isDirection'];
            }

            if (isset($data['isVisibleInTransmission'])) {
                $data['visibleInTransmission'] = (bool) $data['isVisibleInTransmission'];
            }

            // CrÃ©ation de l'entitÃ©
            $service = $this->crudService->postEntity($service, $data);

            // Logger la crÃ©ation avec toutes les donnÃ©es
            $this->actionLogger->logCreate(
                'Service',
                $service->getId(),
                'Création d\'un nouveau service',
                [
                    'service' => [
                        'id' => $service->getId(),
                        'nom' => $service->getNom(),
                        'sigle' => $service->getSigle(),
                        'emailService' => $service->getEmailService(),
                        'telephone' => $service->getTelephone(),
                        'numeroOrdre' => $service->getNumeroOrdre(),
                        'typeService' => $service->getTypeService(),
                        'isActive' => $service->isActive(),
                        'isDirection' => $service->isDirection(),
                        'isVisibleInTransmission' => $service->isVisibleInTransmission(),
                        'chefService' => $service->getChefService()?->getFullName(),
                        'serviceParent' => $service->getIdServiceParent()?->getNom(),
                    ]
                ]
            );

            return $this->json($service, 201, [], ['groups' => 'Get:Service']);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}