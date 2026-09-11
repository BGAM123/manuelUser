<?php

namespace App\Controller\Core\CourrierDepart;

use App\Repository\Cour\CourrierDepartRepository;
use App\Repository\Cour\PieceJointeRepository;
use App\Repository\Cour\TransmissionRepository;
use App\Repository\Core\CorrespondantRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "CourrierDepart")]
class GetController extends AbstractController
{
    public function __construct(
        private CourrierDepartRepository $courrierDepartRepository,
        private PieceJointeRepository $pieceJointeRepository,
        private TransmissionRepository $transmissionRepository,
        private CorrespondantRepository $correspondantRepository,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger
    ) {}

    #[Route('/core/courrier-depart/{id<\d+>}', name: 'app_core_courrier_depart_get', methods: ['GET'])]
    #[OA\Get(
        path: '/core/courrier-depart/{id}',
        summary: 'Récupérer un courrier de départ par ID',
        description: 'Retourne les détails d\'un courrier de départ spécifique avec les informations du courrier lié, le document principal et les pièces jointes.',
        tags: ['CourrierDepart'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant du courrier de départ',
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Courrier de départ trouvé avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'numeroReference', type: 'string', example: 'CD-2025-001'),
                        new OA\Property(property: 'statut', type: 'string', example: 'Transmis'),
                        new OA\Property(property: 'dateSignature', type: 'string', format: 'date', example: '2025-02-14'),
                        new OA\Property(property: 'typeCourrier', type: 'string', example: 'Lettre officielle'),
                        new OA\Property(property: 'commentaire', type: 'string', example: 'Courrier urgent'),
                        new OA\Property(property: 'classeCourrier', type: 'string', example: 'Urgent'),
                        new OA\Property(property: 'categorie', type: 'string', example: 'Administrative'),
                        new OA\Property(property: 'document', type: 'string', example: '/uploads/courrier_depart/document/65ff44c4a8b1f.pdf', description: 'Chemin du document principal'),
                        new OA\Property(property: 'nombrePieceJointe', type: 'integer', example: 2),
                        new OA\Property(
                            property: 'provenancesCopie',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'nom', type: 'string', example: 'Ministère de l\'Agriculture'),
                                ]
                            ),
                            description: 'Liste des correspondants en copie avec leurs détails'
                        ),
                        new OA\Property(
                            property: 'piecesJointes',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'nom', type: 'string', example: 'annexe.pdf'),
                                    new OA\Property(property: 'intitule', type: 'string', example: 'Rapport financier', nullable: true),
                                    new OA\Property(property: 'chemin', type: 'string', example: '/uploads/courrier_depart/pieces/65ff44c4a8b1f.pdf'),
                                    new OA\Property(property: 'type', type: 'string', example: 'application/pdf'),
                                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-02-14 10:30:00'),
                                ]
                            )
                        ),
                        new OA\Property(
                            property: 'transmissions',
                            type: 'array',
                            description: 'Transmissions liees au courrier associe',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 12),
                                    new OA\Property(property: 'serviceDestinataire', type: 'object'),
                                    new OA\Property(property: 'emetteur', type: 'object'),
                                    new OA\Property(property: 'structuresCopie', type: 'array', items: new OA\Items(type: 'integer')),
                                    new OA\Property(property: 'dateInstruction', type: 'string', example: '2025-12-18 10:30:00'),
                                    new OA\Property(property: 'dateReception', type: 'string', nullable: true, example: '2025-12-18 12:05:00'),
                                    new OA\Property(property: 'instruction', type: 'string', example: 'A traiter en urgence'),
                                    new OA\Property(property: 'delaiTraitement', type: 'integer', example: 5),
                                    new OA\Property(property: 'typeTransfert', type: 'string', example: 'Pour traitement'),
                                    new OA\Property(property: 'accuseReception', type: 'boolean', example: false),
                                    new OA\Property(property: 'statut', type: 'string', example: 'Transmis'),
                                    new OA\Property(property: 'isinstance', type: 'boolean', example: false),
                                    new OA\Property(property: 'isArchive', type: 'boolean', example: false),
                                    new OA\Property(property: 'nombrePieceJointe', type: 'integer', example: 2),
                                    new OA\Property(
                                        property: 'piecesJointes',
                                        type: 'array',
                                        items: new OA\Items(
                                            type: 'object',
                                            properties: [
                                                new OA\Property(property: 'id', type: 'integer', example: 250),
                                                new OA\Property(property: 'nom', type: 'string', example: 'bordereau.pdf'),
                                                new OA\Property(property: 'intitule', type: 'string', nullable: true, example: 'Bordereau de transmission'),
                                                new OA\Property(property: 'chemin', type: 'string', example: '/uploads/courrier/pieces/6942c641c1399.pdf'),
                                                new OA\Property(property: 'type', type: 'string', example: 'application/pdf'),
                                            ]
                                        )
                                    ),
                                ]
                            )
                        ),
                        new OA\Property(property: 'isDelete', type: 'boolean', example: false),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-02-14 10:30:00'),
                        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2025-02-14 10:30:00'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Courrier de départ non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function getCourrierDepart(int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetCourrierDepart');

        $courrierDepart = $this->courrierDepartRepository->createQueryBuilder('cd')
            ->leftJoin('cd.destinataire', 'd')
            ->leftJoin('cd.idSignataire', 's')
            ->leftJoin('cd.idCourrier', 'c')
            ->leftJoin('c.typeCourrier', 'tc')
            ->leftJoin('c.idProvenance', 'prov')
            ->leftJoin('prov.categories', 'cat')
            ->addSelect('d', 's', 'c', 'tc', 'prov', 'cat')
            ->where('cd.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$courrierDepart) {
            return $this->json(['code' => 404, 'message' => 'Courrier de départ non trouvé.'], 404);
        }

        // 📎 Récupérer les pièces jointes
        $piecesJointes = $this->pieceJointeRepository->findBy([
            'idParent' => $courrierDepart->getId(),
            'typeParent' => 'CourrierDepart',
            'isDelete' => false
        ]);

        $piecesJointesCourrier = [];
        if ($courrierDepart->getIdCourrier()) {
            $piecesJointesCourrier = $this->pieceJointeRepository->findBy([
                'idParent' => $courrierDepart->getIdCourrier()->getId(),
                'typeParent' => 'Courrier',
                'isDelete' => false
            ]);
        }

        // Récupérer les pièces jointes liées au courrier interne (si présent)
        $piecesJointesCourrierInterne = [];
        if ($courrierDepart->getIdCourrierInterne()) {
            $piecesJointesCourrierInterne = $this->pieceJointeRepository->findBy([
                'idParent' => $courrierDepart->getIdCourrierInterne()->getId(),
                'typeParent' => 'CourrierInterne',
                'isDelete' => false
            ]);
        }

        // âœ… Enrichir provenancesCopie avec id et nom des correspondants
        $provenancesCopieEnriched = [];
        if ($courrierDepart->getProvenancesCopie()) {
            $correspondants = $this->correspondantRepository->createQueryBuilder('c')
                ->where('c.id IN (:ids)')
                ->setParameter('ids', $courrierDepart->getProvenancesCopie())
                ->getQuery()
                ->getResult();

            foreach ($correspondants as $corr) {
                $provenancesCopieEnriched[] = [
                    'id' => $corr->getId(),
                    'nom' => $corr->getNom(),
                ];
            }
        }

        $courrierArriveId = $courrierDepart->getIdCourrier()?->getId();
        $transmissions = [];
        $piecesJointesTransmissionGrouped = [];
        if ($courrierArriveId !== null) {
            $transmissions = $this->transmissionRepository->createQueryBuilder('t')
                ->leftJoin('t.idServiceDestinataire', 'sd')
                ->leftJoin('t.idEmetteur', 'em')
                ->addSelect('sd', 'em')
                ->where('t.idCourrier = :courrierId')
                ->andWhere('t.isDelete = false')
                ->setParameter('courrierId', $courrierArriveId)
                ->orderBy('t.dateInstruction', 'ASC')
                ->getQuery()
                ->getResult();

            $transmissionIds = array_map(fn($t) => $t->getId(), $transmissions);
            if (!empty($transmissionIds)) {
                $piecesJointesTransmission = $this->pieceJointeRepository->createQueryBuilder('pj')
                    ->where('pj.typeParent = :typeParent')
                    ->andWhere('pj.idParent IN (:ids)')
                    ->andWhere('pj.isDelete = false')
                    ->setParameter('typeParent', 'Transmission')
                    ->setParameter('ids', $transmissionIds)
                    ->orderBy('pj.id', 'ASC')
                    ->getQuery()
                    ->getResult();

                foreach ($piecesJointesTransmission as $pj) {
                    $piecesJointesTransmissionGrouped[$pj->getIdParent()][] = $pj;
                }
            }
        }

        $numeroReference = $courrierDepart->getNumeroReference();
        if (
            $courrierDepart->getIdCourrier() === null
            && $courrierDepart->getIdCourrierInterne() !== null
            && !empty($courrierDepart->getIdCourrierInterne()->getNumero())
        ) {
            $numeroReference = $courrierDepart->getIdCourrierInterne()->getNumero();
        }

        $responseData = [
            'id' => $courrierDepart->getId(),
            'numeroReference' => $numeroReference,
            'numeroActe' => $courrierDepart->getNumeroActe(),
            'statut' => $courrierDepart->getStatut()??'Transmis',
            'dateSignature' => $courrierDepart->getDateSignature()?->format('Y-m-d'),
            'typeCourrier' => $courrierDepart->getTypeCourrier(),
            'commentaire' => $courrierDepart->getCommentaire(),
            'classeCourrier' => $courrierDepart->getClasseCourrier(),
            'categorie' => $courrierDepart->getCategorie(),
            'document' => $courrierDepart->getDocument(),
            'email' => $courrierDepart->getEmail(),
            'numeroTelephone' => $courrierDepart->getNumeroTelephone(),
            'nombrePieceJointe' => $courrierDepart->getNombrePieceJointe(),
            'provenancesCopie' => $provenancesCopieEnriched,
            'piecesJointes' => array_map(fn($pj) => [
                'id' => $pj->getId(),
                'nom' => $pj->getNom(),
                'intitule' => $pj->getIntitule(),
                'chemin' => $pj->getChemin(),
                'type' => $pj->getType(),
                'createdAt' => $pj->getCreatedAt()?->format('Y-m-d H:i:s'),
            ], $piecesJointes),
            'destinataire' => $courrierDepart->getDestinataire() ? [
                'id' => $courrierDepart->getDestinataire()->getId(),
                'nom' => $courrierDepart->getDestinataire()->getNom(),
                'adresse' => $courrierDepart->getDestinataire()->getAdresse(),
                'telephone' => $courrierDepart->getDestinataire()->getTelephone(),
                'email' => $courrierDepart->getDestinataire()->getEmail(),
            ] : null,
            'signataire' => $courrierDepart->getIdSignataire() ? [
                'id' => $courrierDepart->getIdSignataire()->getId(),
                'fullName' => $courrierDepart->getIdSignataire()->getFullName(),
                'email' => $courrierDepart->getIdSignataire()->getEmail(),
                'phone' => $courrierDepart->getIdSignataire()->getPhone(),
            ] : null,
            'courrier' => $courrierDepart->getIdCourrier() ? [
                'id' => $courrierDepart->getIdCourrier()->getId(),
                'numero' => $courrierDepart->getIdCourrier()->getNumero(),
                'reference' => $courrierDepart->getIdCourrier()->getReference(),
                'objet' => $courrierDepart->getIdCourrier()->getObjet(),
                'commentaire' => $courrierDepart->getIdCourrier()->getCommentaire(),
                'dateArrivee' => $courrierDepart->getIdCourrier()->getDateArrivee()?->format('Y-m-d'),
                'dateEnregistrement' => $courrierDepart->getIdCourrier()->getDateEnregistrement()?->format('Y-m-d'),
                'typeCourrier' => $courrierDepart->getIdCourrier()->getTypeCourrier()?->getNom(),
                'provenance' => [
                    'id' => $courrierDepart->getIdCourrier()->getIdProvenance()?->getId(),
                    'nom' => $courrierDepart->getIdCourrier()->getIdProvenance()?->getNom(),
                    'categorie' => $courrierDepart->getIdCourrier()->getIdProvenance() && $courrierDepart->getIdCourrier()->getIdProvenance()->getCategories()->count() > 0 
                        ? $courrierDepart->getIdCourrier()->getIdProvenance()->getCategories()->first()->getNom()
                        : null,
                ],
                'priorite' => $courrierDepart->getIdCourrier()->getPriorite(),
                'statut' => $courrierDepart->getIdCourrier()->getStatut(),
                'isConfidentiel' => $courrierDepart->getIdCourrier()->isConfidentiel(),
                'document' => $courrierDepart->getIdCourrier()->getDocument(),
                'piecesJointes' => array_map(fn($pj) => [
                    'id' => $pj->getId(),
                    'nom' => $pj->getNom(),
                    'intitule' => $pj->getIntitule(),
                    'chemin' => $pj->getChemin(),
                    'type' => $pj->getType(),
                    'createdAt' => $pj->getCreatedAt()?->format('Y-m-d H:i:s'),
                ], $piecesJointesCourrier),
                ] : null,
                'courrierInterne' => $courrierDepart->getIdCourrierInterne() ? [
                    'id' => $courrierDepart->getIdCourrierInterne()->getId(),
                    'numero' => $courrierDepart->getIdCourrierInterne()->getNumero(),
                    'objet' => $courrierDepart->getIdCourrierInterne()->getObjet(),
                    'commentairePublic' => $courrierDepart->getIdCourrierInterne()->getCommentairePublic(),
                    'commentaireInterne' => $courrierDepart->getIdCourrierInterne()->getCommentaireInterne(),
                    'dateReponse' => $courrierDepart->getIdCourrierInterne()->getDateReponse()?->format('Y-m-d'),
                    'typeReponse' => $courrierDepart->getIdCourrierInterne()->getTypeReponse()?->getNom(),
                    'priorite' => $courrierDepart->getIdCourrierInterne()->getPriorite(),
                    'statut' => $courrierDepart->getIdCourrierInterne()->getStatut(),
                    'isGeled' => $courrierDepart->getIdCourrierInterne()->isGeled(),
                    'isinstance' => $courrierDepart->getIdCourrierInterne()->isinstance(),
                    'nombrePieceJointe' => $courrierDepart->getIdCourrierInterne()->getNombrePieceJointe(),
                    'serviceDestinataire' => $courrierDepart->getIdCourrierInterne()->getIdServiceDestinataire() ? [
                        'id' => $courrierDepart->getIdCourrierInterne()->getIdServiceDestinataire()->getId(),
                        'nom' => $courrierDepart->getIdCourrierInterne()->getIdServiceDestinataire()->getNom(),
                    ] : null,
                    'redacteur' => $courrierDepart->getIdCourrierInterne()->getIdRedacteur() ? [
                        'id' => $courrierDepart->getIdCourrierInterne()->getIdRedacteur()->getId(),
                        'fullName' => $courrierDepart->getIdCourrierInterne()->getIdRedacteur()->getFullName(),
                        'email' => $courrierDepart->getIdCourrierInterne()->getIdRedacteur()->getEmail(),
                    ] : null,
                    'piecesJointes' => array_map(fn($pj) => [
                        'id' => $pj->getId(),
                        'nom' => $pj->getNom(),
                        'intitule' => $pj->getIntitule(),
                        'chemin' => $pj->getChemin(),
                        'type' => $pj->getType(),
                        'createdAt' => $pj->getCreatedAt()?->format('Y-m-d H:i:s'),
                    ], $piecesJointesCourrierInterne),
                    'createdAt' => $courrierDepart->getIdCourrierInterne()->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'updatedAt' => $courrierDepart->getIdCourrierInterne()->getUpdatedAt()?->format('Y-m-d H:i:s'),
                ] : null,
            'transmissions' => array_map(function ($t) use ($piecesJointesTransmissionGrouped) {
                $transmissionPieces = $piecesJointesTransmissionGrouped[$t->getId()] ?? [];

                return [
                    'id' => $t->getId(),
                    'serviceDestinataire' => $t->getIdServiceDestinataire() ? [
                        'id' => $t->getIdServiceDestinataire()->getId(),
                        'nom' => $t->getIdServiceDestinataire()->getNom(),
                        'sigle' => $t->getIdServiceDestinataire()->getSigle(),
                    ] : null,
                    'emetteur' => $t->getIdEmetteur() ? [
                        'id' => $t->getIdEmetteur()->getId(),
                        'fullName' => $t->getIdEmetteur()->getFullName(),
                        'email' => $t->getIdEmetteur()->getEmail(),
                    ] : null,
                    'structuresCopie' => $t->getStructuresCopie(),
                    'dateInstruction' => $t->getDateInstruction()?->format('Y-m-d H:i:s'),
                    'dateReception' => $t->getDateReception()?->format('Y-m-d H:i:s'),
                    'instruction' => $t->getInstruction(),
                    'delaiTraitement' => $t->getDelaiTraitement(),
                    'typeTransfert' => $t->getTypeTransfert(),
                    'accuseReception' => $t->isAccuseReception(),
                    'statut' => $t->getStatut(),
                    'isinstance' => $t->isinstance(),
                    'isArchive' => $t->isArchive(),
                    'nombrePieceJointe' => $t->getNombrePieceJointe(),
                    'pieceJointe' => $t->getPieceJointe(),
                    'piecesJointes' => array_map(fn($pj) => [
                        'id' => $pj->getId(),
                        'nom' => $pj->getNom(),
                        'intitule' => $pj->getIntitule(),
                        'chemin' => $pj->getChemin(),
                        'type' => $pj->getType(),
                    ], $transmissionPieces),
                    'createdAt' => $t->getCreatedAt()?->format('Y-m-d H:i:s'),
                ];
            }, $transmissions),
            'isDelete' => $courrierDepart->isDelete(),
            'createdAt' => $courrierDepart->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $courrierDepart->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];

        // Logger la consultation avec TOUTES les donnÃ©es du courrier
        $this->actionLogger->logView(
            'CourrierDepart',
            $courrierDepart->getId(),
            'Consultation d\'un courrier de départ',
            [
                'courrier' => $responseData
            ]
        );

        return $this->json($responseData, 200);
    }
}
