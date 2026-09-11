<?php

namespace App\Controller\Core\TransmissionReponse;

use App\Entity\Cour\TransmissionReponse;
use App\Repository\Cour\PieceJointeRepository;
use App\Repository\Cour\CourrierInterneRepository;
use App\Repository\Cour\TransmissionReponseRepository;
use App\Repository\Core\UserRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: 'TransmissionReponse')]
class ParcoursController extends AbstractController
{
    public function __construct(
        private TransmissionReponseRepository $transmissionReponseRepository,
        private CourrierInterneRepository $courrierInterneRepository,
        private PieceJointeRepository $pieceJointeRepository,
        private EntityManagerInterface $entityManager,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger,
        private UserRepository $userRepository,
    ) {
    }

    #[Route('/core/transmission-reponse/{id}/parcours', name: 'app_core_transmission_reponse_parcours', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(
        path: '/core/transmission-reponse/{id}/parcours',
        summary: 'Parcours d un courrier interne transmis',
        description: 'Retourne la timeline complete depuis la creation du courrier interne jusqu aux transmissions reponse liees.',
        tags: ['TransmissionReponse'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID du courrier interne', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'order_by', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['ASC', 'DESC'], default: 'ASC')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'start_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'year', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'priorite', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'statut', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'classe_courrier', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'type_transmission', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'service_destinataire', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'redacteur', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'type_reponse', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'accuse_reception', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'isinstance', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'is_geled', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Parcours transmission reponse recupere'),
            new OA\Response(response: 404, description: 'Transmission reponse non trouvee'),
            new OA\Response(response: 401, description: 'Acces non autorise'),
        ]
    )]
    public function parcours(Request $request, int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetTransmissionReponse');

        $courrierInterne = $this->courrierInterneRepository->find($id);
        if (!$courrierInterne || $courrierInterne->isDelete()) {
            return $this->json(['code' => 404, 'message' => 'Courrier interne non trouve.'], 404);
        }

        $ids = [];
        $sql = 'SELECT id FROM cour_transmission_reponse WHERE is_delete = :isDelete AND JSON_CONTAINS(id_courrier_interne, :courrierInterneId) ORDER BY created_at ASC, id ASC';
        $ids = $this->entityManager->getConnection()->fetchFirstColumn($sql, [
            'isDelete' => 0,
            'courrierInterneId' => json_encode($courrierInterne->getId()),
        ]);

        $entities = $this->transmissionReponseRepository->findBy(['id' => $ids]);
        $entityMap = [];
        foreach ($entities as $entity) {
            if ($entity instanceof TransmissionReponse) {
                $entityMap[$entity->getId()] = $entity;
            }
        }

        $filters = [
            'order_by' => strtoupper((string) $request->query->get('order_by', 'ASC')),
            'page' => max(1, (int) $request->query->get('page', 1)),
            'limit' => (int) $request->query->get('limit', 10),
            'start_date' => $request->query->get('start_date'),
            'end_date' => $request->query->get('end_date'),
            'date' => $request->query->get('date'),
            'year' => $request->query->get('year'),
            'priorite' => $request->query->get('priorite'),
            'statut' => $request->query->get('statut'),
            'classe_courrier' => $request->query->get('classe_courrier'),
            'type_transmission' => $request->query->get('type_transmission'),
            'service_destinataire' => $request->query->get('service_destinataire'),
            'redacteur' => $request->query->get('redacteur'),
            'type_reponse' => $request->query->get('type_reponse'),
            'accuse_reception' => $this->parseNullableBool($request->query->get('accuse_reception')),
            'isinstance' => $this->parseNullableBool($request->query->get('isinstance')),
            'is_geled' => $this->parseNullableBool($request->query->get('is_geled')),
            'search' => $request->query->get('search'),
        ];

        $courrierInternePieces = $this->pieceJointeRepository->findBy([
            'idParent' => $courrierInterne->getId(),
            'typeParent' => 'CourrierInterne',
            'isDelete' => false,
        ], ['id' => 'ASC']);
        $courrierInternePiecesData = array_map(static fn ($pj): array => [
            'id' => $pj->getId(),
            'nom' => $pj->getNom(),
            'intitule' => $pj->getIntitule(),
            'chemin' => $pj->getChemin(),
            'type' => $pj->getType(),
            'createdAt' => $pj->getCreatedAt()?->format('Y-m-d H:i:s'),
        ], $courrierInternePieces);
        $transmissionPiecesById = [];
        if (!empty($ids)) {
            $transmissionPieces = $this->pieceJointeRepository->createQueryBuilder('pj')
                ->where('pj.typeParent = :typeParent')
                ->andWhere('pj.idParent IN (:ids)')
                ->andWhere('pj.isDelete = false')
                ->setParameter('typeParent', 'TransmissionReponse')
                ->setParameter('ids', $ids)
                ->orderBy('pj.id', 'ASC')
                ->getQuery()
                ->getResult();

            foreach ($transmissionPieces as $piece) {
                $transmissionPiecesById[$piece->getIdParent()][] = $piece;
            }
        }

        $serviceUsersCache = [];
        $timeline = [];
        $timeline[] = [
            'date' => $courrierInterne->getCreatedAt()?->format('Y-m-d H:i:s'),
            'type' => 'creation',
            'details' => [
                'id' => $courrierInterne->getId(),
                'objet' => $courrierInterne->getObjet(),
                'commentairePublic' => $courrierInterne->getCommentairePublic(),
                'commentaireInterne' => $courrierInterne->getCommentaireInterne(),
                'statut' => $courrierInterne->getStatut(),
                'priorite' => $courrierInterne->getPriorite(),
                'classeCourrier' => $courrierInterne->getClasseCourrier(),
                'typeTransmission' => $courrierInterne->getTypeTransmission(),
                'accuseReception' => $courrierInterne->isAccuseReception(),
                'isinstance' => $courrierInterne->isinstance(),
                'is_geled' => $courrierInterne->isGeled(),
                'typeReponse' => $courrierInterne->getTypeReponse() ? [
                    'id' => $courrierInterne->getTypeReponse()->getId(),
                    'nom' => $courrierInterne->getTypeReponse()->getNom(),
                ] : null,
                'serviceDestinataire' => $this->buildServiceDestinataireData(
                    $courrierInterne->getIdServiceDestinataire(),
                    $serviceUsersCache
                ),
                'redacteur' => $courrierInterne->getIdRedacteur() ? [
                    'id' => $courrierInterne->getIdRedacteur()->getId(),
                    'fullName' => $courrierInterne->getIdRedacteur()->getFullName(),
                    'email' => $courrierInterne->getIdRedacteur()->getEmail(),
                    'service' => $courrierInterne->getIdRedacteur()->getIdService() ? [
                        'id' => $courrierInterne->getIdRedacteur()->getIdService()->getId(),
                        'nom' => $courrierInterne->getIdRedacteur()->getIdService()->getNom(),
                        'sigle' => $courrierInterne->getIdRedacteur()->getIdService()->getSigle(),
                    ] : null,
                ] : null,
                'piecesJointes' => $courrierInternePiecesData,
            ],
        ];
        foreach ($ids as $transmissionId) {
            $item = $entityMap[(int) $transmissionId] ?? null;
            if (!$item) {
                continue;
            }

            $dateRef = $item->getCreatedAt();
            $pieces = $transmissionPiecesById[$item->getId()] ?? [];
            $piecesData = array_map(static fn ($pj): array => [
                'id' => $pj->getId(),
                'nom' => $pj->getNom(),
                'intitule' => $pj->getIntitule(),
                'chemin' => $pj->getChemin(),
                'type' => $pj->getType(),
                'createdAt' => $pj->getCreatedAt()?->format('Y-m-d H:i:s'),
            ], $pieces);
            $timeline[] = [
                'date' => $dateRef?->format('Y-m-d H:i:s'),
                'type' => 'transmission',
                'details' => [
                    'id' => $item->getId(),
                    'statut' => $item->getStatut(),
                    'priorite' => $item->getPriorite(),
                    'classeCourrier' => $item->getClasseCourrier(),
                    'typeTransmission' => $item->getTypeTransmission(),
                    'accuseReception' => $item->isAccuseReception(),
                    'isinstance' => $item->isinstance(),
                    'is_geled' => $item->isGeled(),
                    'typeReponse' => $item->getTypeReponse() ? [
                        'id' => $item->getTypeReponse()->getId(),
                        'nom' => $item->getTypeReponse()->getNom(),
                    ] : null,
                    'serviceDestinataire' => $this->buildServiceDestinataireData(
                        $item->getIdServiceDestinataire(),
                        $serviceUsersCache
                    ),
                    'redacteur' => $item->getIdRedacteur() ? [
                        'id' => $item->getIdRedacteur()->getId(),
                        'fullName' => $item->getIdRedacteur()->getFullName(),
                        'email' => $item->getIdRedacteur()->getEmail(),
                        'service' => $item->getIdRedacteur()->getIdService() ? [
                            'id' => $item->getIdRedacteur()->getIdService()->getId(),
                            'nom' => $item->getIdRedacteur()->getIdService()->getNom(),
                            'sigle' => $item->getIdRedacteur()->getIdService()->getSigle(),
                        ] : null,
                    ] : null,
                    'piecesJointes' => $piecesData,
                ],
            ];
        }

        $filteredTimeline = $this->applyTimelineFilters($timeline, $filters);
        $filteredTimeline = $this->applyTimelineSearch($filteredTimeline, $filters['search']);
        $filteredTimeline = $this->sortTimeline($filteredTimeline, $filters['order_by']);

        $totalTimeline = count($filteredTimeline);
        $limit = $filters['limit'];
        $page = $filters['page'];
        $timelineData = $limit > 0
            ? array_slice($filteredTimeline, ($page - 1) * $limit, $limit)
            : $filteredTimeline;

        $responseData = [
            'courrierInterne' => [
                'id' => $courrierInterne->getId(),
                'objet' => $courrierInterne->getObjet(),
                'commentairePublic' => $courrierInterne->getCommentairePublic(),
                'commentaireInterne' => $courrierInterne->getCommentaireInterne(),
                'priorite' => $courrierInterne->getPriorite(),
                'statut' => $courrierInterne->getStatut(),
                'typeTransmission' => $courrierInterne->getTypeTransmission(),
                'classeCourrier' => $courrierInterne->getClasseCourrier(),
                'idTransmission' => $courrierInterne->getIdTransmission(),
                'idCourrierInternes' => $courrierInterne->getIdReponses(),
                'serviceDestinataire' => $this->buildServiceDestinataireData(
                    $courrierInterne->getIdServiceDestinataire(),
                    $serviceUsersCache
                ),
                'redacteur' => $courrierInterne->getIdRedacteur() ? [
                    'id' => $courrierInterne->getIdRedacteur()->getId(),
                    'fullName' => $courrierInterne->getIdRedacteur()->getFullName(),
                    'email' => $courrierInterne->getIdRedacteur()->getEmail(),
                    'service' => $courrierInterne->getIdRedacteur()->getIdService() ? [
                        'id' => $courrierInterne->getIdRedacteur()->getIdService()->getId(),
                        'nom' => $courrierInterne->getIdRedacteur()->getIdService()->getNom(),
                        'sigle' => $courrierInterne->getIdRedacteur()->getIdService()->getSigle(),
                    ] : null,
                ] : null,
                'dateReponse' => $courrierInterne->getDateReponse()?->format('Y-m-d H:i:s'),
                'createdAt' => $courrierInterne->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updatedAt' => $courrierInterne->getUpdatedAt()?->format('Y-m-d H:i:s'),
                'piecesJointes' => $courrierInternePiecesData,
            ],
            'filters' => [
                'order_by' => $filters['order_by'],
                'page' => $filters['page'],
                'limit' => $filters['limit'],
                'start_date' => $filters['start_date'],
                'end_date' => $filters['end_date'],
                'date' => $filters['date'],
                'year' => $filters['year'],
                'priorite' => $filters['priorite'],
                'statut' => $filters['statut'],
                'classe_courrier' => $filters['classe_courrier'],
                'type_transmission' => $filters['type_transmission'],
                'service_destinataire' => $filters['service_destinataire'],
                'redacteur' => $filters['redacteur'],
                'type_reponse' => $filters['type_reponse'],
                'accuse_reception' => $filters['accuse_reception'],
                'isinstance' => $filters['isinstance'],
                'is_geled' => $filters['is_geled'],
                'search' => $filters['search'],
            ],
            'timeline' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalTimeline,
                'data' => $timelineData,
            ],
            'statistiques' => [
                'nombreEvenements' => $totalTimeline,
                'nombreTransmissions' => count(array_filter($filteredTimeline, static fn (array $event) => ($event['type'] ?? null) === 'transmission_reponse')),
            ],
        ];

        $this->actionLogger->logView(
            'TransmissionReponse',
            $courrierInterne->getId(),
            'Consultation du parcours d un courrier interne transmis',
            [
                'courrier_interne_parcours' => $responseData,
            ]
        );

        return $this->json($responseData, 200);
    }

    /**
     * @param array<int, array<int, array{id:int, fullName:string}>> $cache
     */
    private function buildServiceDestinataireData(
        ?\App\Entity\Core\Service $service,
        array &$cache
    ): ?array {
        if (!$service) {
            return null;
        }

        $serviceId = $service->getId();
        if (!isset($cache[$serviceId])) {
            $users = $this->userRepository->findBy([
                'idService' => $service,
                'isActive' => true,
                'isDelete' => false,
            ]);

            $cache[$serviceId] = array_map(
                static fn ($user): array => [
                    'id' => $user->getId(),
                    'fullName' => $user->getFullName(),
                ],
                $users
            );
        }

        return [
            'id' => $serviceId,
            'nom' => $service->getNom(),
            'sigle' => $service->getSigle(),
            'utilisateurs' => $cache[$serviceId],
        ];
    }

    private function parseNullableBool(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }
        $raw = strtolower(trim((string) $value));
        if ($raw === '') {
            return null;
        }
        if (in_array($raw, ['true', '1', 'oui', 'yes'], true)) {
            return true;
        }
        if (in_array($raw, ['false', '0', 'non', 'no'], true)) {
            return false;
        }
        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $timeline
     * @return array<int, array<string, mixed>>
     */
    private function applyTimelineFilters(array $timeline, array $filters): array
    {
        $startDate = $this->parseDateFilter($filters['start_date'] ?? null, '00:00:00');
        $endDate = $this->parseDateFilter($filters['end_date'] ?? null, '23:59:59');
        $exactDateStart = $this->parseDateFilter($filters['date'] ?? null, '00:00:00');
        $exactDateEnd = $this->parseDateFilter($filters['date'] ?? null, '23:59:59');
        $year = is_numeric($filters['year'] ?? null) ? (int) $filters['year'] : null;

        return array_values(array_filter($timeline, function (array $event) use (
            $startDate,
            $endDate,
            $exactDateStart,
            $exactDateEnd,
            $year,
            $filters
        ): bool {
            $timestamp = $this->extractTimestamp($event['date'] ?? null);
            if ($timestamp === null) {
                return false;
            }

            if ($startDate !== null && $endDate !== null) {
                if ($timestamp < $startDate->getTimestamp() || $timestamp > $endDate->getTimestamp()) {
                    return false;
                }
            } elseif ($exactDateStart !== null && $exactDateEnd !== null) {
                if ($timestamp < $exactDateStart->getTimestamp() || $timestamp > $exactDateEnd->getTimestamp()) {
                    return false;
                }
            } elseif ($year !== null) {
                $eventYear = (int) date('Y', $timestamp);
                if ($eventYear !== $year) {
                    return false;
                }
            }

            $details = is_array($event['details'] ?? null) ? $event['details'] : [];
            $priorite = $this->normalizeString($filters['priorite'] ?? null);
            if ($priorite !== null && $this->normalizeString($details['priorite'] ?? null) !== $priorite) {
                return false;
            }

            $statut = $this->normalizeString($filters['statut'] ?? null);
            if ($statut !== null && $this->normalizeString($details['statut'] ?? null) !== $statut) {
                return false;
            }

            $classe = $this->normalizeString($filters['classe_courrier'] ?? null);
            if ($classe !== null) {
                $value = $this->normalizeString($details['classeCourrier'] ?? null);
                if ($value === null || !str_contains($value, $classe)) {
                    return false;
                }
            }

            $typeTransmission = $this->normalizeString($filters['type_transmission'] ?? null);
            if ($typeTransmission !== null) {
                $value = $this->normalizeString($details['typeTransmission'] ?? null);
                if ($value === null || !str_contains($value, $typeTransmission)) {
                    return false;
                }
            }

            $serviceDestinataire = is_numeric($filters['service_destinataire'] ?? null) ? (int) $filters['service_destinataire'] : null;
            if ($serviceDestinataire !== null) {
                $serviceId = $details['serviceDestinataire']['id'] ?? null;
                if ((int) $serviceId !== $serviceDestinataire) {
                    return false;
                }
            }

            $redacteur = is_numeric($filters['redacteur'] ?? null) ? (int) $filters['redacteur'] : null;
            if ($redacteur !== null) {
                $redacteurId = $details['redacteur']['id'] ?? null;
                if ((int) $redacteurId !== $redacteur) {
                    return false;
                }
            }

            $typeReponse = is_numeric($filters['type_reponse'] ?? null) ? (int) $filters['type_reponse'] : null;
            if ($typeReponse !== null) {
                $typeId = $details['typeReponse']['id'] ?? null;
                if ((int) $typeId !== $typeReponse) {
                    return false;
                }
            }

            if ($filters['accuse_reception'] !== null && ($details['accuseReception'] ?? null) !== $filters['accuse_reception']) {
                return false;
            }
            if ($filters['isinstance'] !== null && ($details['isinstance'] ?? null) !== $filters['isinstance']) {
                return false;
            }
            if ($filters['is_geled'] !== null && ($details['is_geled'] ?? null) !== $filters['is_geled']) {
                return false;
            }

            return true;
        }));
    }

    private function parseDateFilter(mixed $value, string $time): ?\DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw . ' ' . $time);
        return $date ?: null;
    }

    private function extractTimestamp(mixed $value): ?int
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }
        if (is_string($value) && trim($value) !== '') {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);
            if ($date) {
                return $date->getTimestamp();
            }
        }
        return null;
    }

    private function normalizeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        return function_exists('mb_strtolower') ? mb_strtolower($raw, 'UTF-8') : strtolower($raw);
    }

    /**
     * @param array<int, array<string, mixed>> $timeline
     * @return array<int, array<string, mixed>>
     */
    private function applyTimelineSearch(array $timeline, mixed $search): array
    {
        $needle = $this->normalizeString($search);
        if ($needle === null) {
            return $timeline;
        }

        return array_values(array_filter($timeline, function (array $event) use ($needle): bool {
            return $this->matchesSearchValue($event, $needle);
        }));
    }

    private function matchesSearchValue(mixed $value, string $needle): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_bool($value)) {
            return str_contains($value ? 'true' : 'false', $needle);
        }
        if (is_scalar($value)) {
            return str_contains($this->normalizeString((string) $value) ?? '', $needle);
        }
        if ($value instanceof \DateTimeInterface) {
            return str_contains($this->normalizeString($value->format('Y-m-d H:i:s')) ?? '', $needle);
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->matchesSearchValue($item, $needle)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $timeline
     * @return array<int, array<string, mixed>>
     */
    private function sortTimeline(array $timeline, string $orderBy): array
    {
        $direction = strtoupper($orderBy) === 'DESC' ? 'DESC' : 'ASC';
        usort($timeline, function (array $a, array $b) use ($direction): int {
            $ta = $this->extractTimestamp($a['date'] ?? null) ?? 0;
            $tb = $this->extractTimestamp($b['date'] ?? null) ?? 0;
            if ($ta === $tb) {
                return 0;
            }
            if ($direction === 'DESC') {
                return $tb <=> $ta;
            }
            return $ta <=> $tb;
        });
        return $timeline;
    }
}
