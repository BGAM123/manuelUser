<?php

namespace App\Controller\Core\Courrier;

use App\Repository\Cour\CourrierRepository;
use App\Repository\Cour\TransmissionRepository;
use App\Repository\Cour\PieceJointeRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "CourrierArrive")]
class GetController extends AbstractController
{
    public function __construct(
        private CourrierRepository $courrierRepository,
        private TransmissionRepository $transmissionRepository,
        private PieceJointeRepository $pieceJointeRepository,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/courrier/{id}', name: 'app_core_courrier_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(
        path: '/core/courrier/{id}',
        summary: 'Récupérer un courrier entrant avec ses transmissions et pièces jointes',
        tags: ['CourrierArrive'],
        description: "Retourne les détails complets d’un courrier entrant, incluant ses transmissions et ses pièces jointes.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID du courrier à récupérer',
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Courrier récupéré avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'reference', type: 'string', example: 'CA-00045/2025'),
                        new OA\Property(property: 'objet', type: 'string', example: 'Demande de subvention'),
                        new OA\Property(property: 'commentaire', type: 'string', example: 'Lettre prioritaire'),
                        new OA\Property(property: 'commentairePublic', type: 'string', nullable: true, example: 'Dossier traité et archivé'),
                        new OA\Property(property: 'commentaireInterne', type: 'string', nullable: true, example: 'Nécessite un suivi ultérieur'),
                        new OA\Property(property: 'priorite', type: 'string', example: 'Haute'),
                        new OA\Property(property: 'statut', type: 'string', example: 'Transmis'),
                        new OA\Property(property: 'nom', type: 'string', nullable: true, example: 'Jean Dupont'),
                        new OA\Property(property: 'civilite', type: 'string', nullable: true, example: 'M.'),
                        new OA\Property(property: 'matricule', type: 'string', nullable: true, example: 'EMP-00123'),
                        new OA\Property(property: 'telephone', type: 'string', nullable: true, example: '+237 6XX XXX XXX'),
                        new OA\Property(property: 'email', type: 'string', nullable: true, example: 'jean.dupont@example.com'),
                        new OA\Property(property: 'adresse', type: 'string', nullable: true, example: '123 Rue de la Paix, YaoundÃ©'),
                        new OA\Property(property: 'typeTransfert', type: 'string', nullable: true, example: 'Direct'),
                        new OA\Property(property: 'classeCourrier', type: 'string', nullable: true, example: 'Urgent'),
                        new OA\Property(property: 'categorie', type: 'string', nullable: true, example: 'Administrative'),
                        new OA\Property(property: 'nombrePieceJointe', type: 'integer', nullable: true, example: 3, description: 'Nombre de pièces jointes'),
                        new OA\Property(property: 'dateArrivee', type: 'string', format: 'date-time', example: '2025-02-14T09:30:00+00:00'),
                        new OA\Property(property: 'dateEnregistrement', type: 'string', format: 'date-time', example: '2025-02-14T09:35:00+00:00'),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-02-14T09:30:00+00:00'),
                        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2025-02-14T09:35:00+00:00'),
                        new OA\Property(property: 'idProvenance', type: 'object', nullable: true, properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 5),
                            new OA\Property(property: 'nom', type: 'string', example: 'Correspondant externe'),
                        ]),
                        new OA\Property(property: 'idServiceTraitant', type: 'object', properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 3),
                            new OA\Property(property: 'nom', type: 'string', example: 'Service Financier'),
                        ]),
                        new OA\Property(property: 'idCreateur', type: 'object', properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 2),
                            new OA\Property(property: 'nomComplet', type: 'string', example: 'Jean Dupont'),
                        ]),
                        new OA\Property(property: 'document', type: 'string', example: '/uploads/courrier/document/CA-00045.pdf'),
                        new OA\Property(property: 'piecesJointes', type: 'array', items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'nom', type: 'string', example: 'Annexe1.pdf'),
                                new OA\Property(property: 'intitule', type: 'string', nullable: true, example: 'Justificatif de domicile'),
                                new OA\Property(property: 'chemin', type: 'string', example: '/uploads/courrier/pieces/annexe1.pdf'),
                                new OA\Property(property: 'type', type: 'string', example: 'application/pdf'),
                            ]
                        )),
                        new OA\Property(property: 'transmissions', type: 'array', items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 5),
                                new OA\Property(property: 'dateInstruction', type: 'string', format: 'date-time', example: '2025-02-14T09:40:00+00:00'),
                                new OA\Property(property: 'instruction', type: 'string', example: 'À traiter en priorité.'),
                                new OA\Property(property: 'typeTransfert', type: 'string', example: 'Direct'),
                                new OA\Property(property: 'statut', type: 'string', example: 'En cours'),
                                new OA\Property(property: 'idServiceDestinataire', type: 'object', properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 3),
                                    new OA\Property(property: 'nom', type: 'string', example: 'Service Financier')
                                ]),
                                new OA\Property(property: 'idEmetteur', type: 'object', properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 2),
                                    new OA\Property(property: 'nomComplet', type: 'string', example: 'Jean Dupont')
                                ])
                            ]
                        )),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Courrier non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function getCourrier(int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetCourrier');

        // RÃ©cupÃ©ration du courrier avec ses entitÃ©s liÃ©es pour Ã©viter les requÃªtes lazy loading
        $courrier = $this->courrierRepository->createQueryBuilder('c')
            ->leftJoin('c.idServiceTraitant', 's')
            ->leftJoin('c.idCreateur', 'u')
            ->leftJoin('c.idProvenance', 'p')
            ->leftJoin('p.categories', 'cat')
            ->leftJoin('c.typeCourrier', 't')
            ->addSelect('s', 'u', 'p', 'cat', 't')
            ->where('c.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$courrier) {
            return $this->json(['code' => 404, 'message' => 'Courrier non trouvé.'], 404);
        }

        // RÃ©cupÃ©ration des transmissions avec leurs services pour Ã©viter lazy loading
        $transmissions = $this->transmissionRepository->createQueryBuilder('tr')
            ->leftJoin('tr.idServiceDestinataire', 'sd')
            ->leftJoin('tr.idEmetteur', 'em')
            ->addSelect('sd', 'em')
            ->where('tr.idCourrier = :courrier')
            ->setParameter('courrier', $courrier)
            ->orderBy('tr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        // âœ… RÃ©cupÃ©ration des piÃ¨ces jointes NON supprimÃ©es ET crÃ©Ã©es aprÃ¨s ou en mÃªme temps que le courrier
        $piecesJointes = $this->pieceJointeRepository->createQueryBuilder('pj')
            ->where('pj.idParent = :courrierId')
            ->andWhere('pj.typeParent = :typeParent')
            ->andWhere('pj.isDelete = :isDelete')
            ->andWhere('pj.createdAt >= :courrierCreatedAt')
            ->setParameter('courrierId', $courrier->getId())
            ->setParameter('typeParent', 'Courrier')
            ->setParameter('isDelete', false)
            ->setParameter('courrierCreatedAt', $courrier->getCreatedAt())
            ->orderBy('pj.id', 'ASC')
            ->getQuery()
            ->getResult();

        // Structuration manuelle pour Swagger (plutÃ´t que serializer brut)
        $responseData = [
            'id' => $courrier->getId(),
            'numero' => $courrier->getNumero(),
            'reference' => $courrier->getReference(),
            'objet' => $courrier->getObjet(),
            'commentaire' => $courrier->getCommentaire(),
            'commentairePublic' => $courrier->getCommentairePublic(),
            'commentaireInterne' => $courrier->getCommentaireInterne(),
            'priorite' => $courrier->getPriorite(),
            'statut' => $courrier->getStatut(),
            'nom' => $courrier->getNom(),
            'civilite' => $courrier->getCivilite(),
            'matricule' => $courrier->getMatricule(),
            'telephone' => $courrier->getTelephone(),
            'email' => $courrier->getEmail(),
            'adresse' => $courrier->getAdresse(),
            'typeTransfert' => $courrier->getTypeTransfert(),
            'classeCourrier' => $courrier->getClasseCourrier(),
            'categorie' => $courrier->getCategorie(),
            'nombrePieceJointe' => $courrier->getNombrePieceJointe(),
            'isConfidentiel' => $courrier->isConfidentiel(),
            'categorieProvenance' => $courrier->getIdProvenance() && $courrier->getIdProvenance()->getCategories()->count() > 0 
                ? $courrier->getIdProvenance()->getCategories()->first()->getNom()
                : null,
            'idProvenance' => $courrier->getIdProvenance() ? [
                'id' => $courrier->getIdProvenance()->getId(),
                'nom' => $courrier->getIdProvenance()->getNom()
            ] : null,
            'typeCourrier' => $courrier->getTypeCourrier()?->getNom(),
            'dateArrivee' => $courrier->getDateArrivee()?->format('Y-m-d H:i:s'),
            'dateEnregistrement' => $courrier->getDateEnregistrement()?->format('Y-m-d H:i:s'),
            'createdAt' => $courrier->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $courrier->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'idServiceTraitant' => [
                'id' => $courrier->getIdServiceTraitant()?->getId(),
                'nom' => $courrier->getIdServiceTraitant()?->getNom(),
            ],
            'idCreateur' => [
                'id' => $courrier->getIdCreateur()?->getId(),
                'nomComplet' => $courrier->getIdCreateur()?->getFullName(),
            ],
            'document' => $courrier->getDocument(),
            'piecesJointes' => array_map(fn($p) => [
                'id' => $p->getId(),
                'nom' => $p->getNom(),
                'intitule' => $p->getIntitule(),
                'chemin' => $p->getChemin(),
                'type' => $p->getType(),
            ], $piecesJointes),
            'transmissions' => array_map(function($t) {
                try {
                    $serviceDestinataire = null;
                    if ($t->getIdServiceDestinataire()) {
                        try {
                            // Force le chargement pour vÃ©rifier si l'entitÃ© existe
                            $service = $t->getIdServiceDestinataire();
                            $service->getId(); // DÃ©clenche le lazy loading
                            $serviceDestinataire = [
                                'id' => $service->getId(),
                                'nom' => $service->getNom(),
                            ];
                        } catch (\Doctrine\ORM\EntityNotFoundException $e) {
                            // Service supprimÃ©, on retourne null
                            $serviceDestinataire = null;
                        }
                    }

                    $emetteur = null;
                    if ($t->getIdEmetteur()) {
                        $emetteur = [
                            'id' => $t->getIdEmetteur()->getId(),
                            'nomComplet' => $t->getIdEmetteur()->getFullName(),
                        ];
                    }

                    return [
                        'id' => $t->getId(),
                        'dateInstruction' => $t->getDateInstruction()?->format('Y-m-d H:i:s'),
                        'instruction' => $t->getInstruction(),
                        'typeTransfert' => $t->getTypeTransfert(),
                        'statut' => $t->getStatut(),
                        'idServiceDestinataire' => $serviceDestinataire,
                        'idEmetteur' => $emetteur,
                    ];
                } catch (\Exception $e) {
                    // En cas d'erreur, retourner les donnÃ©es minimales
                    return [
                        'id' => $t->getId(),
                        'dateInstruction' => $t->getDateInstruction()?->format('Y-m-d H:i:s'),
                        'instruction' => $t->getInstruction(),
                        'typeTransfert' => $t->getTypeTransfert(),
                        'statut' => $t->getStatut(),
                        'idServiceDestinataire' => null,
                        'idEmetteur' => null,
                    ];
                }
            }, $transmissions)
        ];

        return $this->json($responseData, 200);
    }
}
