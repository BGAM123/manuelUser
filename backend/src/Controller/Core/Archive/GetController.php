<?php

namespace App\Controller\Core\Archive;

use App\Entity\Core\Archive;
use App\Repository\Core\ArchiveRepository;
use App\Repository\Core\SalleRepository;
use App\Repository\Core\CoffreRepository;
use App\Repository\Cour\CourrierRepository;
use App\Repository\Cour\TransmissionRepository;
use App\Repository\Cour\CourrierDepartRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Archive")]
class GetController extends AbstractController
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

    #[Route('/core/archive/{id}', name: 'app_core_archive_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(
        path: '/core/archive/{id}',
        summary: 'Récupérer une archive par son ID',
        tags: ['Archive'],
        description: "Récupère les détails complets d'une archive spécifique avec toutes les informations des documents associés (courrier, transmission, courrier départ).",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de l\'archive',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Archive récupérée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Archive récupérée avec succès'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'idSalle', type: 'integer', example: 1),
                                new OA\Property(property: 'idCoffre', type: 'integer', example: 1),
                                new OA\Property(property: 'isActive', type: 'boolean', example: true),
                                new OA\Property(property: 'isDelete', type: 'boolean', example: false),
                                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time'),
                                new OA\Property(
                                    property: 'salle',
                                    type: 'object',
                                    nullable: true,
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer'),
                                        new OA\Property(property: 'nom', type: 'string'),
                                        new OA\Property(property: 'isActive', type: 'boolean'),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'coffre',
                                    type: 'object',
                                    nullable: true,
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer'),
                                        new OA\Property(property: 'nom', type: 'string'),
                                        new OA\Property(property: 'nombrePlaceActuelle', type: 'integer'),
                                        new OA\Property(property: 'tailleMaximale', type: 'integer'),
                                        new OA\Property(property: 'placesDisponibles', type: 'integer'),
                                        new OA\Property(property: 'isActive', type: 'boolean'),
                                    ]
                                ),
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
                                        new OA\Property(property: 'idCourrier', type: 'integer'),
                                        new OA\Property(property: 'numeroCourrier', type: 'string'),
                                        new OA\Property(property: 'instruction', type: 'string'),
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
                                        new OA\Property(property: 'numeroOrdre', type: 'string'),
                                        new OA\Property(property: 'typeCourrier', type: 'string'),
                                    ]
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé.'),
            new OA\Response(response: 404, description: 'Archive introuvable.')
        ]
    )]
    public function get(int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetArchive');

        // RÃ©cupÃ©rer l'archive
        $archive = $this->archiveRepository->find($id);

        if (!$archive) {
            return $this->json([
                'error' => 'Archive introuvable'
            ], 404);
        }

        // PrÃ©parer les donnÃ©es de base
        $archiveData = [
            'id' => $archive->getId(),
            'idSalle' => $archive->getIdSalle(),
            'idCoffre' => $archive->getIdCoffre(),
            'isActive' => $archive->isActive(),
            'isDelete' => $archive->isDelete(),
            'createdAt' => $archive->getCreatedAt()?->format('c'),
            'updatedAt' => $archive->getUpdatedAt()?->format('c'),
            'salle' => null,
            'coffre' => null,
            'courriers' => [],
            'transmissions' => [],
            'courriersDepart' => []
        ];

        // RÃ©cupÃ©rer les dÃ©tails de la salle
        if ($archive->getIdSalle() !== null) {
            $salle = $this->salleRepository->find($archive->getIdSalle());
            if ($salle) {
                $archiveData['salle'] = [
                    'id' => $salle->getId(),
                    'nom' => $salle->getNom(),
                    'isActive' => $salle->isActive(),
                ];
            }
        }

        // RÃ©cupÃ©rer les dÃ©tails du coffre
        if ($archive->getIdCoffre() !== null) {
            $coffre = $this->coffreRepository->find($archive->getIdCoffre());
            if ($coffre) {
                $archiveData['coffre'] = [
                    'id' => $coffre->getId(),
                    'nom' => $coffre->getNom(),
                    'nombrePlaceActuelle' => $coffre->getNombrePlaceActuelle(),
                    'tailleMaximale' => $coffre->getTailleMaximale(),
                    'placesDisponibles' => $coffre->getPlacesDisponibles(),
                    'tauxRemplissage' => $coffre->getTauxRemplissage(),
                    'isActive' => $coffre->isActive(),
                ];
            }
        }

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
                    'isConfidentiel' => $courrier->isConfidentiel(),
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
                    'accuseReception' => $transmission->isAccuseReception(),
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

        return $this->json([
            'message' => 'Archive récupérée avec succès',
            'data' => $archiveData
        ], 200);
    }
}
