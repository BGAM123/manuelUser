<?php

namespace App\Controller\Core\Courrier;

use App\Repository\Cour\CourrierRepository;
use App\Repository\Cour\TransmissionRepository;
use App\Repository\Core\UserRepository;
use App\Service\Core\AccessCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "CourrierArrive")]
class GelerController extends AbstractController
{
    public function __construct(
        private CourrierRepository $courrierRepository,
        private TransmissionRepository $transmissionRepository,
        private UserRepository $userRepository,
        private AccessCheckerService $accessChecker,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/core/courrier/geler/{id}', name: 'app_core_courrier_geler', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/courrier/geler/{id}',
        summary: 'Geler un courrier',
        tags: ['Courrier'],
        description: 'Gèle un courrier en définissant is_geled à true et statut à "Classé". Un courrier gelé ne peut plus être modifié. L\'utilisateur connecté est automatiquement enregistré comme celui qui a classé le courrier. Permet d\'ajouter des commentaires public et interne.',
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'commentairePublic', type: 'string', description: 'Commentaire public visible par tous', example: 'Dossier traité et archivé'),
                    new OA\Property(property: 'commentaireInterne', type: 'string', description: 'Commentaire interne visible uniquement en interne', example: 'Nécessite un suivi ultérieur')
                ]
            )
        ),
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant du courrier à geler',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Courrier gelé avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Courrier gelé avec succès'),
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'is_geled', type: 'boolean', example: true),
                        new OA\Property(property: 'statut', type: 'string', example: 'Classé'),
                        new OA\Property(property: 'commentairePublic', type: 'string', example: 'Dossier traité et archivé'),
                        new OA\Property(property: 'commentaireInterne', type: 'string', example: 'Nécessite un suivi ultérieur'),
                        new OA\Property(property: 'transmissions_updated', type: 'integer', example: 3, description: 'Nombre de transmissions mises à jour'),
                        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2025-11-05T10:30:00Z'),
                        new OA\Property(
                            property: 'gele_par',
                            type: 'object',
                            description: 'Informations sur l\'utilisateur qui a gelé le courrier',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 5),
                                new OA\Property(property: 'nom', type: 'string', example: 'Jean Dupont'),
                                new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@example.com')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Courrier non trouvé.'),
            new OA\Response(response: 400, description: 'Le courrier est déjà gelé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function geler(int $id, Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GelerCourrier');
        
        $courrier = $this->courrierRepository->find($id);

        if (!$courrier) {
            return $this->json([
                'code' => 404,
                'message' => 'Courrier non trouvé.'
            ], 404);
        }

        // Vérifier si le courrier est déjà gelé
        if ($courrier->isGeled()) {
            return $this->json([
                'code' => 400,
                'message' => 'Le courrier est déjà gelé.'
            ], 400);
        }

        try {
            // Récupérer les données du body
            $data = json_decode($request->getContent(), true);
            
            // Geler le courrier
            $courrier->setGeled(true);
            
            // Changer le statut à "Classé"
            $courrier->setStatut('Classé');
            
            // Ajouter les commentaires s'ils sont fournis
            if (isset($data['commentairePublic'])) {
                $courrier->setCommentairePublic($data['commentairePublic']);
            }
            
            if (isset($data['commentaireInterne'])) {
                $courrier->setCommentaireInterne($data['commentaireInterne']);
            }
            
            // Récupérer l'utilisateur connecté
            $currentUser = $this->getUser();
            $geleParUser = null;
            $userId = null;
            
            if ($currentUser instanceof \App\Entity\Core\User) {
                $geleParUser = $currentUser;
                $userId = $currentUser->getId();
            }
            
            // Mettre à jour les transmissions liées à ce courrier
            $transmissions = $this->transmissionRepository->findBy(['idCourrier' => $courrier]);
            
            $transmissionsUpdatedCount = 0;
            
            foreach ($transmissions as $transmission) {
                // RÃ©cupÃ©rer le tableau existant de traite_par
                $traitePar = $transmission->getTraitePar() ?? [];
                
                // Ajouter une nouvelle entrÃ©e avec l'action "gele" (courrier gelÃ©/classÃ©)
                $newEntry = [
                    'action' => 'gele',
                    'date_traitement' => (new \DateTime())->format('Y-m-d H:i:s'),
                ];
                
                // Ajouter l'ID utilisateur seulement s'il est disponible
                if ($userId !== null) {
                    $newEntry['classe_par_id'] = $userId;
                }
                
                $traitePar[] = $newEntry;
                
                $transmission->setTraitePar($traitePar);
                $transmissionsUpdatedCount++;
            }
            
            // Sauvegarder les modifications
            $this->entityManager->flush();

            $response = [
                'message' => 'Courrier gelé avec succès',
                'id' => $courrier->getId(),
                'is_geled' => $courrier->isGeled(),
                'statut' => $courrier->getStatut(),
                'commentairePublic' => $courrier->getCommentairePublic(),
                'commentaireInterne' => $courrier->getCommentaireInterne(),
                'transmissions_updated' => $transmissionsUpdatedCount,
                'updatedAt' => $courrier->getUpdatedAt()?->format('c'),
                'gele_par' => null
            ];
            
            // Ajouter les informations de l'utilisateur qui a gelé
            if ($geleParUser instanceof \App\Entity\Core\User) {
                $response['gele_par'] = [
                    'id' => $geleParUser->getId(),
                    'nom' => $geleParUser->getFullName(),
                    'email' => $geleParUser->getEmail()
                ];
            }

            return $this->json($response, 200);

        } catch (\Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => 'Erreur lors du gel du courrier : ' . $e->getMessage()
            ], 500);
        }
    }
}
