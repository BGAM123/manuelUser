<?php

namespace App\Controller\Core\Courrier;

use App\Repository\Cour\CourrierRepository;
use App\Service\Core\AccessCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Psr\Log\LoggerInterface;

#[OA\Tag(name: "CourrierArrive")]
class DeleteAllController extends AbstractController
{
    public function __construct(
        private CourrierRepository $courrierRepository,
        private EntityManagerInterface $entityManager,
        private AccessCheckerService $accessChecker,
        private ?LoggerInterface $logger = null,
    ) {}

    #[Route('/core/courrier/delete-all', name: 'app_core_courrier_delete_all', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/core/courrier/delete-all',
        summary: 'Supprimer tous les courriers arrivés',
        description: 'Supprime TOUS les courriers arrivés de la base de données. ATTENTION : Cette action est IRRÉVERSIBLE et ne peut pas être annulée !',
        tags: ['CourrierArrive'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'confirm',
                in: 'query',
                required: true,
                description: 'Confirmation de suppression. Doit être exactement "DELETE_ALL_COURRIERS"',
                schema: new OA\Schema(type: 'string', example: 'DELETE_ALL_COURRIERS')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tous les courriers ont été supprimés avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Tous les courriers ont été supprimés avec succès'),
                        new OA\Property(property: 'count', type: 'integer', example: 150, description: 'Nombre de courriers supprimés'),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Confirmation manquante ou invalide.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Confirmation requise. Utilisez le paramètre confirm=DELETE_ALL_COURRIERS'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé.'),
            new OA\Response(response: 403, description: 'Permissions insuffisantes. Seuls les administrateurs peuvent effectuer cette action.')
        ]
    )]
    public function deleteAll(Request $request): Response
    {
        // ðŸ” VÃ©rification des droits d'accÃ¨s - ADMIN UNIQUEMENT
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'DeleteAllCourrier');

        // ðŸ›¡ï¸ SÃ©curitÃ© : VÃ©rification de la confirmation
        $confirmation = $request->query->get('confirm');
        
        if ($confirmation !== 'DELETE_ALL_COURRIERS') {
            return $this->json([
                'code' => 400,
                'message' => 'Confirmation requise. Utilisez le paramètre confirm=DELETE_ALL_COURRIERS',
                'hint' => 'Cette action est IRRÉVERSIBLE. Assurez-vous de vouloir supprimer TOUS les courriers.'
            ], 400);
        }

        try {
            // ðŸ“Š Compter le nombre de courriers avant suppression
            $totalCourriers = $this->courrierRepository->count([]);

            if ($totalCourriers === 0) {
                return $this->json([
                    'code' => 200,
                    'message' => 'Aucun courrier à supprimer',
                    'count' => 0
                ], 200);
            }

            // ðŸ—‘ï¸ Suppression en masse sans logging
            // Utilisation de DQL pour supprimer directement en base de donnÃ©es
            $query = $this->entityManager->createQuery(
                'DELETE FROM App\Entity\Cour\Courrier c'
            );
            
            $deletedCount = $query->execute();

            return $this->json([
                'code' => 200,
                'message' => 'Tous les courriers ont été supprimés avec succès',
                'count' => $deletedCount,
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
            ], 200);

        } catch (\Exception $e) {
            $this->logger?->error('Erreur lors de la suppression massive de courriers', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->json([
                'code' => 500,
                'message' => 'Erreur lors de la suppression des courriers',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
