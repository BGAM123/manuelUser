<?php

namespace App\Controller\Core\Courrier;

use App\Entity\Core\User;
use App\Repository\Cour\TransmissionRepository;
use App\Repository\Core\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "CourrierArrive")]
class AccuseReceptionController extends AbstractController
{
    #[Route('/core/courrier/accuse', name: 'accuse_reception', methods: ['POST'])]
    #[OA\Post(
        path: '/core/courrier/accuse',
        summary: 'Enregistrer un accusé de réception pour une ou plusieurs transmissions',
        description: 'Marque une ou plusieurs transmissions comme Reçu et enregistre l\'accusé de réception. Pour plusieurs transmissions, vérifie qu\'elles ont le même idServiceDestinataire.',
        tags: ['CourrierArrive'],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'IDs des transmissions à accuser réception',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'ids',
                        type: 'array',
                        items: new OA\Items(type: 'integer'),
                        example: [1, 2, 3],
                        description: 'Un ou plusieurs IDs de transmission'
                    )
                ],
                required: ['ids']
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Accusé de réception enregistré avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Accusé de réception enregistré avec succès pour 3 transmission(s)'),
                        new OA\Property(property: 'count', type: 'integer', example: 3),
                        new OA\Property(property: 'notifications_marked_as_read', type: 'integer', example: 3),
                        new OA\Property(
                            property: 'transmissions',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'statut', type: 'string', example: 'Reçu'),
                                    new OA\Property(property: 'accuse_reception', type: 'boolean', example: true),
                                    new OA\Property(property: 'date_reception', type: 'string', format: 'date-time', example: '2024-11-05 14:30:00'),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Erreur de validation.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Les transmissions sélectionnées n\'ont pas le même service destinataire')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Transmission introuvable.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Transmission introuvable')
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function accuseReception(
        Request $request,
        TransmissionRepository $transmissionRepository,
        NotificationRepository $notificationRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        // RÃ©cupÃ©rer les donnÃ©es JSON
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['ids']) || !is_array($data['ids']) || empty($data['ids'])) {
            return $this->json(['error' => 'Le paramètre "ids" est requis et doit être un tableau non vide'], 400);
        }

        $ids = array_map('intval', $data['ids']);
        
        // Récupérer toutes les transmissions
        $transmissions = $transmissionRepository->findBy(['id' => $ids]);
        
        if (count($transmissions) === 0) {
            return $this->json(['error' => 'Aucune transmission trouvée'], 404);
        }

        if (count($transmissions) !== count($ids)) {
            $foundIds = array_map(fn($t) => $t->getId(), $transmissions);
            $missingIds = array_diff($ids, $foundIds);
            return $this->json([
                'error' => 'Certaines transmissions sont introuvables',
                'missing_ids' => array_values($missingIds)
            ], 404);
        }

        // Si plusieurs transmissions, vÃ©rifier qu'elles ont le mÃªme idServiceDestinataire
        if (count($transmissions) > 1) {
            $firstServiceDestinataireId = null;
            
            foreach ($transmissions as $transmission) {
                $serviceDestinataire = $transmission->getIdServiceDestinataire();
                
                if ($serviceDestinataire === null) {
                    return $this->json([
                        'error' => 'Une ou plusieurs transmissions n\'ont pas de service destinataire défini',
                        'transmission_id' => $transmission->getId()
                    ], 400);
                }
                
                $serviceDestinataireId = $serviceDestinataire->getId();
                
                if ($firstServiceDestinataireId === null) {
                    $firstServiceDestinataireId = $serviceDestinataireId;
                } elseif ($firstServiceDestinataireId !== $serviceDestinataireId) {
                    return $this->json([
                        'error' => 'Les transmissions sélectionnées n\'ont pas le même service destinataire',
                        'message' => 'Accusé de réception impossible car les transmissions n\'ont pas le même idServiceDestinataire'
                    ], 400);
                }
            }
        }

        // RÃ©cupÃ©rer l'utilisateur connectÃ©
        $currentUser = $this->getUser();
        $userId = null;
        
        if ($currentUser instanceof User) {
            $userId = $currentUser->getId();
        }

        $dateReception = new \DateTime();
        $results = [];

        // Traiter chaque transmission
        foreach ($transmissions as $transmission) {
            // RÃ©cupÃ©ration du tableau existant de traite_par
            $traitePar = $transmission->getTraitePar() ?? [];
            
            // Ajouter l'entrÃ©e avec l'utilisateur connectÃ©
            if ($userId !== null) {
                $traitePar[] = [
                    'action' => 'accuse_reception',
                    'accuse_par_id' => $userId,
                    'date_traitement' => $dateReception->format('Y-m-d H:i:s'),
                ];
            }

            // Mise à jour des statuts
            $transmission->setStatut('Reçu');
            $transmission->setAccuseReception(true);
            $transmission->setDateReception($dateReception);
            $transmission->setTraitePar($traitePar);

            $em->persist($transmission);
            
            $results[] = [
                'id' => $transmission->getId(),
                'statut' => $transmission->getStatut(),
                'accuse_reception' => $transmission->isAccuseReception(),
                'date_reception' => $transmission->getDateReception()?->format('Y-m-d H:i:s'),
            ];
        }

        $em->flush();

        $notificationsMarkedAsRead = $this->markTransmissionNotificationsAsRead($notificationRepository, $ids);

        $count = count($transmissions);
        return $this->json([
            'message' => "Accusé de réception enregistré avec succès pour {$count} transmission(s)",
            'count' => $count,
            'notifications_marked_as_read' => $notificationsMarkedAsRead,
            'transmissions' => $results,
        ]);
    }

    private function markTransmissionNotificationsAsRead(NotificationRepository $notificationRepository, array $transmissionIds): int
    {
        $currentUser = $this->getUser();
        $service = $currentUser instanceof User ? $currentUser->getIdService() : null;

        if (!$service) {
            return 0;
        }

        // Marquer les notifications comme lues
        $markedAsReadCount = $notificationRepository->markTransmissionNotificationsAsReadByServiceAndTransmissionIds($service, $transmissionIds);

        // Supprimer les notifications après les avoir marquées comme lues
        $notificationRepository->deleteTransmissionNotificationsByServiceAndTransmissionIds($service, $transmissionIds);

        return $markedAsReadCount;
    }
}
