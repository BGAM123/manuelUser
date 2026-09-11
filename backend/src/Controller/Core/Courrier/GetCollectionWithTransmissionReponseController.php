<?php

namespace App\Controller\Core\Courrier;

use App\Repository\Cour\CourrierRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: "CourrierArrive")]
class GetCollectionWithTransmissionReponseController extends AbstractController
{
    public function __construct(
        private CourrierRepository $courrierRepository,
        private EntityManagerInterface $entityManager,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger,
    ) {}

    #[Route('/core/courrier/with-transmission-reponse', name: 'app_core_courrier_get_collection_with_transmission_reponse', methods: ['GET'])]
    #[OA\Get(
        path: '/core/courrier/with-transmission-reponse',
        summary: 'Liste les courriers arrivés ayant au moins une transmission avec une réponse',
        tags: ['CourrierArrive'],
        description: "Retourne les courriers entrants (courrier arrivé) pour lesquels il existe au moins une transmission (cour_transmission) référencée dans une réponse (cour_reponse.id_transmission).",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Page number.', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Items per page.', schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'order_by', in: 'query', required: false, description: 'Sort order by createdAt.', schema: new OA\Schema(type: 'string', enum: ['ASC', 'DESC'], default: 'DESC')),
            new OA\Parameter(name: 'is_delete', in: 'query', required: false, description: 'Filter deleted courriers.', schema: new OA\Schema(type: 'boolean', default: false)),
            new OA\Parameter(name: 'isarchive', in: 'query', required: false, description: 'Filter archived courriers.', schema: new OA\Schema(type: 'boolean', default: false)),
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Recherche textuelle sur tous les champs retournes.', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste récupérée avec succès.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'page', type: 'integer', example: 1),
                        new OA\Property(property: 'limit', type: 'integer', example: 10),
                        new OA\Property(property: 'total', type: 'integer', example: 100),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function list(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetCourrierCollection');

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, (int) $request->query->get('limit', 10));
        $offset = ($page - 1) * $limit;

        $orderBy = strtoupper((string) $request->query->get('order_by', 'DESC'));
        $orderBy = in_array($orderBy, ['ASC', 'DESC'], true) ? $orderBy : 'DESC';

        $isDelete = filter_var($request->query->get('is_delete', false), FILTER_VALIDATE_BOOLEAN);
        $isArchive = filter_var($request->query->get('isarchive', false), FILTER_VALIDATE_BOOLEAN);

        $searchFilter = is_string($request->query->get('search')) && trim((string) $request->query->get('search')) !== ''
            ? trim((string) $request->query->get('search'))
            : null;
        $searchFilterNormalized = $searchFilter !== null
            ? (function_exists('mb_strtolower') ? mb_strtolower($searchFilter, 'UTF-8') : strtolower($searchFilter))
            : null;

        $conn = $this->entityManager->getConnection();

        $baseSql = <<<SQL
FROM cour_courrier c
WHERE c.is_delete = :isDelete
  AND c.is_archive = :isArchive
  AND EXISTS (
      SELECT 1
      FROM cour_transmission tr
      WHERE tr.id_courrier = c.id
        AND tr.is_delete = 0
        AND EXISTS (
            SELECT 1
            FROM cour_reponse r
            WHERE r.is_delete = 0
              AND r.id_transmission IS NOT NULL
              AND JSON_CONTAINS(r.id_transmission, CONCAT('[', tr.id, ']'))
        )
  )
