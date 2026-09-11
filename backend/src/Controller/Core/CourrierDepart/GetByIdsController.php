<?php

namespace App\Controller\Core\CourrierDepart;

use App\Repository\Cour\CourrierDepartRepository;
use App\Repository\Cour\PieceJointeRepository;
use App\Repository\Core\CorrespondantRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "CourrierDepart")]
class GetByIdsController extends AbstractController
{
    public function __construct(
        private CourrierDepartRepository $courrierDepartRepository,
        private PieceJointeRepository $pieceJointeRepository,
        private CorrespondantRepository $correspondantRepository,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger
    ) {}

    #[Route('/core/courrier-depart/by-ids', name: 'app_core_courrier_depart_get_by_ids', methods: ['GET'])]
    #[OA\Get(
        path: '/core/courrier-depart/by-ids',
        summary: 'Récupérer plusieurs courriers de départ par leurs IDs',
        description: 'Retourne les détails de plusieurs courriers de départ en passant leurs IDs en paramètre (query string pour GET ou body pour POST).',
        tags: ['CourrierDepart'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'ids',
                in: 'query',
                required: false,
                description: 'Liste des identifiants des courriers de départ séparés par des virgules (ex: 1,2,3)',
                schema: new OA\Schema(type: 'string', example: '1,2,3')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des courriers de départ trouvés avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: '3 courrier(s) de départ trouvés.'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'numeroReference', type: 'string', example: 'CD-2025-001'),
                                    new OA\Property(property: 'numeroActe', type: 'string', example: 'ACT-001'),
                                    new OA\Property(property: 'dateSignature', type: 'string', format: 'date', example: '2025-02-14'),
                                    new OA\Property(property: 'typeCourrier', type: 'string', example: 'Lettre officielle'),
                                    new OA\Property(property: 'commentaire', type: 'string', example: 'Courrier urgent'),
                                    new OA\Property(property: 'classeCourrier', type: 'string', example: 'Urgent'),
                                    new OA\Property(property: 'categorie', type: 'string', example: 'Administrative'),
                                    new OA\Property(property: 'document', type: 'string', example: '/uploads/courrier_depart/document/65ff44c4a8b1f.pdf'),
                                    new OA\Property(property: 'email', type: 'string', example: 'contact@example.com'),
                                    new OA\Property(property: 'numeroTelephone', type: 'string', example: '+237 690 000 000'),
                                    new OA\Property(
                                        property: 'provenancesCopie',
                                        type: 'array',
                                        items: new OA\Items(
                                            type: 'object',
                                            properties: [
                                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                                new OA\Property(property: 'nom', type: 'string', example: 'Ministère de l\'Agriculture'),
                                            ]
                                        )
                                    ),
                                    new OA\Property(
                                        property: 'piecesJointes',
                                        type: 'array',
                                        items: new OA\Items(
                                            type: 'object',
                                            properties: [
                                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                                new OA\Property(property: 'nom', type: 'string', example: 'annexe.pdf'),
                                                new OA\Property(property: 'chemin', type: 'string', example: '/uploads/courrier_depart/pieces/65ff44c4a8b1f.pdf'),
                                                new OA\Property(property: 'type', type: 'string', example: 'application/pdf'),
                                                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-02-14 10:30:00'),
                                            ]
                                        )
                                    ),
                                    new OA\Property(
                                        property: 'destinataire',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                            new OA\Property(property: 'nom', type: 'string', example: 'Société ABC'),
                                            new OA\Property(property: 'adresse', type: 'string', example: 'Yaoundé, Cameroun'),
                                            new OA\Property(property: 'telephone', type: 'string', example: '+237 690 000 000'),
                                            new OA\Property(property: 'email', type: 'string', example: 'contact@abc.cm'),
                                        ],
                                        nullable: true
                                    ),
                                    new OA\Property(
                                        property: 'signataire',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                            new OA\Property(property: 'fullName', type: 'string', example: 'Jean Dupont'),
                                            new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@minepia.cm'),
                                            new OA\Property(property: 'phone', type: 'string', example: '+237 690 000 000'),
                                        ],
                                        nullable: true
                                    ),
                                    new OA\Property(
                                        property: 'courrier',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                            new OA\Property(property: 'numero', type: 'string', example: 'C-2025-001'),
                                            new OA\Property(property: 'reference', type: 'string', example: 'REF-001'),
                                            new OA\Property(property: 'objet', type: 'string', example: 'Demande d\'information'),
                                            new OA\Property(property: 'commentaire', type: 'string', example: 'À traiter en priorité'),
                                            new OA\Property(property: 'dateArrivee', type: 'string', format: 'date', example: '2025-02-10'),
                                            new OA\Property(property: 'dateEnregistrement', type: 'string', format: 'date', example: '2025-02-11'),
                                            new OA\Property(property: 'typeCourrier', type: 'string', example: 'Lettre'),
                                            new OA\Property(property: 'provenance', type: 'object'),
                                            new OA\Property(property: 'priorite', type: 'string', example: 'Haute'),
                                            new OA\Property(property: 'statut', type: 'string', example: 'Traité'),
                                            new OA\Property(property: 'isConfidentiel', type: 'boolean', example: false),
                                        ],
                                        nullable: true
                                    ),
                                    new OA\Property(property: 'isDelete', type: 'boolean', example: false),
                                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-02-14 10:30:00'),
                                    new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2025-02-14 10:30:00'),
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Paramètre ids manquant ou invalide.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    #[OA\Post(
        path: '/core/courrier-depart/by-ids',
        summary: 'Récupérer plusieurs courriers de départ par leurs IDs (POST)',
        description: 'Retourne les détails de plusieurs courriers de départ en passant leurs IDs dans le body de la requête POST.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'ids',
                        type: 'array',
                        items: new OA\Items(type: 'integer'),
                        example: [1, 2, 3],
                        description: 'Liste des identifiants des courriers de départ'
                    )
                ],
                required: ['ids']
            )
        ),
        tags: ['CourrierDepart'],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des courriers de départ trouvés avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: '3 courrier(s) de départ trouvés.'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object'))
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Paramètre ids manquant ou invalide.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function getCourriersDepartByIds(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetCourriersDepartByIds');

        // RÃ©cupÃ©rer les IDs depuis le query string (GET) ou le body (POST)
        $ids = [];
        
        if ($request->getMethod() === 'POST') {
            $data = json_decode($request->getContent(), true);
            if (isset($data['ids']) && is_array($data['ids'])) {
                $ids = array_map('intval', $data['ids']);
            }
        } else {
            $idsParam = $request->query->get('ids');
            if ($idsParam) {
                $ids = array_map('intval', array_filter(explode(',', $idsParam)));
            }
        }

        // Validation
        if (empty($ids)) {
            return $this->json([
                'code' => 400,
                'message' => 'Le paramètre "ids" est requis et doit contenir au moins un identifiant.'
            ], 400);
        }

        // RÃ©cupÃ©rer les courriers dÃ©part
        $courriersDepart = $this->courrierDepartRepository->createQueryBuilder('cd')
            ->leftJoin('cd.destinataire', 'd')
            ->leftJoin('cd.idSignataire', 's')
            ->leftJoin('cd.idCourrier', 'c')
            ->leftJoin('c.typeCourrier', 'tc')
            ->leftJoin('c.idProvenance', 'prov')
            ->leftJoin('prov.categories', 'cat')
            ->addSelect('d', 's', 'c', 'tc', 'prov', 'cat')
            ->where('cd.id IN (:ids)')
            ->andWhere('cd.isDelete = :isDelete')
            ->setParameter('ids', $ids)
            ->setParameter('isDelete', false)
            ->getQuery()
            ->getResult();

        if (empty($courriersDepart)) {
            return $this->json([
                'code' => 404,
                'message' => 'Aucun courrier de départ trouvé pour les IDs fournis.',
                'data' => []
            ], 404);
        }

        // PrÃ©parer les donnÃ©es de rÃ©ponse
        $responseData = [];
        
        foreach ($courriersDepart as $courrierDepart) {
            // ðŸ“Ž RÃ©cupÃ©rer les piÃ¨ces jointes
            $piecesJointes = $this->pieceJointeRepository->findBy([
                'idParent' => $courrierDepart->getId(),
                'typeParent' => 'CourrierDepart',
                'isDelete' => false
            ]);

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

            $responseData[] = [
                'id' => $courrierDepart->getId(),
                'numeroReference' => $courrierDepart->getNumeroReference(),
                'numeroActe' => $courrierDepart->getNumeroActe(),
                'dateSignature' => $courrierDepart->getDateSignature()?->format('Y-m-d'),
                'typeCourrier' => $courrierDepart->getTypeCourrier(),
                'commentaire' => $courrierDepart->getCommentaire(),
                'classeCourrier' => $courrierDepart->getClasseCourrier(),
                'categorie' => $courrierDepart->getCategorie(),
                'document' => $courrierDepart->getDocument(),
                'email' => $courrierDepart->getEmail(),
                'numeroTelephone' => $courrierDepart->getNumeroTelephone(),
                'provenancesCopie' => $provenancesCopieEnriched,
                'piecesJointes' => array_map(fn($pj) => [
                    'id' => $pj->getId(),
                    'nom' => $pj->getNom(),
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
                ] : null,
                'isDelete' => $courrierDepart->isDelete(),
                'createdAt' => $courrierDepart->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updatedAt' => $courrierDepart->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        return $this->json([
            'code' => 200,
            'message' => sprintf('%d courrier(s) de départ trouvés.', count($courriersDepart)),
            'data' => $responseData
        ]);
    }
}
