<?php
 
namespace App\Controller\Core\Courrier;

use App\Entity\Cour\Courrier;
use App\Repository\Cour\PieceJointeRepository;
use App\Repository\Core\UserRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Entity\Cour\Transmission;

#[OA\Tag(name: "CourrierArrive")]
class ParcoursController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker,
        private UserRepository $userRepository,
        private PieceJointeRepository $pieceJointeRepository
    ) {}

    /**
     * MÃ©thode helper pour rÃ©cupÃ©rer le nom d'un service de maniÃ¨re sÃ©curisÃ©e
     * ConcatÃ¨ne avec le sigle du service parent si disponible
     * Format: "NomService - SigleParent"
     */
    private function getServiceName($service): ?string
    {
        if (!$service) {
            return null;
        }
        
        try {
            if ($service->isDelete()) {
                return 'Service supprimé';
            }
            
            $nomService = $service->getNom();
            
            // Ajouter le sigle du service parent si disponible
            $serviceParent = $service->getIdServiceParent();
            if ($serviceParent && !$serviceParent->isDelete()) {
                $sigleParent = $serviceParent->getSigle() ?: $serviceParent->getNom();
                return $nomService . ' - ' . $sigleParent;
            }
            
            return $nomService;
        } catch (\Exception $e) {
            return 'Service introuvable';
        }
    }

    /**
     * MÃ©thode helper pour rÃ©cupÃ©rer les noms des utilisateurs actifs d'un service
     */
    private function getServiceUsers($service): array
    {
        if (!$service) {
            return [];
        }
        
        try {
            if ($service->isDelete()) {
                return [];
            }
            
            $users = $this->userRepository->findBy([
                'idService' => $service,
                'isActive' => true,
                'isDelete' => false
            ]);
            
            $userNames = [];
            foreach ($users as $user) {
                $userNames[] = [
                    'id' => $user->getId(),
                    'nom_complet' => $user->getFullName(),
                    'email' => $user->getEmail(),
                ];
            }
            
            return $userNames;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function formatPieceJointe($piece): array
    {
        return [
            'id' => $piece->getId(),
            'nom' => $piece->getNom(),
            'intitule' => $piece->getIntitule(),
            'chemin' => $piece->getChemin(),
            'type' => $piece->getType(),
        ];
    }

    private function getPiecesJointesForParent(int $parentId, string $typeParent, ?\DateTimeInterface $createdAt = null): array
    {
        $queryBuilder = $this->pieceJointeRepository->createQueryBuilder('pj')
            ->where('pj.idParent = :parentId')
            ->andWhere('pj.typeParent = :typeParent')
            ->andWhere('pj.isDelete = :isDelete')
            ->setParameter('parentId', $parentId)
            ->setParameter('typeParent', $typeParent)
            ->setParameter('isDelete', false)
            ->orderBy('pj.createdAt', 'ASC')
            ->addOrderBy('pj.id', 'ASC');

        if ($createdAt) {
            $queryBuilder
                ->andWhere('pj.createdAt >= :createdAt')
                ->setParameter('createdAt', $createdAt);
        }

        return array_map(
            fn($piece) => $this->formatPieceJointe($piece),
            $queryBuilder->getQuery()->getResult()
        );
    }


        // fonction pour recuper le nom de l'utilisateur additionnel qui a transmis le courrier "Alex"
        private function getTransmissionUser(Transmission $transmission): ?string
        {
            $traitePar = $transmission->getTraitePar();

            if (empty($traitePar) || !is_array($traitePar)) {
                return null;
            }

            // Recherche la dernière action de type "transmission"
            foreach (array_reverse($traitePar) as $action) {

                if (($action['action'] ?? null) !== 'transmission') {
                    continue;
                }

                $userId = $action['transmis_par_id'] ?? null;

                if (!$userId) {
                    continue;
                }

                $user = $this->userRepository->find($userId);

                return $user?->getFullName();
            }

            return null;
        }

    #[Route('/courrier/{id}/parcours', name: 'app_courrier_parcours', methods: ['GET'])]
    #[OA\Get(
        path: '/courrier/{id}/parcours',
        summary: 'Récupérer le parcours complet d\'un courrier',
        description: 'Retourne toutes les informations de traçabilité d\'un courrier avec une timeline chronologique complète. Les noms de services sont affichés avec le sigle de leur service parent (ex: "MINISTRE - CM").',
        tags: ['CourrierArrive'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID du courrier',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Parcours du courrier récupéré avec succès',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'courrier',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'numero', type: 'string'),
                                new OA\Property(property: 'objet', type: 'string'),
                                new OA\Property(property: 'document', type: 'string'),
                            ]
                        ),
                        new OA\Property(
                            property: 'timeline',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'date', type: 'string', format: 'date-time'),
                                    new OA\Property(property: 'type', type: 'string', enum: ['creation', 'transmission', 'reponse', 'cloture', 'classement']),
                                    new OA\Property(property: 'details', type: 'object'),
                                ]
                            )
                        ),
                        new OA\Property(
                            property: 'piecejointesTimeline',
                            type: 'array',
                            items: new OA\Items(type: 'object')
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Courrier non trouvé'),
            new OA\Response(response: 401, description: 'Accès non autorisé')
        ]
    )]
    public function getParcours(?Courrier $courrier = null): Response
    {
        
    //   $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetCourrier');

        if (!$courrier || $courrier->isDelete()) {
            return $this->json(['code' => 404, 'message' => 'Courrier non trouvé.'], 404);
        }

        try {
            // âœ… INFORMATIONS DU COURRIER
            $courrierData = [
                'id' => $courrier->getId(),
                'numero' => $courrier->getNumero(),
                'objet' => $courrier->getObjet(),
                'reference' => $courrier->getReference(),
                'statut' => $courrier->getStatut(),
                'priorite' => $courrier->getPriorite(),
                'isConfidentiel' => $courrier->isConfidentiel(),
                'isGeled' => $courrier->isGeled(),
                'document' => $courrier->getDocument(),
            ];

            $piecesJointesTimeline = [];
            $courrierPiecesJointes = $this->getPiecesJointesForParent(
                $courrier->getId(),
                'Courrier',
                $courrier->getCreatedAt()
            );

            $timeline = [];

            $timeline[] = [
                'date' => $courrier->getCreatedAt()?->format('Y-m-d H:i:s'),
                'dateTimestamp' => $courrier->getCreatedAt()?->getTimestamp() ?? 0,
                'type' => 'creation',
                'details' => [
                    'dateArrivee' => $courrier->getDateArrivee()?->format('Y-m-d H:i:s'),
                    'dateEnregistrement' => $courrier->getDateEnregistrement()?->format('Y-m-d H:i:s'),
                    'createur' => [
                        'nom_createur' => $courrier->getIdCreateur()?->getFullName(),
                        'service_utilisateur' => $this->getServiceName($courrier->getIdCreateur()?->getIdService())
                    ],
                    'service_traitant' => $this->getServiceName($courrier->getIdServiceTraitant()),
                    'utilisateurs_service_traitant' => $this->getServiceUsers($courrier->getIdServiceTraitant()),
                    'provenance' => [
                        'nom' => $courrier->getIdProvenance()?->getNom(),
                        'type' => $courrier->getIdProvenance() ? 'Correspondant externe' : null
                    ],
                    'categorie' => $courrier->getTypeCourrier()?->getNom(),
                    'priorite' => $courrier->getPriorite(),
                    'reference' => $courrier->getReference(),
                    'isConfidentiel' => $courrier->isConfidentiel(),
                    'document' => $courrier->getDocument(),
                ]
            ];
            $piecesJointesTimeline[] = [
                'date' => $courrier->getCreatedAt()?->format('Y-m-d H:i:s'),
                'dateTimestamp' => $courrier->getCreatedAt()?->getTimestamp() ?? 0,
                'type' => 'creation',
                'piecesJointes' => $courrierPiecesJointes,
            ];

            // 2ï¸âƒ£ TRANSMISSIONS
            foreach ($courrier->getTransmissions() as $transmission) {
                if (!$transmission->isDelete()) {
                    try {
                        $dateRef = $transmission->getCreatedAt();
                        
                        // RÃ©cupÃ©rer le service destinataire en gÃ©rant les cas oÃ¹ il n'existe plus
                        $serviceDestinataire = null;
                        $nomServiceDestinataire = null;
                        $chefServiceNom = null;
                        
                        try {
                            $serviceDestinataire = $transmission->getIdServiceDestinataire();
                            if ($serviceDestinataire && !$serviceDestinataire->isDelete()) {
                                $nomServiceDestinataire = $this->getServiceName($serviceDestinataire);
                                $chefService = $serviceDestinataire->getChefService();
                                if ($chefService) {
                                    $chefServiceNom = $chefService->getFullName();
                                }
                            }
                        } catch (\Exception $e) {
                            $nomServiceDestinataire = 'Service supprimé ou introuvable';
                        }
                        
                        $piecesJointesTransmission = $this->getPiecesJointesForParent(
                            $transmission->getId(),
                            'Transmission',
                            $dateRef
                        );
                        
                        $timeline[] = [
                            'date' => $dateRef?->format('Y-m-d H:i:s'),
                            'dateTimestamp' => $dateRef?->getTimestamp() ?? 0,
                            'type' => 'transmission',
                            'details' => [
                                'id' => $transmission->getId(),
                                'emetteur' => [
                                    // 'nom_emetteur' => $transmission->getIdEmetteur()?->getFullName(),
                                    'nom_emetteur' => $this->getTransmissionUser($transmission),
                                    'service_emetteur' => $this->getServiceName($transmission->getIdEmetteur()?->getIdService())
                                ],
                                'destinataire' => [
                                    'nom_destinataire_chef' => $chefServiceNom,
                                    'service_destinataire' => $nomServiceDestinataire
                                ],
                                'utilisateurs_service_destinataire' => $this->getServiceUsers($serviceDestinataire),
                                'instruction' => $transmission->getInstruction(),
                                'typeTransfert' => $transmission->getTypeTransfert(),
                                'delaiTraitement' => $transmission->getDelaiTraitement(),
                                'statut' => $transmission->getStatut(),
                                'accuseReception' => $transmission->isAccuseReception(),
                                'structuresCopie' => $transmission->getStructuresCopie(),
                            ]
                        ];
                        $piecesJointesTimeline[] = [
                            'date' => $dateRef?->format('Y-m-d H:i:s'),
                            'dateTimestamp' => $dateRef?->getTimestamp() ?? 0,
                            'type' => 'transmission',
                            'sourceId' => $transmission->getId(),
                            'piecesJointes' => $piecesJointesTransmission,
                        ];
                    } catch (\Exception $e) {
                        // Ignorer cette transmission si elle cause des problÃ¨mes
                        continue;
                    }
                }
            }

            // 3ï¸âƒ£ RÃ‰PONSES
            foreach ($courrier->getReponses() as $reponse) {
                if (!$reponse->isDelete()) {
                    try {
                        $dateRef = $reponse->getCreatedAt();
                        
                        // RÃ©cupÃ©rer le service destinataire en gÃ©rant les cas oÃ¹ il n'existe plus
                        $serviceDestinataire = null;
                        $nomServiceDestinataire = null;
                        $chefServiceNom = null;
                        
                        try {
                            $serviceDestinataire = $reponse->getIdServiceDestinataire();
                            if ($serviceDestinataire && !$serviceDestinataire->isDelete()) {
                                $nomServiceDestinataire = $this->getServiceName($serviceDestinataire);
                                $chefService = $serviceDestinataire->getChefService();
                                if ($chefService) {
                                    $chefServiceNom = $chefService->getFullName();
                                }
                            }
                        } catch (\Exception $e) {
                            // Service non trouvÃ© ou supprimÃ©
                            $nomServiceDestinataire = 'Service supprimé ou introuvable';
                        }
                        
                        $piecesJointesReponse = $this->getPiecesJointesForParent(
                            $reponse->getId(),
                            'Reponse',
                            $dateRef
                        );

                        $timeline[] = [
                            'date' => $dateRef?->format('Y-m-d H:i:s'),
                            'dateTimestamp' => $dateRef?->getTimestamp() ?? 0,
                            'type' => 'reponse',
                            'details' => [
                                'id' => $reponse->getId(),
                                'auteur' => [
                                    'nom_auteur' => $reponse->getIdRedacteur()?->getFullName(),
                                    'service_auteur' => $this->getServiceName($reponse->getIdRedacteur()?->getIdService())
                                ],
                                'destinataire' => [
                                    'nom_destinataire_chef' => $chefServiceNom,
                                    'service_destinataire' => $nomServiceDestinataire
                                ],
                                'utilisateurs_service_destinataire' => $this->getServiceUsers($serviceDestinataire),
                                'typeReponse' => $reponse->getTypeReponse()?->getNom(),
                                'objet' => $reponse->getObjet(),
                                'commentaire' => $reponse->getCommentairePublic(),
                            ]
                        ];
                        $piecesJointesTimeline[] = [
                            'date' => $dateRef?->format('Y-m-d H:i:s'),
                            'dateTimestamp' => $dateRef?->getTimestamp() ?? 0,
                            'type' => 'reponse',
                            'sourceId' => $reponse->getId(),
                            'piecesJointes' => $piecesJointesReponse,
                        ];
                    } catch (\Exception $e) {
                        // Ignorer cette rÃ©ponse si elle cause des problÃ¨mes
                        continue;
                    }
                }
            }

            // 4ï¸âƒ£ CLÃ”TURE (si le courrier est clÃ´turÃ©)
            if ($courrier->getDateCloture()) {
                $timeline[] = [
                    'date' => $courrier->getDateCloture()->format('Y-m-d H:i:s'),
                    'dateTimestamp' => $courrier->getDateCloture()->getTimestamp(),
                    'type' => 'cloture',
                    'details' => [
                        'message' => 'Courrier clôturé',
                    ]
                ];
                $piecesJointesTimeline[] = [
                    'date' => $courrier->getDateCloture()->format('Y-m-d H:i:s'),
                    'dateTimestamp' => $courrier->getDateCloture()->getTimestamp(),
                    'type' => 'cloture',
                    'sourceId' => $courrier->getId(),
                    'piecesJointes' => [],
                ];
            }

            // 5ï¸âƒ£ CLASSEMENT (si le courrier est gelé/classé)
            if ($courrier->isGeled()) {
                $timeline[] = [
                    'date' => $courrier->getUpdatedAt()?->format('Y-m-d H:i:s'),
                    'dateTimestamp' => $courrier->getUpdatedAt()?->getTimestamp() ?? 0,
                    'type' => 'classement',
                    'details' => [
                        'message' => 'Courrier classÃ©',
                    ]
                ];
                $piecesJointesTimeline[] = [
                    'date' => $courrier->getUpdatedAt()?->format('Y-m-d H:i:s'),
                    'dateTimestamp' => $courrier->getUpdatedAt()?->getTimestamp() ?? 0,
                    'type' => 'classement',
                    'sourceId' => $courrier->getId(),
                    'piecesJointes' => [],
                ];
            }

            // âœ… TRIER LA TIMELINE PAR ORDRE CHRONOLOGIQUE
            // Trier en prioritÃ© les Ã©vÃ©nements de type 'creation' avant les autres,
            // puis par date croissante pour les Ã©vÃ©nements ayant la mÃªme prioritÃ©.
            usort($timeline, function($a, $b) {
                $priority = fn($event) => $event['type'] === 'creation' ? 0 : 1;

                $pa = $priority($a);
                $pb = $priority($b);

                if ($pa !== $pb) {
                    return $pa <=> $pb; // 'creation' (0) avant les autres (1)
                }

                // MÃªme prioritÃ© : trier par timestamp (ordre chronologique)
                return $a['dateTimestamp'] <=> $b['dateTimestamp'];
            });

            // Supprimer les timestamps (utilisÃ©s uniquement pour le tri)
            foreach ($timeline as &$event) {
                unset($event['dateTimestamp']);
            }

            // âœ… STATISTIQUES
            usort($piecesJointesTimeline, function($a, $b) {
                $priority = fn($event) => $event['type'] === 'creation' ? 0 : 1;

                $pa = $priority($a);
                $pb = $priority($b);

                if ($pa !== $pb) {
                    return $pa <=> $pb;
                }

                return $a['dateTimestamp'] <=> $b['dateTimestamp'];
            });

            foreach ($piecesJointesTimeline as &$event) {
                unset($event['dateTimestamp']);
            }
            unset($event);

            $statistiques = [
                'nombreEvenements' => count($timeline),
                'nombreTransmissions' => count(array_filter($timeline, fn($e) => $e['type'] === 'transmission')),
                'nombreReponses' => count(array_filter($timeline, fn($e) => $e['type'] === 'reponse')),
                'estCloture' => $courrier->getDateCloture() !== null,
                'estClasse' => $courrier->isGeled(),
            ];

            return $this->json([
                'courrier' => $courrierData,
                'timeline' => $timeline,
                'piecejointesTimeline' => $piecesJointesTimeline,
                'statistiques' => $statistiques,
            ]);

        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}
