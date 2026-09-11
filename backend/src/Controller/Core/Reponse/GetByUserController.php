<?php

namespace App\Controller\Core\Reponse;

use App\Repository\Cour\ReponseRepository;
use App\Repository\Core\UserRepository;
use App\Repository\Cour\PieceJointeRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Reponse")]
class GetByUserController extends AbstractController
{
    public function __construct(
        private ReponseRepository $reponseRepository,
        private UserRepository $userRepository,
        private PieceJointeRepository $pieceJointeRepository,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger,
    ) {}

    #[Route('/core/reponse/by-user/{userId<([1-9][0-9]*)>}', name: 'app_core_reponse_get_by_user', methods: ['GET'])]
    #[OA\Get(
        path: '/core/reponse/by-user/{userId}',
        summary: 'Réponses d\'un utilisateur',
        tags: ['Reponse'],
        description: "Retourne toutes les réponses rédigées par un utilisateur donné.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 2)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 10)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Réponses récupérées avec succès.'),
            new OA\Response(response: 404, description: 'Utilisateur non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function getReponsesByUser(Request $request, int $userId): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetReponsesByUser');

        $user = $this->userRepository->find($userId);

        if (!$user) {
            return $this->json(['code' => 404, 'message' => 'Utilisateur non trouvé.'], 404);
        }

        $page = max(1, (int)$request->query->get('page', 1));
        $limit = (int)$request->query->get('limit', 10);

        $queryBuilder = $this->reponseRepository->createQueryBuilder('r')
            ->leftJoin('r.idCourrier', 'c')
            ->leftJoin('r.idServiceDestinataire', 'sd')
            ->addSelect('c', 'sd')
            ->where('r.idRedacteur = :user')
            ->andWhere('r.isDelete = :isDelete')
            ->setParameter('user', $user)
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
                'serviceDestinataire' => [
                    'id' => $r->getIdServiceDestinataire()?->getId(),
                    'nom' => $r->getIdServiceDestinataire()?->getNom(),
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

        // Logger la consultation
        $this->actionLogger->logView(
            'Reponse',
            null,
            'Consultation des réponses d\'un utilisateur',
            [
                'userId' => $userId,
                'username' => $user->getUsername(),
                'total' => (int)$total,
                'page' => $page,
                'limit' => $limit,
                'reponses' => array_slice($data, 0, 50), // Limiter Ã  50 pour les logs
            ]
        );

        return $this->json([
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'fullName' => $user->getFullName(),
            ],
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'reponses' => $data
        ], 200);
    }
}