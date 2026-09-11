<?php

namespace App\Controller\Core\Service;

use App\Repository\Core\ServiceRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Service")]
class GetChildrenController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker,
        private ServiceRepository $serviceRepository,
    ) {}

    #[Route('/core/service/{id}/children', name: 'app_core_service_get_children', methods: ['GET'])]
    #[OA\Get(
        path: '/core/service/{id}/children',
        summary: 'Récupérer les services enfants directs',
        description: 'Retourne uniquement les services enfants directs d\'un service parent donné (sans les petits-enfants).',
        tags: ['Service'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID du service parent pour lequel récupérer les enfants',
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des services enfants récupérée avec succès',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'parentServiceId', type: 'integer', example: 1),
                        new OA\Property(property: 'parentServiceName', type: 'string', example: 'Direction Générale'),
                        new OA\Property(
                            property: 'children',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 3),
                                    new OA\Property(property: 'nom', type: 'string', example: 'Direction Administrative et Financière'),
                                    new OA\Property(property: 'sigle', type: 'string', example: 'DAF'),
                                    new OA\Property(property: 'emailService', type: 'string', example: 'contact@daf.gov'),
                                    new OA\Property(property: 'telephone', type: 'string', example: '0022860000000'),
                                    new OA\Property(property: 'numeroOrdre', type: 'integer', example: 1, description: 'Numéro d\'ordre pour le tri'),
                                    new OA\Property(property: 'typeService', type: 'string', example: 'service', description: 'Type de service (poste ou service)'),
                                    new OA\Property(property: 'isActive', type: 'boolean', example: true),
                                    new OA\Property(property: 'isDirection', type: 'boolean', example: true),
                                    new OA\Property(property: 'isVisibleInTransmission', type: 'boolean', example: false),
                                    new OA\Property(
                                        property: 'chefService',
                                        type: 'object',
                                        nullable: true,
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 10),
                                            new OA\Property(property: 'nom', type: 'string', example: 'DUPONT'),
                                            new OA\Property(property: 'prenom', type: 'string', example: 'Jean'),
                                        ]
                                    ),
                                    new OA\Property(
                                        property: 'parentId',
                                        type: 'integer',
                                        nullable: true,
                                        example: 1,
                                        description: 'ID du service parent direct'
                                    ),
                                ]
                            )
                        ),
                        new OA\Property(property: 'totalChildren', type: 'integer', example: 5)
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé'),
            new OA\Response(response: 404, description: 'Service non trouvé')
        ]
    )]
    public function getChildren(int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetChildrenService');

        try {
            $service = $this->serviceRepository->find($id);

            if (!$service) {
                return $this->json([
                    'code' => 404,
                    'message' => 'Service non trouvé'
                ], 404);
            }

            $children = [];
            
            // RÃ©cupÃ©rer uniquement les enfants directs non supprimÃ©s
            foreach ($service->getServiceEnfants() as $child) {
                // Filtrer les services supprimÃ©s
                if ($child->isDelete()) {
                    continue;
                }

                $childData = [
                    'id' => $child->getId(),
                    'nom' => $child->getNom(),
                    'sigle' => $child->getSigle(),
                    'emailService' => $child->getEmailService(),
                    'telephone' => $child->getTelephone(),
                    'numeroOrdre' => $child->getNumeroOrdre(),
                    'typeService' => $child->getTypeService(),
                    'isActive' => $child->isActive(),
                    'isDirection' => $child->isDirection(),
                    'isVisibleInTransmission' => $child->isVisibleInTransmission(),
                    'parentId' => $service->getId(),
                ];

                // Ajouter les informations du chef de service si disponible
                if ($child->getChefService()) {
                    $chef = $child->getChefService();
                    $childData['chefService'] = [
                        'id' => $chef->getId(),
                        'nom' => $chef->getLastName(),
                        'prenom' => $chef->getFirstName(),
                        'email' => $chef->getEmail(),
                    ];
                } else {
                    $childData['chefService'] = null;
                }

                $children[] = $childData;
            }

            // Trier les enfants par numÃ©ro d'ordre croissant
            usort($children, fn($a, $b) => $a['numeroOrdre'] <=> $b['numeroOrdre']);

            return $this->json([
                'parentServiceId' => $service->getId(),
                'parentServiceName' => $service->getNom(),
                'children' => $children,
                'totalChildren' => count($children)
            ], 200);

        } catch (\Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => 'Erreur lors de la récupération des services enfants: ' . $e->getMessage()
            ], 500);
        }
    }
}
