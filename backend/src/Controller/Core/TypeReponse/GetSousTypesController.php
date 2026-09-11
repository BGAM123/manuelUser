<?php

namespace App\Controller\Core\TypeReponse;

use App\Repository\Core\TypeReponseRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "TypeReponse")]
class GetSousTypesController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker,
        private TypeReponseRepository $typeReponseRepository,
    ) {}

    #[Route('/core/type-reponse/{id}/sous-types', name: 'app_core_type_reponse_get_sous_types', methods: ['GET'])]
    #[OA\Get(
        path: '/core/type-reponse/{id}/sous-types',
        summary: 'Récupérer les sous-types d\'un type de réponse',
        tags: ['TypeReponse'],
        description: "Récupère tous les sous-types d'un type de réponse parent donné.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID du type parent', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des sous-types récupérée.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 2),
                            new OA\Property(property: 'nom', type: 'string', example: 'Accusée de réception'),
                            new OA\Property(property: 'description', type: 'string', example: 'Confirmation de réception'),
                            new OA\Property(property: 'isActive', type: 'boolean', example: true),
                        ]
                    )
                )
            ),
            new OA\Response(response: 404, description: 'Type parent non trouvée.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function getSousTypes(int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetSousTypesTypeReponse');

        $parentType = $this->typeReponseRepository->find($id);
        
        if (!$parentType) {
            return $this->json(['code' => 404, 'message' => 'Type parent non trouvée.'], 404);
        }

        $sousTypes = $this->typeReponseRepository->createQueryBuilder('t')
            ->where('t.typeParent = :parent')
            ->andWhere('t.isDelete = false')
            ->andWhere('t.isActive = true')
            ->setParameter('parent', $parentType)
            ->orderBy('t.nom', 'ASC')
            ->getQuery()
            ->getResult();

        $formattedData = array_map(function ($typeReponse) {
            return [
                'id' => $typeReponse->getId(),
                'nom' => $typeReponse->getNom(),
                'description' => $typeReponse->getDescription(),
                'isActive' => $typeReponse->isActive(),
            ];
        }, $sousTypes);

        return $this->json($formattedData, 200);
    }
}
