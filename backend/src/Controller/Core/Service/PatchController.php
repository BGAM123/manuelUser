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
class PatchController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private UserRepository $userRepository,
        private ServiceRepository $serviceRepository,
        private UserActionLoggerService $actionLogger,
    ) {}

    #[Route('/core/service/{id<([1-9][0-9]*)>}', name: 'app_core_service_patch', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/service/{id}',
        summary: 'Modifier un service existant',
        description: 'Met à jour les informations d\'un service existant (nom, sigle, chef, etc.)',
        tags: ['Service'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant du service à modifier',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'Direction des Finances'),
                    new OA\Property(property: 'sigle', type: 'string', example: 'DF'),
                    new OA\Property(property: 'emailService', type: 'string', example: 'finances@dg.gov'),
                    new OA\Property(property: 'telephone', type: 'string', example: '0022860123456'),
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
                    new OA\Property(property: 'idChefService', type: 'integer', example: 5),
                    new OA\Property(property: 'idServiceParent', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Service mis à jour avec succès',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Mise à jour réussie'),
                        new OA\Property(property: 'data', type: 'object')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Service non trouvé'),
            new OA\Response(response: 401, description: 'Accès non autorisé')
        ]
    )]
    public function update(Request $request, ?Service $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'PatchService');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Service non trouvé.'], 404);
        }

        // Sauvegarder les anciennes valeurs pour le log
        $oldData = [
            'id' => $entity->getId(),
            'nom' => $entity->getNom(),
            'sigle' => $entity->getSigle(),
            'emailService' => $entity->getEmailService(),
            'telephone' => $entity->getTelephone(),
            'numeroOrdre' => $entity->getNumeroOrdre(),
            'typeService' => $entity->getTypeService(),
            'isActive' => $entity->isActive(),
            'isDirection' => $entity->isDirection(),
            'isVisibleInTransmission' => $entity->isVisibleInTransmission(),
            'chefService' => $entity->getChefService()?->getFullName(),
            'serviceParent' => $entity->getIdServiceParent()?->getNom(),
        ];

        $data = json_decode($request->getContent(), true) ?? [];
        $this->functionService->validate($data);

        try {
            // Gestion des relations
            $idChefService = !empty($data['idChefService'])
                ? $this->userRepository->find($data['idChefService'])
                : $entity->getChefService();

            $idServiceParent = !empty($data['idServiceParent'])
                ? $this->serviceRepository->find($data['idServiceParent'])
                : $entity->getIdServiceParent();

            // âœ… Validation : VÃ©rifier qu'un parent n'a pas dÃ©jÃ  un enfant avec le mÃªme nom
            // On vÃ©rifie si le nom OU le parent a changÃ©
            $nomToCheck = $data['nom'] ?? $entity->getNom();
            
            if ($idServiceParent !== null && !empty($nomToCheck)) {
                $hasChildWithSameName = $this->serviceRepository->hasChildWithSameName(
                    $idServiceParent,
                    $nomToCheck,
                    $entity->getId() // Exclure le service actuel de la recherche
                );

                if ($hasChildWithSameName) {
                    return $this->json([
                        'code' => 400,
                        'message' => sprintf(
                            'Le service parent "%s" a déjà un enfant nommé "%s". Un parent ne peut pas avoir plusieurs enfants avec le même nom.',
                            $idServiceParent->getNom(),
                            trim($nomToCheck)
                        )
                    ], 400);
                }
            }

            $data['chefService'] = $idChefService;
            $data['idServiceParent'] = $idServiceParent;
            
            // âœ… FIX : Mapper correctement les propriÃ©tÃ©s boolÃ©ennes
            if (isset($data['isDirection'])) {
                $data['direction'] = (bool) $data['isDirection'];
                unset($data['isDirection']); // Retirer l'ancienne clÃ©
            }

            if (isset($data['isVisibleInTransmission'])) {
                $data['visibleInTransmission'] = (bool) $data['isVisibleInTransmission'];
                unset($data['isVisibleInTransmission']); // Retirer l'ancienne clÃ©
            }
            
            if (isset($data['isActive'])) {
                $data['active'] = (bool) $data['isActive'];
                unset($data['isActive']); // Retirer l'ancienne clÃ©
            }
            
            // Mise Ã  jour de l'entitÃ©
            $entity = $this->crudService->patchEntity($entity, $data);

            // Logger la mise Ã  jour avec anciennes et nouvelles valeurs
            $this->actionLogger->logUpdate(
                'Service',
                $entity->getId(),
                'Mise à jour d\'un service',
                [
                    'before' => $oldData,
                    'after' => [
                        'id' => $entity->getId(),
                        'nom' => $entity->getNom(),
                        'sigle' => $entity->getSigle(),
                        'emailService' => $entity->getEmailService(),
                        'telephone' => $entity->getTelephone(),
                        'numeroOrdre' => $entity->getNumeroOrdre(),
                        'typeService' => $entity->getTypeService(),
                        'isActive' => $entity->isActive(),
                        'isDirection' => $entity->isDirection(),
                        'isVisibleInTransmission' => $entity->isVisibleInTransmission(),
                        'chefService' => $entity->getChefService()?->getFullName(),
                        'serviceParent' => $entity->getIdServiceParent()?->getNom(),
                    ]
                ]
            );

            return $this->json([
                'code' => 200,
                'message' => 'Mise à jour réussie',
                'data' => $entity
            ], 200, [], ['groups' => 'Get:Service']);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}
