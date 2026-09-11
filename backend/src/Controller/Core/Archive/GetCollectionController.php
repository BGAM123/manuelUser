<?php

namespace App\Controller\Core\Archive;

use App\Repository\Core\ArchiveRepository;
use App\Repository\Core\SalleRepository;
use App\Repository\Core\CoffreRepository;
use App\Repository\Cour\CourrierRepository;
use App\Repository\Cour\TransmissionRepository;
use App\Repository\Cour\CourrierDepartRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Archive")]
class GetCollectionController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker,
        private ArchiveRepository $archiveRepository,
        private SalleRepository $salleRepository,
        private CoffreRepository $coffreRepository,
        private CourrierRepository $courrierRepository,
        private TransmissionRepository $transmissionRepository,
        private CourrierDepartRepository $courrierDepartRepository,
    ) {}

    #[Route('/core/archive', name: 'app_core_archive_get_collection', methods: ['GET'])]
    #[OA\Get(
        path: '/core/archive',
        summary: 'Lister les archives groupées par salle et coffre',
        tags: ['Archive'],
        description: "Récupère la liste de toutes les archives groupées par salle, puis par coffre, avec les IDs des documents archivés (courrier, transmission, courrier départ).",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id_salle',
                in: 'query',
                required: false,
                description: 'Filtrer par ID de salle',
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'id_coffre',
                in: 'query',
                required: false,
                description: 'Filtrer par ID de coffre',
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'is_active',
                in: 'query',
                required: false,
                description: 'Filtrer par statut actif',
                schema: new OA\Schema(type: 'boolean')
            ),
            new OA\Parameter(
                name: 'is_delete',
                in: 'query',
                required: false,
                description: 'Inclure les archives supprimées',
                schema: new OA\Schema(type: 'boolean', default: false)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des archives groupées récupérée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'total', type: 'integer', example: 150),
                        new OA\Property(property: 'totalSalles', type: 'integer', example: 3),
                        new OA\Property(property: 'totalCoffres', type: 'integer', example: 10),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(
                                        property: 'salle',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                            new OA\Property(property: 'nom', type: 'string', example: 'Salle A'),
                                            new OA\Property(property: 'isActive', type: 'boolean', example: true),
                                        ]
                                    ),
                                    new OA\Property(property: 'totalArchives', type: 'integer', example: 50),
                                    new OA\Property(property: 'totalCoffres', type: 'integer', example: 3),
                                    new OA\Property(
                                        property: 'coffres',
                                        type: 'array',
                                        items: new OA\Items(
                                            type: 'object',
                                            properties: [
                                                new OA\Property(
                                                    property: 'coffre',
                                                    type: 'object',
                                                    properties: [
                                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                                        new OA\Property(property: 'nom', type: 'string', example: 'Coffre A1'),
                                                        new OA\Property(property: 'nombrePlaceActuelle', type: 'integer', example: 15),
                                                        new OA\Property(property: 'tailleMaximale', type: 'integer', example: 20),
                                                        new OA\Property(property: 'isActive', type: 'boolean', example: true),
                                                    ]
                                                ),
                                                new OA\Property(property: 'totalArchives', type: 'integer', example: 15),
                                                new OA\Property(
                                                    property: 'archives',
                                                    type: 'array',
                                                    items: new OA\Items(
                                                        type: 'object',
                                                        properties: [
                                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                                            new OA\Property(property: 'idSalle', type: 'integer', example: 1),
                                                            new OA\Property(property: 'idCoffre', type: 'integer', example: 1),
                                                            new OA\Property(property: 'isActive', type: 'boolean', example: true),
                                                            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                                                            new OA\Property(
                                                                property: 'courrier',
                                                                type: 'object',
                                                                nullable: true,
                                                                properties: [
                                                                    new OA\Property(property: 'id', type: 'integer'),
                                                                    new OA\Property(property: 'numero', type: 'string'),
                                                                    new OA\Property(property: 'reference', type: 'string'),
                                                                    new OA\Property(property: 'objet', type: 'string'),
                                                                    new OA\Property(property: 'dateArrivee', type: 'string', format: 'date-time'),
                                                                    new OA\Property(property: 'priorite', type: 'string'),
                                                                    new OA\Property(property: 'statut', type: 'string'),
                                                                ]
                                                            ),
                                                            new OA\Property(
                                                                property: 'transmission',
                                                                type: 'object',
                                                                nullable: true,
                                                                properties: [
                                                                    new OA\Property(property: 'id', type: 'integer'),
                                                                    new OA\Property(property: 'idCourrier', type: 'integer', nullable: true),
                                                                    new OA\Property(property: 'numeroCourrier', type: 'string', nullable: true),
                                                                    new OA\Property(property: 'instruction', type: 'string'),
                                                                    new OA\Property(property: 'dateInstruction', type: 'string', format: 'date-time'),
                                                                    new OA\Property(property: 'statut', type: 'string'),
                                                                ]
                                                            ),
                                                            new OA\Property(
                                                                property: 'courrierDepart',
                                                                type: 'object',
                                                                nullable: true,
                                                                properties: [
                                                                    new OA\Property(property: 'id', type: 'integer'),
                                                                    new OA\Property(property: 'numeroReference', type: 'string'),
                                                                    new OA\Property(property: 'numeroOrdre', type: 'string', nullable: true),
                                                                    new OA\Property(property: 'typeCourrier', type: 'string'),
                                                                    new OA\Property(property: 'dateSignature', type: 'string', format: 'date-time'),
                                                                ]
                                                            ),
                                                        ]
                                                    )
                                                ),
                                            ]
                                        )
                                    ),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function collection(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetCollectionArchive');

        $idSalle = $request->query->get('id_salle');
        $idCoffre = $request->query->get('id_coffre');
        $isActive = $request->query->get('is_active');
        $isDelete = $request->query->getBoolean('is_delete', false);

        $queryBuilder = $this->archiveRepository->createQueryBuilder('a');

        // Filtre de suppression
        if (!$isDelete) {
            $queryBuilder->andWhere('a.isDelete = :isDelete')
                ->setParameter('isDelete', false);
        }

        // Filtre actif
        if ($isActive !== null) {
            $queryBuilder->andWhere('a.isActive = :isActive')
                ->setParameter('isActive', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
        }

        // Filtre par salle
        if ($idSalle !== null) {
            $queryBuilder->andWhere('a.idSalle = :idSalle')
                ->setParameter('idSalle', (int) $idSalle);
        }

        // Filtre par coffre
        if ($idCoffre !== null) {
            $queryBuilder->andWhere('a.idCoffre = :idCoffre')
                ->setParameter('idCoffre', (int) $idCoffre);
        }

        // Comptage total
        $total = (int) (clone $queryBuilder)
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // RÃ©cupÃ©rer toutes les archives
        $queryBuilder->orderBy('a.idSalle', 'ASC')
            ->addOrderBy('a.idCoffre', 'ASC')
            ->addOrderBy('a.createdAt', 'DESC');

        $archives = $queryBuilder->getQuery()->getResult();

        // Grouper par salle puis par coffre
        $archivesParSalle = [];
        $sallesCache = [];
        $coffresCache = [];
        $totalCoffresUniques = [];

        foreach ($archives as $archive) {
            $idSalle = $archive->getIdSalle();
            $idCoffre = $archive->getIdCoffre();

            // Cache de la salle
            if (!isset($sallesCache[$idSalle])) {
                $salle = $this->salleRepository->find($idSalle);
                $sallesCache[$idSalle] = $salle ? [
                    'id' => $salle->getId(),
                    'nom' => $salle->getNom(),
                    'isActive' => $salle->isActive()
                ] : [
                    'id' => $idSalle,
                    'nom' => 'Salle inconnue',
                    'isActive' => false
                ];
            }

            // Cache du coffre
            if (!isset($coffresCache[$idCoffre])) {
                $coffre = $this->coffreRepository->find($idCoffre);
                $coffresCache[$idCoffre] = $coffre ? [
                    'id' => $coffre->getId(),
                    'nom' => $coffre->getNom(),
                    'nombrePlaceActuelle' => $coffre->getNombrePlaceActuelle(),
                    'tailleMaximale' => $coffre->getTailleMaximale(),
                    'placesDisponibles' => $coffre->getPlacesDisponibles(),
                    'isActive' => $coffre->isActive()
                ] : [
                    'id' => $idCoffre,
                    'nom' => 'Coffre inconnu',
                    'nombrePlaceActuelle' => 0,
                    'tailleMaximale' => 0,
                    'placesDisponibles' => 0,
                    'isActive' => false
                ];
            }

            // Initialiser la structure de la salle
            if (!isset($archivesParSalle[$idSalle])) {
                $archivesParSalle[$idSalle] = [
                    'salle' => $sallesCache[$idSalle],
                    'totalArchives' => 0,
                    'totalCoffres' => 0,
                    'coffres' => []
                ];
            }

            // Initialiser la structure du coffre
            if (!isset($archivesParSalle[$idSalle]['coffres'][$idCoffre])) {
                $archivesParSalle[$idSalle]['coffres'][$idCoffre] = [
                    'coffre' => $coffresCache[$idCoffre],
                    'totalArchives' => 0,
                    'archives' => []
                ];
                
                // Marquer ce coffre comme unique pour cette salle
                $totalCoffresUniques[$idSalle . '_' . $idCoffre] = true;
            }

            // CrÃ©er l'objet archive avec les dÃ©tails
            $archiveData = [
                'id' => $archive->getId(),
                'idSalle' => $archive->getIdSalle(),
                'idCoffre' => $archive->getIdCoffre(),
                'isActive' => $archive->isActive(),
                'createdAt' => $archive->getCreatedAt()?->format('c'),
                'courriers' => [],
                'transmissions' => [],
                'courriersDepart' => []
            ];

            // RÃ©cupÃ©rer les dÃ©tails des courriers
            $idCourriers = $archive->getIdCourriers() ?? [];
            foreach ($idCourriers as $idCourrier) {
                $courrier = $this->courrierRepository->find($idCourrier);
                if ($courrier && !$courrier->isDelete()) {
                    $archiveData['courriers'][] = [
                        'id' => $courrier->getId(),
                        'numero' => $courrier->getNumero(),
                        'reference' => $courrier->getReference(),
                        'objet' => $courrier->getObjet(),
                        'dateArrivee' => $courrier->getDateArrivee()?->format('c'),
                        'dateEnregistrement' => $courrier->getDateEnregistrement()?->format('c'),
                        'priorite' => $courrier->getPriorite(),
                        'statut' => $courrier->getStatut(),
                        'isArchive' => $courrier->isArchive(),
                        'provenance' => $courrier->getIdProvenance() ? [
                            'id' => $courrier->getIdProvenance()->getId(),
                        ] : null,
                        'serviceTraitant' => $courrier->getIdServiceTraitant() ? [
                            'id' => $courrier->getIdServiceTraitant()->getId(),
                        ] : null,
                    ];
                }
            }

            // RÃ©cupÃ©rer les dÃ©tails des transmissions
            $idTransmissions = $archive->getIdTransmissions() ?? [];
            foreach ($idTransmissions as $idTransmission) {
                $transmission = $this->transmissionRepository->find($idTransmission);
                if ($transmission && !$transmission->isDelete()) {
                    $archiveData['transmissions'][] = [
                        'id' => $transmission->getId(),
                        'idCourrier' => $transmission->getIdCourrier()?->getId(),
                        'numeroCourrier' => $transmission->getIdCourrier()?->getNumero(),
                        'instruction' => $transmission->getInstruction(),
                        'dateInstruction' => $transmission->getDateInstruction()?->format('c'),
                        'dateReception' => $transmission->getDateReception()?->format('c'),
                        'delaiTraitement' => $transmission->getDelaiTraitement(),
                        'statut' => $transmission->getStatut(),
                        'typeTransfert' => $transmission->getTypeTransfert(),
                        'isArchive' => $transmission->isArchive(),
                        'serviceDestinataire' => $transmission->getIdServiceDestinataire() ? [
                            'id' => $transmission->getIdServiceDestinataire()->getId(),
                        ] : null,
                        'emetteur' => $transmission->getIdEmetteur() ? [
                            'id' => $transmission->getIdEmetteur()->getId(),
                        ] : null,
                        'courrier' => $transmission->getIdCourrier() ? [
                            'id' => $transmission->getIdCourrier()->getId(),
                            'numero' => $transmission->getIdCourrier()->getNumero(),
                            'objet' => $transmission->getIdCourrier()->getObjet(),
                        ] : null,
                    ];
                }
            }

            // RÃ©cupÃ©rer les dÃ©tails des courriers dÃ©part
            $idCourriersDepart = $archive->getIdCourriersDepart() ?? [];
            foreach ($idCourriersDepart as $idCourrierDepart) {
                $courrierDepart = $this->courrierDepartRepository->find($idCourrierDepart);
                if ($courrierDepart && !$courrierDepart->isDelete()) {
                    $archiveData['courriersDepart'][] = [
                        'id' => $courrierDepart->getId(),
                        'numeroReference' => $courrierDepart->getNumeroReference(),
                        'numeroOrdre' => $courrierDepart->getNumeroActe(),
                        'typeCourrier' => $courrierDepart->getTypeCourrier(),
                        'classeCourrier' => $courrierDepart->getClasseCourrier(),
                        'categorie' => $courrierDepart->getCategorie(),
                        'dateSignature' => $courrierDepart->getDateSignature()?->format('c'),
                        'commentaire' => $courrierDepart->getCommentaire(),
                        'isArchive' => $courrierDepart->isArchive(),
                        'destinataire' => $courrierDepart->getDestinataire() ? [
                            'id' => $courrierDepart->getDestinataire()->getId(),
                        ] : null,
                        'signataire' => $courrierDepart->getIdSignataire() ? [
                            'id' => $courrierDepart->getIdSignataire()->getId(),
                        ] : null,
                        'courrier' => $courrierDepart->getIdCourrier() ? [
                            'id' => $courrierDepart->getIdCourrier()->getId(),
                            'numero' => $courrierDepart->getIdCourrier()->getNumero(),
                            'objet' => $courrierDepart->getIdCourrier()->getObjet(),
                        ] : null,
                    ];
                }
            }

            // Ajouter l'archive au coffre
            $archivesParSalle[$idSalle]['coffres'][$idCoffre]['archives'][] = $archiveData;

            // IncrÃ©menter les compteurs
            $archivesParSalle[$idSalle]['coffres'][$idCoffre]['totalArchives']++;
            $archivesParSalle[$idSalle]['totalArchives']++;
        }

        // Convertir les coffres en array et calculer le total
        foreach ($archivesParSalle as $idSalle => &$salleData) {
            $salleData['coffres'] = array_values($salleData['coffres']);
            $salleData['totalCoffres'] = count($salleData['coffres']);
        }

        return $this->json([
            'total' => $total,
            'totalSalles' => count($archivesParSalle),
            'totalCoffres' => count($totalCoffresUniques),
            'data' => array_values($archivesParSalle),
        ], 200);
    }
}
