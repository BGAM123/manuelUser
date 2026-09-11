<?php

namespace App\Controller\Core\Transmission;

use App\Repository\Cour\TransmissionRepository;
use App\Repository\Cour\CourrierRepository;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Transmission")]
class GetByCourrierController extends AbstractController
{
    public function __construct(
        private TransmissionRepository $transmissionRepository,
        private CourrierRepository $courrierRepository,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/transmission/by-courrier/{courrierId<([1-9][0-9]*)>}', name: 'app_core_transmission_get_by_courrier', methods: ['GET'])]
    #[OA\Get(
        path: '/core/transmission/by-courrier/{courrierId}',
        summary: 'Historique complet des transmissions d\'un courrier',
        tags: ['Transmission'],
        description: "Retourne toutes les transmissions d'un courrier donnée (circuit complet).",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'courrierId',
                in: 'path',
                required: true,
                description: 'ID du courrier',
                schema: new OA\Schema(type: 'integer', example: 5)
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Historique des transmissions récupéré avec succès.'),
            new OA\Response(response: 404, description: 'Courrier non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function getTransmissionsByCourrier(int $courrierId): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'GetTransmissionsByCourrier');

        $courrier = $this->courrierRepository->find($courrierId);

        if (!$courrier) {
            return $this->json(['code' => 404, 'message' => 'Courrier non trouvé.'], 404);
        }

        $transmissions = $this->transmissionRepository->findBy(
            ['idCourrier' => $courrier, 'isDelete' => false],
            ['dateInstruction' => 'ASC']
        );

        $data = array_map(fn($t) => [
            'id' => $t->getId(),
            'serviceDestinataire' => [
                'id' => $t->getIdServiceDestinataire()?->getId(),
                'nom' => $t->getIdServiceDestinataire()?->getNom(),
                'sigle' => $t->getIdServiceDestinataire()?->getSigle(),
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
            'courrier' => [
                'id' => $courrier->getId(),
                'numero' => $courrier->getNumero(),
                'reference' => $courrier->getReference(),
                'objet' => $courrier->getObjet(),
                'dateArrivee' => $courrier->getDateArrivee()?->format('Y-m-d'),
                'typeCourrier' => $courrier->getTypeCourrier()?->getNom(),
            ],
            'total' => count($data),
            'transmissions' => $data
        ], 200);
    }
}