SQL;

        $params = [
            'isDelete' => (int) $isDelete,
            'isArchive' => (int) $isArchive,
        ];
        $types = [
            'isDelete' => ParameterType::INTEGER,
            'isArchive' => ParameterType::INTEGER,
        ];

        $total = $searchFilterNormalized === null
            ? (int) $conn->fetchOne('SELECT COUNT(c.id) ' . $baseSql, $params, $types)
            : 0;

        if ($searchFilterNormalized === null) {
            $idsSql = 'SELECT c.id ' . $baseSql . " ORDER BY c.created_at {$orderBy} LIMIT :limit OFFSET :offset";
            $ids = array_map('intval', $conn->fetchFirstColumn(
                $idsSql,
                array_merge($params, ['limit' => $limit, 'offset' => $offset]),
                array_merge($types, ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER])
            ));
        } else {
            $idsSql = 'SELECT c.id ' . $baseSql . " ORDER BY c.created_at {$orderBy}";
            $ids = array_map('intval', $conn->fetchFirstColumn($idsSql, $params, $types));
        }

        if (empty($ids)) {
            $this->actionLogger->logView(
                'Courrier',
                null,
                'Consultation de la liste des courriers entrants (avec transmission + réponse)',
                [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'is_delete' => $isDelete,
                    'isarchive' => $isArchive,
                    'search' => $searchFilter,
                ]
            );

            return $this->json([
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'data' => [],
            ], 200);
        }

        $courriers = $this->courrierRepository->createQueryBuilder('c')
            ->leftJoin('c.idServiceTraitant', 's')
            ->leftJoin('c.idCreateur', 'u')
            ->leftJoin('c.idProvenance', 'p')
            ->leftJoin('p.categories', 'cat')
            ->leftJoin('c.typeCourrier', 't')
            ->leftJoin('c.transmissions', 'trans', 'WITH', 'trans.isDelete = false')
            ->leftJoin('trans.idServiceDestinataire', 'servTrans')
            ->addSelect('s', 'u', 'p', 'cat', 't', 'trans', 'servTrans')
            ->andWhere('c.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $positionById = array_flip($ids);
        usort($courriers, function ($a, $b) use ($positionById): int {
            $pa = $positionById[$a->getId()] ?? PHP_INT_MAX;
            $pb = $positionById[$b->getId()] ?? PHP_INT_MAX;
            return $pa <=> $pb;
        });

        $data = array_map(function ($c): array {
            $derniereTransmission = null;
            if ($c->getTransmissions()->count() > 0) {
                $transmissions = $c->getTransmissions()->toArray();
                usort($transmissions, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());
                $derniereTransmission = $transmissions[0] ?? null;
            }

            return [
                'id' => $c->getId(),
                'numero' => $c->getNumero(),
                'reference' => $c->getReference(),
                'objet' => $c->getObjet(),
                'priorite' => $c->getPriorite(),
                'statut' => $c->getStatut(),
                'nom' => $c->getNom(),
                'civilite' => $c->getCivilite(),
                'matricule' => $c->getMatricule(),
                'telephone' => $c->getTelephone(),
                'email' => $c->getEmail(),
                'adresse' => $c->getAdresse(),
                'commentaire' => $c->getCommentaire(),
                'commentairePublic' => $c->getCommentairePublic(),
                'commentaireInterne' => $c->getCommentaireInterne(),
                'typeTransfert' => $c->getTypeTransfert(),
                'classeCourrier' => $c->getClasseCourrier(),
                'categorie' => $c->getCategorie(),
                'nombrePieceJointe' => $c->getNombrePieceJointe(),
                'is_geled' => $c->isGeled(),
                'dateArrivee' => $c->getDateArrivee()?->format('Y-m-d H:i:s'),
                'dateEnregistrement' => $c->getDateEnregistrement()?->format('Y-m-d H:i:s'),
                'createdAt' => $c->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updatedAt' => $c->getUpdatedAt()?->format('Y-m-d H:i:s'),
                'idServiceTraitant' => $c->getIdServiceTraitant()?->getNom(),
                'categorieProvenance' => $c->getIdProvenance() && $c->getIdProvenance()->getCategories()->count() > 0
                    ? $c->getIdProvenance()->getCategories()->first()->getNom()
                    : null,
                'typeCourrier' => $c->getTypeCourrier()?->getNom(),
                'idProvenance' => $c->getIdProvenance() ? [
                    'id' => $c->getIdProvenance()->getId(),
                    'nom' => $c->getIdProvenance()->getNom(),
                ] : null,
                'createur' => $c->getIdCreateur() ? [
                    'id' => $c->getIdCreateur()->getId(),
                    'nom' => $c->getIdCreateur()->getFullName(),
                ] : null,
                'isConfidentiel' => $c->isConfidentiel(),
                'isArchive' => $c->isArchive(),
                'hasCourrierDepart' => $c->getCourrierDeparts()->count() > 0,
                'statut_transmission' => $derniereTransmission?->getStatut(),
                'service_traitement' => $derniereTransmission && $derniereTransmission->getIdServiceDestinataire() ? [
                    'id' => $derniereTransmission->getIdServiceDestinataire()->getId(),
                    'nom' => $derniereTransmission->getIdServiceDestinataire()->getNom(),
                ] : null,
            ];
        }, $courriers);

        if ($searchFilterNormalized !== null) {
            $collectScalars = static function ($value, array &$values) use (&$collectScalars): void {
                if ($value === null) {
                    return;
                }
                if (is_array($value)) {
                    foreach ($value as $v) {
                        $collectScalars($v, $values);
                    }
                    return;
                }
                if (is_bool($value)) {
                    $values[] = $value ? 'true' : 'false';
                    return;
                }
                if (is_scalar($value)) {
                    $stringValue = trim((string) $value);
                    if ($stringValue !== '') {
                        $values[] = $stringValue;
                    }
                }
            };

            $data = array_values(array_filter($data, static function (array $row) use ($searchFilterNormalized, $collectScalars): bool {
                $values = [];
                $collectScalars($row, $values);
                $haystack = implode(' ', $values);
                $haystackNormalized = function_exists('mb_strtolower')
                    ? mb_strtolower($haystack, 'UTF-8')
                    : strtolower($haystack);

                return $haystackNormalized !== '' && str_contains($haystackNormalized, $searchFilterNormalized);
            }));

            $total = count($data);
            $data = array_slice($data, $offset, $limit);
        }

        $this->actionLogger->logView(
            'Courrier',
            null,
            'Consultation de la liste des courriers entrants (avec transmission + réponse)',
            [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'is_delete' => $isDelete,
                'isarchive' => $isArchive,
                'search' => $searchFilter,
                'courriers' => array_slice($data, 0, 100),
            ]
        );

        return $this->json([
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'data' => $data,
        ], 200);
    }
}
