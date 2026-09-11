<?php

namespace App\Controller\Core\Reponse;

use App\Repository\Cour\ReponseRepository;
use App\Repository\Core\ServiceRepository;
use App\Repository\Cour\PieceJointeRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Reponse")]
class GetByServiceController extends AbstractController
{
    public function __construct(
        private ReponseRepository $reponseRepository,
        private ServiceRepository $serviceRepository,
        private PieceJointeRepository $pieceJointeRepository,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/reponse/by-service/{serviceId<([1-9][0-9]*)>}', name: 'app_core_reponse_get_by_service', methods: ['GET'])]
    #[OA\Get(
        path: '/core/reponse/by-service/{serviceId}',
        summary: 'Réponses d\'un service',
        tags: ['Reponse'],
        description: "Retourne toutes les réponses destinées à un service donné.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'serviceId', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 3)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 10)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Réponses récupérées avec succès.'),
            new OA\Response(response: 404, description: 'Service non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function getReponsesByService(Request $request, int $serviceId): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetReponsesByService');

        $service = $this->serviceRepository->find($serviceId);

        if (!$service) {
            return $this->json(['code' => 404, 'message' => 'Service non trouvÃ©.'], 404);
        }

        $page = max(1, (int)$request->query->get('page', 1));
        $limit = (int)$request->query->get('limit', 10);

        $queryBuilder = $this->reponseRepository->createQueryBuilder('r')
            ->leftJoin('r.idCourrier', 'c')
            ->leftJoin('r.idRedacteur', 'red')
            ->addSelect('c', 'red')
            ->where('r.idServiceDestinataire = :service')
            ->andWhere('r.isDelete = :isDelete')
            ->setParameter('service', $service)
            ->setParameter('isDelete', false)
            ->orderBy('r.dateReponse', 'DESC');

        $total = (clone $queryBuilder)->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();

        if ($limit > 0) {
            $queryBuilder->setMaxResults($limit)->setFirstResult(($page - 1) * $limit);
        }

        $reponses = $queryBuilder->getQuery()->getResult();

        $data = array_map(function($r) {
            $piecesJointes = $this->pieceJointeRepository->findBy([
                'idParent' => $r->getId(),
                'typeParent' => 'Reponse',
                'isDelete' => false
            ]);

            return [
                'id' => $r->getId(),
                'courrier' => $r->getIdCourrier() ? [
                    'id' => $r->getIdCourrier()?->getId(),
                    'numero' => $r->getIdCourrier()?->getNumero(),
                    'reference' => $r->getIdCourrier()?->getReference(),
                    'objet' => $r->getIdCourrier()?->getObjet(),
                    'dateArrivee' => $r->getIdCourrier()?->getDateArrivee()?->format('Y-m-d'),
                    'typeCourrier' => $r->getIdCourrier()?->getTypeCourrier()?->getNom(),
                    'provenance' => $r->getIdCourrier()?->getIdProvenance()?->getNom(),
                ] : null,
                'typeReponse' => $r->getTypeReponse() ? [
                    'id' => $r->getTypeReponse()->getId(),
                    'nom' => $r->getTypeReponse()->getNom(),
                ] : null,
                'objet' => $r->getObjet(),
                'redacteur' => [
                    'id' => $r->getIdRedacteur()?->getId(),
                    'fullName' => $r->getIdRedacteur()?->getFullName(),
                ],
                'dateReponse' => $r->getDateReponse()?->format('Y-m-d H:i:s'),
                'piecesJointes' => array_map(fn($pj) => [
                    'id' => $pj->getId(),
                    'nom' => $pj->getNom(),
                    'chemin' => $pj->getChemin(),
                    'type' => $pj->getType(),
                ], $piecesJointes),
            ];
        }, $reponses);

        return $this->json([
            'service' => [
                'id' => $service->getId(),
                'nom' => $service->getNom(),
                'sigle' => $service->getSigle(),
            ],
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'reponses' => $data
        ], 200);
    }
}