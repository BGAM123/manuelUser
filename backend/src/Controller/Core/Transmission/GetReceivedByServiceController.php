<?php

namespace App\Controller\Core\Transmission;

use App\Repository\Cour\TransmissionRepository;
use App\Repository\Core\ServiceRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Transmission")]
class GetReceivedByServiceController extends AbstractController
{
    public function __construct(
        private TransmissionRepository $transmissionRepository,
        private ServiceRepository $serviceRepository,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/transmission/received/{serviceId<([1-9][0-9]*)>}', name: 'app_core_transmission_get_received_by_service', methods: ['GET'])]
    #[OA\Get(
        path: '/core/transmission/received/{serviceId}',
        summary: 'Transmissions reçues par un service',
        tags: ['Transmission'],
        description: "Retourne toutes les transmissions reçues par un service donné.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'serviceId',
                in: 'path',
                required: true,
                description: 'ID du service',
                schema: new OA\Schema(type: 'integer', example: 3)
            ),
            new OA\Parameter(name: 'statut', in: 'query', required: false, description: 'Filtrer par statut', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Page number', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Items per page', schema: new OA\Schema(type: 'integer', default: 10)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des transmissions reçues récupérée avec succès.'),
            new OA\Response(response: 404, description: 'Service non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function getReceivedByService(Request $request, int $serviceId): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetReceivedTransmissions');

        $service = $this->serviceRepository->find($serviceId);

        if (!$service) {
            return $this->json(['code' => 404, 'message' => 'Service non trouvé.'], 404);
        }

        $page = max(1, (int)$request->query->get('page', 1));
        $limit = (int)$request->query->get('limit', 10);
        $statut = $request->query->get('statut');

        $queryBuilder = $this->transmissionRepository->createQueryBuilder('t')
            ->leftJoin('t.idCourrier', 'c')
            ->leftJoin('c.typeCourrier', 'tc')
            ->leftJoin('c.idProvenance', 'prov')
            ->leftJoin('t.idEmetteur', 'e')
            ->addSelect('c', 'tc', 'prov', 'e')
            ->where('t.idServiceDestinataire = :service')
            ->andWhere('t.isDelete = :isDelete')
            ->setParameter('service', $service)
            ->setParameter('isDelete', false)
            ->orderBy('t.dateInstruction', 'DESC');

        if (!empty($statut)) {
            $queryBuilder->andWhere('t.statut = :statut')
                         ->setParameter('statut', $statut);
        }

        $total = (clone $queryBuilder)->select('COUNT(t.id)')->getQuery()->getSingleScalarResult();

        if ($limit > 0) {
            $queryBuilder->setMaxResults($limit)->setFirstResult(($page - 1) * $limit);
        }

        $transmissions = $queryBuilder->getQuery()->getResult();

        $data = array_map(fn($t) => [
            'id' => $t->getId(),
            'courrier' => [
                'id' => $t->getIdCourrier()->getId(),
                'numero' => $t->getIdCourrier()->getNumero(),
                'reference' => $t->getIdCourrier()->getReference(),
                'objet' => $t->getIdCourrier()->getObjet(),
                'dateArrivee' => $t->getIdCourrier()->getDateArrivee()?->format('Y-m-d'),
                'typeCourrier' => $t->getIdCourrier()->getTypeCourrier()?->getNom(),
                'provenance' => $t->getIdCourrier()->getIdProvenance()?->getNom(),
                'priorite' => $t->getIdCourrier()->getPriorite(),
            ],
            'emetteur' => [
                'id' => $t->getIdEmetteur()?->getId(),
                'fullName' => $t->getIdEmetteur()?->getFullName(),
            ],
            'structuresCopie' => $t->getStructuresCopie(),
            'dateInstruction' => $t->getDateInstruction()?->format('Y-m-d H:i:s'),
            'instruction' => $t->getInstruction(),
            'delaiTraitement' => $t->getDelaiTraitement(),
            'typeTransfert' => $t->getTypeTransfert(),
            'accuseReception' => $t->isAccuseReception(),
            'statut' => $t->getStatut(),
        ], $transmissions);

        return $this->json([
            'service' => [
                'id' => $service->getId(),
                'nom' => $service->getNom(),
                'sigle' => $service->getSigle(),
            ],
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'transmissions' => $data
        ], 200);
    }
}