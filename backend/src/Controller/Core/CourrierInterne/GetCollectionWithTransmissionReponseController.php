<?php

namespace App\Controller\Core\CourrierInterne;

use App\Repository\Cour\CourrierInterneRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'CourrierInterne')]
class GetCollectionWithTransmissionReponseController extends AbstractController
{
    public function __construct(
        private CourrierInterneRepository $courrierInterneRepository,
        private EntityManagerInterface $entityManager,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger,
    ) {}

    #[Route('/core/courrier-interne/with-transmission-reponse', name: 'app_core_courrier_interne_get_collection_with_transmission_reponse', methods: ['GET'])]
    #[OA\Get(
        path: '/core/courrier-interne/with-transmission-reponse',
        summary: 'Lister les courriers internes ayant au moins une transmissionReponse avec une reponse',
        description: "Retourne les courriers internes (cour_courrier_interne) pour lesquels il existe au moins une transmission de reponse (cour_transmission_reponse) référencée sur ce courrier interne et contenant au moins une reponse (id_reponses non vide).",
        tags: ['CourrierInterne'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Page number.', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, description: "Items per page. Use 0 to return all.", schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'order_by', in: 'query', required: false, description: 'Sort order by createdAt.', schema: new OA\Schema(type: 'string', enum: ['ASC', 'DESC'], default: 'DESC')),
            new OA\Parameter(name: 'is_delete', in: 'query', required: false, description: 'Include deleted courriers internes.', schema: new OA\Schema(type: 'boolean', default: false)),
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
            new OA\Response(response: 401, description: 'Accès non autorisé.'),
        ]
    )]
    public function __invoke(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetCourrierInterneCollection');

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = (int) $request->query->get('limit', 10);
        $limit = $limit < 0 ? 10 : $limit;
        $offset = ($page - 1) * ($limit > 0 ? $limit : 0);

        $orderBy = strtoupper((string) $request->query->get('order_by', 'DESC'));
        $orderBy = in_array($orderBy, ['ASC', 'DESC'], true) ? $orderBy : 'DESC';

        $isDelete = filter_var($request->query->get('is_delete', false), FILTER_VALIDATE_BOOLEAN);

        $searchFilter = is_string($request->query->get('search')) && trim((string) $request->query->get('search')) !== ''
            ? trim((string) $request->query->get('search'))
            : null;
        $searchFilterNormalized = $searchFilter !== null
            ? (function_exists('mb_strtolower') ? mb_strtolower($searchFilter, 'UTF-8') : strtolower($searchFilter))
            : null;

        $conn = $this->entityManager->getConnection();

        $baseSql = <<<SQL
FROM cour_courrier_interne ci
WHERE ci.is_delete = :isDelete
  AND EXISTS (
      SELECT 1
      FROM cour_transmission_reponse tr
      WHERE tr.is_delete = 0
        AND tr.id_courrier_interne IS NOT NULL
        AND JSON_CONTAINS(tr.id_courrier_interne, CONCAT('', ci.id))
        AND tr.id_reponses IS NOT NULL
        AND tr.id_reponses <> '[]'
  )
SQL;

        $params = [
            'isDelete' => (int) $isDelete,
        ];
        $types = [
            'isDelete' => ParameterType::INTEGER,
        ];

        $total = $searchFilterNormalized === null
            ? (int) $conn->fetchOne('SELECT COUNT(ci.id) ' . $baseSql, $params, $types)
            : 0;

        $idsSql = 'SELECT ci.id ' . $baseSql . " ORDER BY ci.created_at {$orderBy}";
        if ($searchFilterNormalized === null && $limit > 0) {
            $idsSql .= ' LIMIT :limit OFFSET :offset';
            $params['limit'] = $limit;
            $params['offset'] = $offset;
            $types['limit'] = ParameterType::INTEGER;
            $types['offset'] = ParameterType::INTEGER;
        }

        $ids = array_map('intval', $conn->fetchFirstColumn($idsSql, $params, $types));

        if (empty($ids)) {
            $this->actionLogger->logView(
                'CourrierInterne',
                null,
                'Consultation des courriers internes (avec transmissionReponse + reponse)',
                [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'order_by' => $orderBy,
                    'is_delete' => $isDelete,
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

        $courriersInternes = $this->courrierInterneRepository->createQueryBuilder('ci')
            ->leftJoin('ci.idServiceDestinataire', 'sd')
            ->leftJoin('ci.idRedacteur', 'rd')
            ->leftJoin('ci.typeReponse', 'tr')
            ->addSelect('sd', 'rd', 'tr')
            ->andWhere('ci.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $positionById = array_flip($ids);
        usort($courriersInternes, function ($a, $b) use ($positionById): int {
            $pa = $positionById[$a->getId()] ?? PHP_INT_MAX;
            $pb = $positionById[$b->getId()] ?? PHP_INT_MAX;
            return $pa <=> $pb;
        });

        $data = array_map(function ($ci): array {
            return [
                'id' => $ci->getId(),
                'numero' => $ci->getNumero(),
                'objet' => $ci->getObjet(),
                'commentairePublic' => $ci->getCommentairePublic(),
                'commentaireInterne' => $ci->getCommentaireInterne(),
                'priorite' => $ci->getPriorite(),
                'statut' => $ci->getStatut(),
                'dateReponse' => $ci->getDateReponse()?->format('Y-m-d H:i:s'),
                'classeCourrier' => $ci->getClasseCourrier(),
                'typeTransmission' => $ci->getTypeTransmission(),
                'createdAt' => $ci->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updatedAt' => $ci->getUpdatedAt()?->format('Y-m-d H:i:s'),
                'nombrePieceJointe' => $ci->getNombrePieceJointe(),
                'typeReponse' => $ci->getTypeReponse() ? [
                    'id' => $ci->getTypeReponse()->getId(),
                    'nom' => $ci->getTypeReponse()->getNom(),
                ] : null,
                'serviceDestinataire' => $ci->getIdServiceDestinataire() ? [
                    'id' => $ci->getIdServiceDestinataire()->getId(),
                    'nom' => $ci->getIdServiceDestinataire()->getNom(),
                    'sigle' => $ci->getIdServiceDestinataire()->getSigle(),
                ] : null,
                'redacteur' => $ci->getIdRedacteur() ? [
                    'id' => $ci->getIdRedacteur()->getId(),
                    'fullName' => $ci->getIdRedacteur()->getFullName(),
                ] : null,
            ];
        }, $courriersInternes);

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
            if ($limit > 0) {
                $data = array_slice($data, $offset, $limit);
            }
        }

        $this->actionLogger->logView(
            'CourrierInterne',
            null,
            'Consultation des courriers internes (avec transmissionReponse + reponse)',
            [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'order_by' => $orderBy,
                'is_delete' => $isDelete,
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
