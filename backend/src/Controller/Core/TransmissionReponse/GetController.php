<?php

namespace App\Controller\Core\TransmissionReponse;

use App\Entity\Cour\CourrierInterne;
use App\Repository\Cour\TransmissionReponseRepository;
use App\Repository\Cour\PieceJointeRepository;
use App\Repository\Cour\ReponseRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "TransmissionReponse")]
class GetController extends AbstractController
{
    public function __construct(
        private TransmissionReponseRepository $transmissionReponseRepository,
        private PieceJointeRepository $pieceJointeRepository,
        private ReponseRepository $reponseRepository,
        private AccessCheckerService $accessChecker,
        private EntityManagerInterface $entityManager,
        private UserActionLoggerService $actionLogger,
    ) {}

    #[Route('/core/transmission-reponse/{id}', name: 'app_core_transmission_reponse_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(
        path: '/core/transmission-reponse/{id}',
        summary: 'Récupérer une transmission réponse par son ID',
        tags: ['TransmissionReponse'],
        description: "Retourne les détails complets d'une transmission réponse, incluant ses pièces jointes.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Transmission réponse récupérée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'objet', type: 'string', example: 'RE: Demande de budget'),
                        new OA\Property(property: 'commentairePublic', type: 'string', example: 'Voici notre rÃ©ponse'),
                        new OA\Property(property: 'classeCourrier', type: 'string', example: 'Urgent'),
                        new OA\Property(property: 'typeTransmission', type: 'string', example: 'Electronique'),
                        new OA\Property(property: 'priorite', type: 'string', example: 'Haute'),
                        new OA\Property(property: 'statut', type: 'string', example: 'Transmis'),
                        new OA\Property(property: 'idCourrierInternes', type: 'array', items: new OA\Items(type: 'integer'), example: [4, 5, 6]),
                        new OA\Property(property: 'reponses', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(
                            property: 'courrierInterneLiees',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 12),
                                    new OA\Property(property: 'statut', type: 'string', example: 'ReÃ§u'),
                                    new OA\Property(
                                        property: 'serviceDestinataire',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 3),
                                            new OA\Property(property: 'nom', type: 'string', example: 'DAJ'),
                                            new OA\Property(property: 'sigle', type: 'string', example: 'DAJ'),
                                        ]
                                    ),
                                ]
                            )
                        ),
                        new OA\Property(property: 'accuseReception', type: 'boolean', example: true),
                        new OA\Property(property: 'isinstance', type: 'boolean', example: false),
                        new OA\Property(property: 'is_geled', type: 'boolean', example: false),
                        new OA\Property(property: 'nombrePieceJointe', type: 'integer', example: 2),
                        new OA\Property(
                            property: 'piecesJointes',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'nom', type: 'string', example: 'rapport.pdf'),
                                    new OA\Property(property: 'intitule', type: 'string', example: 'Rapport financier', nullable: true),
                                    new OA\Property(property: 'chemin', type: 'string', example: '/uploads/courrier_piece/rapport.pdf'),
                                    new OA\Property(property: 'type', type: 'string', example: 'application/pdf'),
                                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-02-14 10:30:00'),
                                ]
                            )
                        ),
                        new OA\Property(property: 'dateReponse', type: 'string', format: 'date-time', example: '2025-02-14 10:30:00'),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-02-14 10:30:00'),
                        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2025-02-14 10:30:00'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Transmission réponse non trouvée.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function show(int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetTransmissionReponse');

        $reponse = $this->transmissionReponseRepository->find($id);

        if (!$reponse) {
            return $this->json(['code' => 404, 'message' => 'Transmission réponse non trouvée.'], 404);
        }

        $piecesJointes = $this->pieceJointeRepository->findBy([
            'idParent' => $reponse->getId(),
            'typeParent' => 'TransmissionReponse',
            'isDelete' => false
        ]);

        $typesCourrier = [];
        if ($reponse->getTypesCourrierIds()) {
            foreach ($reponse->getTypesCourrierIds() as $typeId) {
                $typeCourrier = $this->entityManager->getRepository(\App\Entity\Core\TypeCourrier::class)->find($typeId);
                if ($typeCourrier && !$typeCourrier->isDelete()) {
                    $typesCourrier[] = [
                        'id' => $typeCourrier->getId(),
                        'nom' => $typeCourrier->getNom(),
                        'type' => $typeCourrier->getType(),
                    ];
                }
            }
        }

        $linkedCourrierInterneIds = $this->resolveCourrierInterneIds($reponse);
        $courrierInterneNumerosById = [];
        if (!empty($linkedCourrierInterneIds)) {
            $courrierInternes = $this->entityManager->getRepository(CourrierInterne::class)->findBy([
                'id' => $linkedCourrierInterneIds
            ]);
            foreach ($courrierInternes as $courrierInterne) {
                $courrierInterneNumerosById[$courrierInterne->getId()] = $courrierInterne->getNumero();
            }
        }

        $courrierInterneLiees = [];
        foreach ($linkedCourrierInterneIds as $courrierInterneId) {
            $lastTransmission = $this->transmissionReponseRepository->findLatestByCourrierInterneId($courrierInterneId);
            $courrierInterneLiees[] = [
                'id' => $courrierInterneId,
                'numero' => $courrierInterneNumerosById[$courrierInterneId] ?? null,
                'statut' => $lastTransmission?->getStatut(),
                'derniereTransmissionReponse' => $lastTransmission ? [
                    'id' => $lastTransmission->getId(),
                    'accuseReception' => $lastTransmission->isAccuseReception(),
                ] : null,
                'serviceDestinataire' => $lastTransmission?->getIdServiceDestinataire() ? [
                    'id' => $lastTransmission->getIdServiceDestinataire()->getId(),
                    'nom' => $lastTransmission->getIdServiceDestinataire()->getNom(),
                    'sigle' => $lastTransmission->getIdServiceDestinataire()->getSigle(),
                ] : null,
            ];
        }

        $idCourrierInternesWithReference = array_map(fn($ci) => [
            'id' => $ci['id'],
            'reference' => $ci['numero'] ?? null,
        ], $courrierInterneLiees);

        // Récupérer les pièces jointes des autres transmissions liées aux mêmes courrier interne
        $piecesJointesAutresTransmissions = [];
        try {
            $conn = $this->transmissionReponseRepository->getEntityManager()->getConnection();
            foreach ($linkedCourrierInterneIds as $courrierInterneId) {
                $sql = 'SELECT id FROM cour_transmission_reponse WHERE JSON_CONTAINS(id_courrier_interne, :cid) AND id != :currentId AND is_delete = 0 ORDER BY created_at DESC';
                $rows = $conn->fetchAllAssociative($sql, [
                    'cid' => json_encode($courrierInterneId),
                    'currentId' => $reponse->getId(),
                ]);

                $transmissionIds = array_map(fn($r) => (int) $r['id'], $rows);
                if (empty($transmissionIds)) {
                    continue;
                }

                foreach ($transmissionIds as $transId) {
                    $otherPjs = $this->pieceJointeRepository->findBy([
                        'idParent' => $transId,
                        'typeParent' => 'TransmissionReponse',
                        'isDelete' => false
                    ]);

                    foreach ($otherPjs as $pj) {
                        $piecesJointesAutresTransmissions[] = [
                            'courrierInterneId' => $courrierInterneId,
                            'transmissionId' => $transId,
                            'id' => $pj->getId(),
                            'nom' => $pj->getNom(),
                            'intitule' => method_exists($pj, 'getIntitule') ? $pj->getIntitule() : null,
                            'chemin' => $pj->getChemin(),
                            'type' => $pj->getType(),
                            'createdAt' => $pj->getCreatedAt()?->format('Y-m-d H:i:s'),
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            // En cas d'erreur DB, on laisse le tableau vide pour ne pas casser l'API
            $piecesJointesAutresTransmissions = [];
        }

        $reponses = [];
        $reponseIds = is_array($reponse->getIdReponses()) ? array_values(array_unique(array_filter($reponse->getIdReponses(), 'is_numeric'))) : [];
        if (!empty($reponseIds)) {
            $reponsesEntities = $this->reponseRepository->findBy([
                'id' => $reponseIds,
                'isDelete' => false
            ]);
            $reponsesById = [];
            foreach ($reponsesEntities as $reponseEntity) {
                $reponsesById[$reponseEntity->getId()] = $reponseEntity;
            }
            foreach ($reponseIds as $reponseId) {
                $reponseEntity = $reponsesById[(int) $reponseId] ?? null;
                if (!$reponseEntity) {
                    continue;
                }
                $reponses[] = [
                    'id' => $reponseEntity->getId(),
                    'objet' => $reponseEntity->getObjet(),
                    'commentairePublic' => $reponseEntity->getCommentairePublic(),
                    'commentaireInterne' => $reponseEntity->getCommentaireInterne(),
                    'classeCourrier' => $reponseEntity->getClasseCourrier(),
                    'typeTransmission' => $reponseEntity->getTypeTransmission(),
                    'priorite' => $reponseEntity->getPriorite(),
                    'statut' => $reponseEntity->getStatut(),
                    'dateReponse' => $reponseEntity->getDateReponse()?->format('Y-m-d'),
                    'serviceDestinataire' => $reponseEntity->getIdServiceDestinataire() ? [
                        'id' => $reponseEntity->getIdServiceDestinataire()->getId(),
                        'nom' => $reponseEntity->getIdServiceDestinataire()->getNom(),
                        'sigle' => $reponseEntity->getIdServiceDestinataire()->getSigle(),
                    ] : null,
                    'redacteur' => $reponseEntity->getIdRedacteur() ? [
                        'id' => $reponseEntity->getIdRedacteur()->getId(),
                        'fullName' => $reponseEntity->getIdRedacteur()->getFullName(),
                        'email' => $reponseEntity->getIdRedacteur()->getEmail(),
                    ] : null,
                ];
            }
        }

        /** @var \App\Entity\Core\User|null $currentUser */
        $currentUser = $this->getUser();
        [
            $canTransmit,
            $latestTransmissionId,
            $latestTransmissionCanModify
        ] = $this->computeTransmissionPermissions($reponse, $currentUser);

        $responseData = [
            'id' => $reponse->getId(),
            'courriers' => [],
            'typesCourrier' => $typesCourrier,
            'idTransmission' => $reponse->getIdTransmission(),
            'idCourrierInternes' => $idCourrierInternesWithReference,
            'reponses' => $reponses,
            'is_reponse' => !empty($reponses),
            'courrierInterneLiees' => $courrierInterneLiees,
            'objet' => $reponse->getObjet(),
            'commentairePublic' => $reponse->getCommentairePublic(),
            'classeCourrier' => $reponse->getClasseCourrier(),
            'typeTransmission' => $reponse->getTypeTransmission(),
            'priorite' => $reponse->getPriorite(),
            'statut' => $reponse->getStatut(),
            'accuseReception' => $reponse->isAccuseReception(),
            'isinstance' => $reponse->isinstance(),
            'is_geled' => $reponse->isGeled(),
            'serviceDestinataire' => $reponse->getIdServiceDestinataire() ? [
                'id' => $reponse->getIdServiceDestinataire()->getId(),
                'nom' => $reponse->getIdServiceDestinataire()->getNom(),
                'sigle' => $reponse->getIdServiceDestinataire()->getSigle(),
            ] : null,
            'redacteur' => $reponse->getIdRedacteur() ? [
                'id' => $reponse->getIdRedacteur()->getId(),
                'username' => $reponse->getIdRedacteur()->getUsername(),
                'fullName' => $reponse->getIdRedacteur()->getFullName(),
                'email' => $reponse->getIdRedacteur()->getEmail(),
            ] : null,
            'dateReponse' => $reponse->getDateReponse()?->format('Y-m-d H:i:s'),
            'canTransmit' => $canTransmit,
            'derniereCreer' => [
                'idDernier' => $latestTransmissionId,
                'canModify' => $latestTransmissionCanModify,
            ],
            'nombrePieceJointe' => $reponse->getNombrePieceJointe(),
            'piecesJointes' => array_map(fn($pj) => [
                'id' => $pj->getId(),
                'nom' => $pj->getNom(),
                'intitule' => $pj->getIntitule(),
                'chemin' => $pj->getChemin(),
                'type' => $pj->getType(),
                'createdAt' => $pj->getCreatedAt()?->format('Y-m-d H:i:s'),
            ], $piecesJointes),
            'piecesJointesAutresTransmissions' => $piecesJointesAutresTransmissions,
            'createdAt' => $reponse->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $reponse->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];

        $this->actionLogger->logView(
            'TransmissionReponse',
            $reponse->getId(),
            'Consultation d\'une transmission reponse',
            [
                'transmission_reponse' => $responseData,
            ]
        );

        return $this->json($responseData, 200);
    }

    /**
     * @return array<int, int>
     */
    private function resolveCourrierInterneIds(\App\Entity\Cour\TransmissionReponse $reponse): array
    {
        $ids = $reponse->getIdCourrierInternes();
        if (!is_array($ids)) {
            $fallbackId = $reponse->getId();
            return $fallbackId !== null ? [(int) $fallbackId] : [];
        }

        $normalized = [];
        foreach ($ids as $id) {
            if (is_numeric($id)) {
                $normalized[] = (int) $id;
            }
        }

        if (empty($normalized)) {
            $fallbackId = $reponse->getId();
            return $fallbackId !== null ? [(int) $fallbackId] : [];
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return array{0: bool, 1: ?int, 2: bool}
     */
    private function computeTransmissionPermissions(
        \App\Entity\Cour\TransmissionReponse $reponse,
        ?\App\Entity\Core\User $currentUser
    ): array {
        $canTransmit = true;
        $latestUserTransmissionId = null;
        $latestUserTransmissionCanModify = false;

        $linkedIds = $this->resolveCourrierInterneIds($reponse);
        if (empty($linkedIds) || !$currentUser) {
            return [$canTransmit, $latestUserTransmissionId, $latestUserTransmissionCanModify];
        }

        $latestUserTransmission = null;
        $latestOverallTransmission = null;
        foreach ($linkedIds as $courrierInterneId) {
            $latestTransmissionForLinked = $this->transmissionReponseRepository
                ->findLatestByCourrierInterneId($courrierInterneId);

            if ($latestTransmissionForLinked) {
                $shouldReplaceOverall = false;
                if (!$latestOverallTransmission) {
                    $shouldReplaceOverall = true;
                } else {
                    $currentCreated = $latestTransmissionForLinked->getCreatedAt();
                    $latestCreated = $latestOverallTransmission->getCreatedAt();

                    if ($currentCreated && (!$latestCreated || $currentCreated > $latestCreated)) {
                        $shouldReplaceOverall = true;
                    } elseif (!$currentCreated && !$latestCreated && $latestTransmissionForLinked->getId() > $latestOverallTransmission->getId()) {
                        $shouldReplaceOverall = true;
                    }
                }

                if ($shouldReplaceOverall) {
                    $latestOverallTransmission = $latestTransmissionForLinked;
                }
            }

            $userTransmission = $this->transmissionReponseRepository
                ->findLatestByCourrierInterneIdAndRedacteurId($courrierInterneId, $currentUser->getId());

            if (!$userTransmission) {
                continue;
            }

            $shouldReplace = false;
            if (!$latestUserTransmission) {
                $shouldReplace = true;
            } else {
                $currentCreated = $userTransmission->getCreatedAt();
                $latestCreated = $latestUserTransmission->getCreatedAt();

                if ($currentCreated && (!$latestCreated || $currentCreated > $latestCreated)) {
                    $shouldReplace = true;
                } elseif (!$currentCreated && !$latestCreated && $userTransmission->getId() > $latestUserTransmission->getId()) {
                    $shouldReplace = true;
                }
            }

            if ($shouldReplace) {
                $latestUserTransmission = $userTransmission;
            }
        }

        if ($latestOverallTransmission?->getIdRedacteur() && $latestOverallTransmission->getIdRedacteur()->getId() === $currentUser->getId()) {
            $canTransmit = false;
        }

        if ($latestUserTransmission) {
            $latestUserTransmissionId = $latestUserTransmission->getId();
            $latestUserTransmissionCanModify = !$latestUserTransmission->isAccuseReception();
        }

        return [$canTransmit, $latestUserTransmissionId, $latestUserTransmissionCanModify];
    }
}
