<?php

namespace App\Controller\Core\BordereauTransmission;

use App\Entity\Core\BordereauTransmission;
use App\Entity\Cour\CourrierDepart;
use App\Service\Core\AccessCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "BordereauTransmission")]
class GetController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker,
        private EntityManagerInterface $entityManager,
    ) {}

    #[Route('/core/bordereau-transmission/{id}', name: 'app_core_bordereau_transmission_get', methods: ['GET'])]
    #[OA\Get(
        path: '/core/bordereau-transmission/{id}',
        summary: 'Récupérer un bordereau de transmission',
        tags: ['BordereauTransmission'],
        description: "Récupère les informations détaillées d'un bordereau de transmission avec toutes les informations des courriers départ associés.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID du bordereau de transmission',
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Bordereau de transmission récupéré avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Bordereau de transmission récupéré avec succès.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'correspondantId', type: 'integer', example: 5, nullable: true, description: 'ID du correspondant destinataire'),
                                new OA\Property(
                                    property: 'courrierIds',
                                    type: 'array',
                                    items: new OA\Items(type: 'integer'),
                                    example: [1, 2, 3, 5, 8],
                                    description: 'Liste des IDs des courriers départ'
                                ),
                                new OA\Property(
                                    property: 'courriers',
                                    type: 'array',
                                    description: 'Informations détaillées des courriers départ',
                                    items: new OA\Items(
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                            new OA\Property(property: 'numeroReference', type: 'string', example: 'CD-2025-001'),
                                            new OA\Property(property: 'typeCourrier', type: 'string', example: 'Lettre'),
                                            new OA\Property(property: 'classeCourrier', type: 'string', example: 'Normal'),
                                            new OA\Property(property: 'categorie', type: 'string', example: 'Administratif'),
                                            new OA\Property(property: 'dateSignature', type: 'string', format: 'date-time', example: '2025-11-20T10:00:00+00:00'),
                                            new OA\Property(property: 'numeroActe', type: 'string', example: 'ACT-001'),
                                            new OA\Property(property: 'document', type: 'string', example: 'document.pdf'),
                                            new OA\Property(property: 'commentaire', type: 'string', example: 'Commentaire du courrier'),
                                            new OA\Property(property: 'email', type: 'string', example: 'contact@example.com'),
                                            new OA\Property(property: 'numeroTelephone', type: 'string', example: '+237123456789'),
                                            new OA\Property(
                                                property: 'provenancesCopie',
                                                type: 'array',
                                                items: new OA\Items(type: 'object'),
                                                example: [['id' => 1, 'nom' => 'Service A']]
                                            ),
                                            new OA\Property(
                                                property: 'destinataire',
                                                type: 'object',
                                                properties: [
                                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                                    new OA\Property(property: 'nom', type: 'string', example: 'Ministère XYZ'),
                                                ]
                                            ),
                                            new OA\Property(
                                                property: 'signataire',
                                                type: 'object',
                                                properties: [
                                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                                    new OA\Property(property: 'lastname', type: 'string', example: 'Dupont'),
                                                    new OA\Property(property: 'firstname', type: 'string', example: 'Jean'),
                                                ]
                                            ),
                                            new OA\Property(
                                                property: 'courrier',
                                                type: 'object',
                                                properties: [
                                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                                    new OA\Property(property: 'objet', type: 'string', example: 'Objet du courrier'),
                                                ]
                                            ),
                                        ]
                                    )
                                ),
                                new OA\Property(property: 'numeroReference', type: 'string', example: 'BT-2025-00001'),
                                new OA\Property(property: 'nombrePieceJointe', type: 'integer', example: 5),
                                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-11-21T10:30:00+00:00'),
                                new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2025-11-24T14:45:00+00:00'),
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé - authentification requise.'),
            new OA\Response(response: 403, description: 'Accès interdit - permissions insuffisantes.'),
            new OA\Response(response: 404, description: 'Bordereau de transmission non trouvé.')
        ]
    )]
    public function get(int $id): Response
    {
        // Vérification des permissions
        $this->accessChecker->checker(
            $this->getUser(), 
            $this->isGranted('ROLE_ADMIN'), 
            'GetBordereauTransmission'
        );

        try {
            // RÃ©cupÃ©rer le bordereau de transmission
            $bordereau = $this->entityManager->getRepository(BordereauTransmission::class)->find($id);

            if (!$bordereau) {
                return $this->json([
                    'code' => 404,
                    'message' => 'Bordereau de transmission non trouvÃ©.'
                ], 404);
            }

            // Récupérer les informations détaillées des courriers départ
            $courrierIds = $bordereau->getCourrierIds();
            $courriers = [];

            if (!empty($courrierIds)) {
                $courriersDepart = $this->entityManager->getRepository(CourrierDepart::class)
                    ->createQueryBuilder('cd')
                    ->where('cd.id IN (:ids)')
                    ->andWhere('cd.isDelete = false')
                    ->setParameter('ids', $courrierIds)
                    ->getQuery()
                    ->getResult();

                foreach ($courriersDepart as $courrier) {
                    $courriers[] = [
                        'id' => $courrier->getId(),
                        'numeroReference' => $courrier->getNumeroReference(),
                        'typeCourrier' => $courrier->getTypeCourrier(),
                        'classeCourrier' => $courrier->getClasseCourrier(),
                        'categorie' => $courrier->getCategorie(),
                        'dateSignature' => $courrier->getDateSignature()?->format('c'),
                        'numeroActe' => $courrier->getNumeroActe(),
                        'document' => $courrier->getDocument(),
                        'commentaire' => $courrier->getCommentaire(),
                        'email' => $courrier->getEmail(),
                        'numeroTelephone' => $courrier->getNumeroTelephone(),
                        'provenancesCopie' => $courrier->getProvenancesCopie(),
                        'destinataire' => $courrier->getDestinataire() ? [
                            'id' => $courrier->getDestinataire()->getId(),
                            'nom' => $courrier->getDestinataire()->getNom(),
                        ] : null,
                        'signataire' => $courrier->getIdSignataire() ? [
                            'id' => $courrier->getIdSignataire()->getId(),
                            'lastname' => $courrier->getIdSignataire()->getLastname(),
                            'firstname' => $courrier->getIdSignataire()->getFirstname(),
                        ] : null,
                        'courrier' => $courrier->getIdCourrier() ? [
                            'id' => $courrier->getIdCourrier()->getId(),
                            'objet' => $courrier->getIdCourrier()->getObjet(),
                        ] : null,
                    ];
                }
            }

            return $this->json([
                'code' => 200,
                'message' => 'Bordereau de transmission récupéré avec succès.',
                'data' => [
                    'id' => $bordereau->getId(),
                    'correspondantId' => $bordereau->getCorrespondantId(),
                    'courrierIds' => $courrierIds,
                    'courriers' => $courriers,
                    'numeroReference' => $bordereau->getNumeroReference(),
                    'nombrePieceJointe' => $bordereau->getNombrePieceJointe(),
                    'createdAt' => $bordereau->getCreatedAt()?->format('c'),
                    'updatedAt' => $bordereau->getUpdatedAt()?->format('c'),
                ]
            ], 200);

        } catch (\Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => 'Une erreur est survenue lors de la récupération du bordereau.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
