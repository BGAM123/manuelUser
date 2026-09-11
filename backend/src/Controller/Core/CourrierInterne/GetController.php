<?php

namespace App\Controller\Core\CourrierInterne;

use App\Repository\Cour\CourrierInterneRepository;
use App\Repository\Cour\PieceJointeRepository;
use App\Repository\Cour\TransmissionRepository;
use App\Repository\Cour\TransmissionReponseRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "CourrierInterne")]
class GetController extends AbstractController
{
    public function __construct(
        private CourrierInterneRepository $courrierInterneRepository,
        private PieceJointeRepository $pieceJointeRepository,
        private TransmissionRepository $transmissionRepository,
        private TransmissionReponseRepository $transmissionReponseRepository,
        private AccessCheckerService $accessChecker,
        private EntityManagerInterface $entityManager,
        private UserActionLoggerService $actionLogger,
    ) {}

    #[Route('/core/courrier-interne/{id}', name: 'app_core_courrier_interne_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(
        path: '/core/courrier-interne/{id}',
        summary: 'Recuperer un courrier interne par son ID',
        tags: ['CourrierInterne'],
        description: "Retourne les details complets d'un courrier interne, incluant ses pieces jointes.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Courrier interne recupere avec succes.'),
            new OA\Response(response: 404, description: 'Courrier interne non trouve.'),
            new OA\Response(response: 401, description: 'Acces non autorise.')
        ]
    )]
    public function show(int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetReponse');

        $courrierInterne = $this->courrierInterneRepository->createQueryBuilder('ci')
            ->leftJoin('ci.idServiceDestinataire', 'sd')
            ->leftJoin('ci.idRedacteur', 'rd')
            ->addSelect('sd', 'rd')
            ->where('ci.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$courrierInterne) {
            return $this->json(['code' => 404, 'message' => 'Courrier interne non trouve.'], 404);
        }

        $piecesJointes = $this->pieceJointeRepository->findBy([
            'idParent' => $courrierInterne->getId(),
            'typeParent' => 'CourrierInterne',
            'isDelete' => false
        ]);

        $typesCourrier = [];
        if ($courrierInterne->getTypesCourrierIds()) {
            foreach ($courrierInterne->getTypesCourrierIds() as $typeId) {
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

        $courriers = [];
        $seenCourrierIds = [];
        $transmissionIds = $courrierInterne->getIdTransmission();
        if (is_array($transmissionIds) && !empty($transmissionIds)) {
            $transmissionIds = array_values(array_unique(array_filter($transmissionIds, 'is_numeric')));
            if (!empty($transmissionIds)) {
                $transmissions = $this->transmissionRepository->createQueryBuilder('t')
                    ->leftJoin('t.idCourrier', 'c')
                    ->addSelect('c')
                    ->where('t.id IN (:ids)')
                    ->setParameter('ids', $transmissionIds)
                    ->getQuery()
                    ->getResult();

                foreach ($transmissions as $transmission) {
                    $courrier = $transmission->getIdCourrier();
                    if (!$courrier) {
                        continue;
                    }

                    $courrierId = $courrier->getId();
                    if ($courrierId === null || isset($seenCourrierIds[$courrierId])) {
                        continue;
                    }
                    $seenCourrierIds[$courrierId] = true;

                    $courriers[] = [
                        'id' => $courrierId,
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
            }
        }

        // Récupérer les pièces jointes des transmissions liées à ce courrier interne
        $piecesJointesAutresTransmissions = [];
        try {
            if (is_array($transmissionIds) && !empty($transmissionIds)) {
                foreach ($transmissionIds as $transId) {
                    if (!is_numeric($transId)) {
                        continue;
                    }

                    $otherPjs = $this->pieceJointeRepository->findBy([
                        'idParent' => (int) $transId,
                        'typeParent' => 'Transmission',
                        'isDelete' => false
                    ]);

                    foreach ($otherPjs as $pj) {
                        $piecesJointesAutresTransmissions[] = [
                            'transmissionId' => (int) $transId,
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
            // Récupérer aussi les pièces jointes des TransmissionReponse liées à ce courrier interne
            $conn = $this->transmissionReponseRepository->getEntityManager()->getConnection();
            $rows = $conn->fetchAllAssociative('SELECT id FROM cour_transmission_reponse WHERE JSON_CONTAINS(id_courrier_interne, :cid) AND is_delete = 0', [
                'cid' => json_encode($courrierInterne->getId()),
            ]);
            $trIds = array_map(fn($r) => (int) $r['id'], $rows);
            if (!empty($trIds)) {
                foreach ($trIds as $trId) {
                    $otherPjs = $this->pieceJointeRepository->findBy([
                        'idParent' => $trId,
                        'typeParent' => 'TransmissionReponse',
                        'isDelete' => false
                    ]);
                    foreach ($otherPjs as $pj) {
                        $piecesJointesAutresTransmissions[] = [
                            'transmissionId' => $trId,
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
            $piecesJointesAutresTransmissions = [];
        }

        // Enlever doublons éventuels (même transmissionId + id)
        if (!empty($piecesJointesAutresTransmissions)) {
            $seen = [];
            $deduped = [];
            foreach ($piecesJointesAutresTransmissions as $pj) {
                $key = $pj['transmissionId'] . '_' . $pj['id'];
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $deduped[] = $pj;
            }
            $piecesJointesAutresTransmissions = $deduped;
        }

        $courrierInterneLiees = [];
        if (is_array($courrierInterne->getIdReponses())) {
            foreach ($courrierInterne->getIdReponses() as $reponseId) {
                if (!is_numeric($reponseId)) {
                    continue;
                }
                $lastTransmission = $this->transmissionReponseRepository->findLatestByCourrierInterneId((int) $reponseId);
                $courrierInterneLiees[] = [
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
            'id' => $courrierInterne->getId(),
            'courriers' => $courriers,
            'typesCourrier' => $typesCourrier,
            'idTransmission' => $courrierInterne->getIdTransmission(),
            'idReponses' => $courrierInterne->getIdReponses(),
            'courrierInterneLiees' => $courrierInterneLiees,
            'objet' => $courrierInterne->getObjet(),
            'commentairePublic' => $courrierInterne->getCommentairePublic(),
            'commentaireInterne' => $courrierInterne->getCommentaireInterne(),
            'classeCourrier' => $courrierInterne->getClasseCourrier(),
            'typeTransmission' => $courrierInterne->getTypeTransmission(),
            'priorite' => $courrierInterne->getPriorite(),
            'statut' => $courrierInterne->getStatut(),
            'numero'=> $courrierInterne->getNumero(),
            'reference'=> $courrierInterne->getNumero(),
            'accuseReception' => $courrierInterne->isAccuseReception(),
            'isinstance' => $courrierInterne->isinstance(),
            'is_geled' => $courrierInterne->isGeled(),
            'serviceDestinataire' => $courrierInterne->getIdServiceDestinataire() ? [
                'id' => $courrierInterne->getIdServiceDestinataire()->getId(),
                'nom' => $courrierInterne->getIdServiceDestinataire()->getNom(),
                'sigle' => $courrierInterne->getIdServiceDestinataire()->getSigle(),
            ] : null,
            'redacteur' => $courrierInterne->getIdRedacteur() ? [
                'id' => $courrierInterne->getIdRedacteur()->getId(),
                'username' => $courrierInterne->getIdRedacteur()->getUsername(),
                'fullName' => $courrierInterne->getIdRedacteur()->getFullName(),
                'email' => $courrierInterne->getIdRedacteur()->getEmail(),
            ] : null,
            'dateReponse' => $courrierInterne->getDateReponse()?->format('Y-m-d H:i:s'),
            'nombrePieceJointe' => $courrierInterne->getNombrePieceJointe(),
            'piecesJointes' => array_map(fn($pj) => [
                'id' => $pj->getId(),
                'nom' => $pj->getNom(),
                'intitule' => $pj->getIntitule(),
                'chemin' => $pj->getChemin(),
                'type' => $pj->getType(),
                'createdAt' => $pj->getCreatedAt()?->format('Y-m-d H:i:s'),
            ], $piecesJointes),
            'piecesJointesAutresTransmissions' => $piecesJointesAutresTransmissions,
            'createdAt' => $courrierInterne->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $courrierInterne->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];

        $this->actionLogger->logView(
            'CourrierInterne',
            $courrierInterne->getId(),
            'Consultation d\'un courrier interne',
            [
                'courrier_interne' => $responseData,
            ]
        );

        return $this->json($responseData, 200);
    }
}
