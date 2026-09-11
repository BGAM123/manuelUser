<?php

namespace App\Controller\Core\Courrier;

use App\Repository\Cour\CourrierRepository;
use App\Service\Core\AccessCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "CourrierArrive")]
class DegelerController extends AbstractController
{
    public function __construct(
        private CourrierRepository $courrierRepository,
        private AccessCheckerService $accessChecker,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/core/courrier/degeler/{id}', name: 'app_core_courrier_degeler', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/courrier/degeler/{id}',
        summary: 'Dégeler un courrier',
        tags: ['Courrier'],
        description: 'Dégèle un courrier en définissant is_geled à false et ajuste le statut : "Traiter" si le courrier a des réponses, "Reçu" si une transmission a un accusé de réception, sinon "Transmis". Le courrier peut à nouveau être modifié. Les commentaires public et interne sont réinitialisés à null.',
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant du courrier à dégeler',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Courrier dégelé avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Courrier dégelé avec succès'),
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'is_geled', type: 'boolean', example: false),
                        new OA\Property(property: 'statut', type: 'string', example: 'Reçu'),
                        new OA\Property(property: 'commentairePublic', type: 'string', nullable: true, example: null, description: 'Réinitialisé à null lors du dégel'),
                        new OA\Property(property: 'commentaireInterne', type: 'string', nullable: true, example: null, description: 'Réinitialisé à null lors du dégel'),
                        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2025-11-05T10:30:00Z')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Courrier non trouvé.'),
            new OA\Response(response: 400, description: 'Le courrier n\'est pas gelé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function degeler(int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'DegelerCourrier');

        $courrier = $this->courrierRepository->find($id);

        if (!$courrier) {
            return $this->json([
                'code' => 404,
                'message' => 'Courrier non trouvé.'
            ], 404);
        }

        // Vérifier si le courrier n'est pas gelé
        if (!$courrier->isGeled()) {
            return $this->json([
                'code' => 400,
                'message' => 'Le courrier n\'est pas gelé.'
            ], 400);
        }

        try {
            // Dégeler le courrier
            $courrier->setGeled(false);
            
            // RÃ©initialiser les commentaires Ã  null
            $courrier->setCommentairePublic(null);
            $courrier->setCommentaireInterne(null);
            
            // DÃ©terminer le nouveau statut selon la logique mÃ©tier
            $nouveauStatut = $this->determinerStatutApresGel($courrier);
            $courrier->setStatut($nouveauStatut);
            
            // Sauvegarder les modifications
            $this->entityManager->flush();

            return $this->json([
                'message' => 'Courrier dégelé avec succès',
                'id' => $courrier->getId(),
                'is_geled' => $courrier->isGeled(),
                'statut' => $courrier->getStatut(),
                'commentairePublic' => $courrier->getCommentairePublic(),
                'commentaireInterne' => $courrier->getCommentaireInterne(),
                'updatedAt' => $courrier->getUpdatedAt()?->format('c')
            ], 200);

        } catch (\Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => 'Erreur lors du dégel du courrier : ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * DÃ©termine le statut du courrier aprÃ¨s dÃ©gel selon la logique mÃ©tier
     * - Si le courrier a des rÃ©ponses : "Traiter"
     * - Si une transmission a un accusÃ© de rÃ©ception (accuse_reception = true) : "ReÃ§u"
     * - Sinon : "Transmis"
     */
    private function determinerStatutApresGel($courrier): string
    {
        // 1. VÃ©rifier si le courrier a des rÃ©ponses
        if ($courrier->getReponses()->count() > 0) {
            return 'Traiter';
        }

        // 2. VÃ©rifier si au moins une transmission a un accusÃ© de rÃ©ception
        $transmissions = $courrier->getTransmissions();
        foreach ($transmissions as $transmission) {
            if ($transmission->isAccuseReception()) {
                return 'Reçu';
            }
        }

        // 3. Par défaut, retourner "Transmis"
        return 'Transmis';
    }
}
