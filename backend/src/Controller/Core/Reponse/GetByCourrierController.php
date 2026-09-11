<?php

namespace App\Controller\Core\Reponse;

use App\Repository\Cour\ReponseRepository;
use App\Repository\Cour\CourrierRepository;
use App\Repository\Cour\PieceJointeRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Reponse")]
class GetByCourrierController extends AbstractController
{
    public function __construct(
        private ReponseRepository $reponseRepository,
        private CourrierRepository $courrierRepository,
        private PieceJointeRepository $pieceJointeRepository,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/reponse/by-courrier/{courrierId<([1-9][0-9]*)>}', name: 'app_core_reponse_get_by_courrier', methods: ['GET'])]
    #[OA\Get(
        path: '/core/reponse/by-courrier/{courrierId}',
        summary: 'Toutes les réponses d\'un courrier',
        tags: ['Reponse'],
        description: "Retourne toutes les réponses données à un courrier spécifique.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'courrierId', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 5))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Réponses récupérées avec succès.'),
            new OA\Response(response: 404, description: 'Courrier non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function getReponsesByCourrier(int $courrierId): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetReponsesByCourrier');

        $courrier = $this->courrierRepository->find($courrierId);

        if (!$courrier) {
            return $this->json(['code' => 404, 'message' => 'Courrier non trouvé.'], 404);
        }

        $reponses = $this->reponseRepository->findBy(
            ['idCourrier' => $courrier, 'isDelete' => false],
            ['dateReponse' => 'DESC']
        );

        $data = array_map(function($r) {
            $piecesJointes = $this->pieceJointeRepository->findBy([
                'idParent' => $r->getId(),
                'typeParent' => 'Reponse',
                'isDelete' => false
            ]);

            return [
                'id' => $r->getId(),
                'typeReponse' => $r->getTypeReponse() ? [
                    'id' => $r->getTypeReponse()->getId(),
                    'nom' => $r->getTypeReponse()->getNom(),
                ] : null,
                'objet' => $r->getObjet(),
                'commentairePublic' => $r->getCommentairePublic(),
                'serviceDestinataire' => [
                    'id' => $r->getIdServiceDestinataire()?->getId(),
                    'nom' => $r->getIdServiceDestinataire()?->getNom(),
                    'sigle' => $r->getIdServiceDestinataire()?->getSigle(),
                ],
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
            'courrier' => $courrier->getIdCourrier() ?  [
                'id' => $courrier->getId(),
                'numero' => $courrier->getNumero(),
                'reference' => $courrier->getReference(),
                'objet' => $courrier->getObjet(),
            ]: null,
            'total' => count($data),
            'reponses' => $data
        ], 200);
    }
}