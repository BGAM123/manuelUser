<?php

namespace App\Controller\Core\BordereauTransmission;

use App\Entity\Core\BordereauTransmission;
use App\Service\Core\AccessCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "BordereauTransmission")]
class DeleteController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker,
        private EntityManagerInterface $entityManager,
    ) {}

    #[Route('/core/bordereau-transmission', name: 'app_core_bordereau_transmission_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/core/bordereau-transmission',
        summary: 'Supprimer un ou plusieurs bordereaux de transmission',
        tags: ['BordereauTransmission'],
        description: "Supprime un ou plusieurs bordereaux de transmission. Peut supprimer un seul bordereau, plusieurs bordereaux par leurs IDs, ou tous les bordereaux.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'ids', 
                        type: 'array',
                        items: new OA\Items(type: 'integer'),
                        example: [1, 2, 3, 5],
                        description: 'Liste des IDs des bordereaux à  supprimer. Si vide ou absent avec deleteAll=true, tous les bordereaux seront supprimées.'
                    ),
                    new OA\Property(
                        property: 'deleteAll', 
                        type: 'boolean', 
                        example: false,
                        description: 'Si true, supprime tous les bordereaux de transmission. Attention : cette action est irreversible !'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Bordereau(x) supprimée(s) avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: '3 bordereau(x) de transmission supprimé(s) avec succès.'),
                        new OA\Property(property: 'deletedCount', type: 'integer', example: 3, description: 'Nombre de bordereaux supprimÃ©s'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Requête invalide - paramètres manquants ou incorrects.'),
            new OA\Response(response: 401, description: 'Accès non autorisé - authentification requise.'),
            new OA\Response(response: 403, description: 'Accès interdit - permissions insuffisantes.'),
            new OA\Response(response: 404, description: 'Aucun bordereau trouvé avec les IDs fournis.')
        ]
    )]
    public function delete(Request $request): Response
    {
        // VÃ©rification des permissions
        $this->accessChecker->checker(
            $this->getUser(), 
            $this->isGranted('ROLE_ADMIN'), 
            'DeleteBordereauTransmission'
        );

        try {
            // RÃ©cupÃ©ration des donnÃ©es
            $data = json_decode($request->getContent(), true);
            
            // VÃ©rifier si on veut tout supprimer
            $deleteAll = $data['deleteAll'] ?? false;
            $ids = $data['ids'] ?? [];

            // Validation : soit des IDs, soit deleteAll
            if (empty($ids) && !$deleteAll) {
                return $this->json([
                    'code' => 400,
                    'message' => 'Vous devez fournir soit une liste d\'IDs, soit activer deleteAll=true.'
                ], 400);
            }

            $deletedCount = 0;

            if ($deleteAll) {
                // Supprimer tous les bordereaux
                $qb = $this->entityManager->getRepository(BordereauTransmission::class)
                    ->createQueryBuilder('bt')
                    ->delete();
                
                $deletedCount = $qb->getQuery()->execute();

                return $this->json([
                    'code' => 200,
                    'message' => "Tous les bordereaux de transmission ont été supprimés avec succès.",
                    'deletedCount' => $deletedCount
                ], 200);

            } else {
                // Supprimer les bordereaux spÃ©cifiques par IDs
                if (!is_array($ids) || empty($ids)) {
                    return $this->json([
                        'code' => 400,
                        'message' => 'Le champ "ids" doit être un tableau non vide d\'identifiants.'
                    ], 400);
                }

                // Vérifier que tous les IDs sont des entiers
                foreach ($ids as $id) {
                    if (!is_numeric($id)) {
                        return $this->json([
                            'code' => 400,
                            'message' => 'Tous les IDs doivent être des nombres entiers.'
                        ], 400);
                    }
                }

                // Supprimer les bordereaux
                $qb = $this->entityManager->getRepository(BordereauTransmission::class)
                    ->createQueryBuilder('bt')
                    ->delete()
                    ->where('bt.id IN (:ids)')
                    ->setParameter('ids', $ids);
                
                $deletedCount = $qb->getQuery()->execute();

                if ($deletedCount === 0) {
                    return $this->json([
                        'code' => 404,
                        'message' => 'Aucun bordereau de transmission trouvé avec les IDs fournis.'
                    ], 404);
                }

                return $this->json([
                    'code' => 200,
                    'message' => "$deletedCount bordereau(x) de transmission supprimé(s) avec succès.",
                    'deletedCount' => $deletedCount
                ], 200);
            }

        } catch (\Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => 'Une erreur est survenue lors de la suppression des bordereaux.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
