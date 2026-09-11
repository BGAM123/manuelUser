<?php

namespace App\Controller\Core\Reponse;

use App\Repository\Cour\ReponseRepository;
use App\Repository\Cour\PieceJointeRepository;
use App\Repository\Cour\TransmissionReponseRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Reponse")]
class GetController extends AbstractController
{
    public function __construct(
        private ReponseRepository $reponseRepository,
        private PieceJointeRepository $pieceJointeRepository,
        private TransmissionReponseRepository $transmissionReponseRepository,
        private AccessCheckerService $accessChecker,
        private EntityManagerInterface $entityManager,
        private UserActionLoggerService $actionLogger,
    ) {}

    #[Route('/core/reponse/{id}', name: 'app_core_reponse_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(
        path: '/core/reponse/{id}',
        summary: 'Récupérer une réponse par son ID',
        tags: ['Reponse'],
        description: "Retourne les détails complets d'une réponse, incluant ses pièces jointes.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Réponse récupérée avec succès.',
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
                        new OA\Property(property: 'idReponses', type: 'array', items: new OA\Items(type: 'integer'), example: [4, 5, 6]),
                        new OA\Property(
                            property: 'reponsesLiees',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 12),
                                    new OA\Property(property: 'statut', type: 'string', example: 'Reçu'),
                                    new OA\Property(
                                        property: 'serviceDestinataire',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 3),
                                            new OA\Property(property: 'nom', type: 'string', example: 'DAJ'),
                                            new OA\Property(property: 'sigle', type: 'string', example: 'DAJ'),
                                        ]
                                    ),
                        new OA\Property(property: 'idCourrierInternes', type: 'array', items: new OA\Items(type: 'integer'), example: [4, 5, 6]),
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
            new OA\Response(response: 404, description: 'Réponse non trouvée.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function show(int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetReponse');

        $reponse = $this->reponseRepository->find($id);

        if (!$reponse) {
            return $this->json(['code' => 404, 'message' => 'Réponse non trouvée.'], 404);
        }

        //  Récupérer les pièces jointes
        $piecesJointes = $this->pieceJointeRepository->findBy([
            'idParent' => $reponse->getId(),
            'typeParent' => 'Reponse',
            'isDelete' => false
        ]);

        //  Récupérer les types de courrier depuis typesCourrierIds
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

        //  Récupérer tous les courriers associés
        $courriers = [];
        foreach ($reponse->getCourriers() as $courrier) {
            $courriers[] = [
                'id' => $courrier->getId(),
                'numero' => $courrier->getNumero(),
                'reference' => $courrier->getReference(),
                'objet' => $courrier->getObjet(),
                'commentaire' => $courrier->getCommentaire(),
                'dateArrivee' => $courrier->getDateArrivee()?->format('Y-m-d'),
                'dateEnregistrement' => $courrier->getDateEnregistrement()?->format('Y-m-d H:i:s'),
                'typeCourrier' => [
                    'id' => $courrier->getTypeCourrier()?->getId(),
                    'nom' => $courrier->getTypeCourrier()?->getNom(),
                ],
                'provenance' => [
                    'id' => $courrier->getIdProvenance()?->getId(),
                    'nom' => $courrier->getIdProvenance()?->getNom(),
                ],
                'priorite' => $courrier->getPriorite(),
            ];
        }

        //  Récupérer le statut et le service destinataire de la dernière transmission réponse liée
        $reponsesLiees = [];
        if (is_array($reponse->getIdReponses())) {
            foreach ($reponse->getIdReponses() as $reponseId) {
                if (!is_numeric($reponseId)) {
                    continue;
                }
                $lastTransmission = $this->transmissionReponseRepository->findLatestByReponseId((int) $reponseId);
                $reponsesLiees[] = [
                    'id' => (int) $reponseId,
                    'statut' => $lastTransmission?->getStatut(),
                    'serviceDestinataire' => $lastTransmission?->getIdServiceDestinataire() ? [
                        'id' => $lastTransmission->getIdServiceDestinataire()->getId(),
                        'nom' => $lastTransmission->getIdServiceDestinataire()->getNom(),
                        'sigle' => $lastTransmission->getIdServiceDestinataire()->getSigle(),
                    ] : null,
                ];
            }
        }

        $responseData = [
            'id' => $reponse->getId(),
            'courriers' => $courriers,
            'typesCourrier' => $typesCourrier,
            'idTransmission' => $reponse->getIdTransmission(),
            'idReponses' => $reponse->getIdReponses(),
            'idCourrierInternes' => $reponse->getIdCourrierInternes(),
            'reponsesLiees' => $reponsesLiees,
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
            'nombrePieceJointe' => $reponse->getNombrePieceJointe(),
            'piecesJointes' => array_map(fn($pj) => [
                'id' => $pj->getId(),
                'nom' => $pj->getNom(),
                'intitule' => $pj->getIntitule(),
                'chemin' => $pj->getChemin(),
                'type' => $pj->getType(),
                'createdAt' => $pj->getCreatedAt()?->format('Y-m-d H:i:s'),
            ], $piecesJointes),
            'createdAt' => $reponse->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $reponse->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];

        // Logger la consultation
        $this->actionLogger->logView(
            'Reponse',
            $reponse->getId(),
            'Consultation d\'une réponse',
            [
                'reponse' => $responseData,
            ]
        );

        return $this->json($responseData, 200);
    }
}
