<?php

namespace App\Controller\Core\BordereauTransmission;

use App\Entity\Core\BordereauTransmission;
use App\Entity\Cour\CourrierDepart;
use App\Service\Core\AccessCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "BordereauTransmission")]
class GetCollectionController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker,
        private EntityManagerInterface $entityManager,
    ) {}

    #[Route('/core/bordereau-transmission', name: 'app_core_bordereau_transmission_get_collection', methods: ['GET'])]
    #[OA\Get(
        path: '/core/bordereau-transmission',
        summary: 'Lister tous les bordereaux de transmission',
        tags: ['BordereauTransmission'],
        description: "Récupère la liste de tous les bordereaux de transmission avec toutes leurs informations.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'page',
                in: 'query',
                required: false,
                description: 'Numéro de la page (pagination)',
                schema: new OA\Schema(type: 'integer', example: 1, default: 1)
            ),
            new OA\Parameter(
                name: 'limit',
                in: 'query',
                required: false,
                description: 'Nombre d\'éléments par page. Utiliser 0 pour retourner tous les résultats sans pagination.',
                schema: new OA\Schema(type: 'integer', example: 20, default: 20)
            ),
            new OA\Parameter(
                name: 'numeroReference',
                in: 'query',
                required: false,
                description: 'Filtrer par numéro de référence',
                schema: new OA\Schema(type: 'string', example: 'BT-2025-00001')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des bordereaux de transmission récupérée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Liste des bordereaux de transmission récupérée avec succès.'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
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
                                                new OA\Property(property: 'commentaire', type: 'string', example: 'Commentaire'),
                                                new OA\Property(
                                                    property: 'destinataire',
                                                    type: 'object',
                                                    properties: [
                                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                                        new OA\Property(property: 'nom', type: 'string', example: 'MinistÃ¨re XYZ'),
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
                                            ]
                                        )
                                    ),
                                    new OA\Property(property: 'numeroReference', type: 'string', example: 'BT-2025-00001'),
                                    new OA\Property(property: 'nombrePieceJointe', type: 'integer', example: 5),
                                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-11-21T10:30:00+00:00'),
                                    new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2025-11-24T14:45:00+00:00'),
                                ]
                            )
                        ),
                        new OA\Property(
                            property: 'pagination',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'total', type: 'integer', example: 50, description: 'Nombre total de bordereaux'),
                                new OA\Property(property: 'page', type: 'integer', example: 1, description: 'Page actuelle'),
                                new OA\Property(property: 'limit', type: 'integer', example: 20, description: 'Nombre d\'Ã©lÃ©ments par page'),
                                new OA\Property(property: 'totalPages', type: 'integer', example: 3, description: 'Nombre total de pages'),
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé - authentification requise.'),
            new OA\Response(response: 403, description: 'Accès interdit - permissions insuffisantes.')
        ]
    )]
    public function list(Request $request): Response
    {
        // VÃ©rification des permissions
        $this->accessChecker->checker(
            $this->getUser(), 
            $this->isGranted('ROLE_ADMIN'), 
            'GetCollectionBordereauTransmission'
        );

        try {
            // RÃ©cupÃ©ration des paramÃ¨tres de pagination
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = (int) $request->query->get('limit', 20);
            
            // Si limit = 0, on retourne tous les rÃ©sultats sans pagination
            $allResults = ($limit === 0);
            
            if (!$allResults) {
                $limit = max(1, min(100, $limit));
            }
            
            $offset = $allResults ? 0 : ($page - 1) * $limit;

            // RÃ©cupÃ©ration des filtres
            $numeroReference = $request->query->get('numeroReference');

            // Construction de la requÃªte
            $qb = $this->entityManager->getRepository(BordereauTransmission::class)
                ->createQueryBuilder('bt')
                ->orderBy('bt.createdAt', 'DESC');

            // Appliquer les filtres si prÃ©sents
            if ($numeroReference) {
                $qb->andWhere('bt.numeroReference LIKE :numeroReference')
                   ->setParameter('numeroReference', '%' . $numeroReference . '%');
            }

            // Compter le nombre total d'Ã©lÃ©ments
            $totalQuery = clone $qb;
            $total = (int) $totalQuery->select('COUNT(bt.id)')->getQuery()->getSingleScalarResult();

            // Appliquer la pagination (sauf si limit = 0)
            if (!$allResults) {
                $bordereaux = $qb->setFirstResult($offset)
                                 ->setMaxResults($limit)
                                 ->getQuery()
                                 ->getResult();
            } else {
                // RÃ©cupÃ©rer tous les rÃ©sultats sans pagination
                $bordereaux = $qb->getQuery()->getResult();
            }

            // Formater les donnÃ©es avec les informations des courriers
            $data = array_map(function (BordereauTransmission $bordereau) {
                $courrierIds = $bordereau->getCourrierIds();
                $courriers = [];

                // RÃ©cupÃ©rer les informations dÃ©taillÃ©es des courriers dÃ©part
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

                return [
                    'id' => $bordereau->getId(),
                    'correspondantId' => $bordereau->getCorrespondantId(),
                    'courrierIds' => $courrierIds,
                    'courriers' => $courriers,
                    'numeroReference' => $bordereau->getNumeroReference(),
                    'nombrePieceJointe' => $bordereau->getNombrePieceJointe(),
                    'createdAt' => $bordereau->getCreatedAt()?->format('c'),
                    'updatedAt' => $bordereau->getUpdatedAt()?->format('c'),
                ];
            }, $bordereaux);

            // Calculer le nombre total de pages
            $totalPages = $allResults ? 1 : (int) ceil($total / $limit);

            return $this->json([
                'code' => 200,
                'message' => 'Liste des bordereaux de transmission récupérée avec succès.',
                'data' => $data,
                'pagination' => [
                    'total' => $total,
                    'page' => $allResults ? 1 : $page,
                    'limit' => $allResults ? $total : $limit,
                    'totalPages' => $totalPages,
                    'allResults' => $allResults,
                ]
            ], 200);

        } catch (\Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => 'Une erreur est survenue lors de la récupération des bordereaux.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
