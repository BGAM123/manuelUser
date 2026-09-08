<?php

namespace App\Controller\Comptable2;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ApiResponseFactory;
use App\Service\FicheDetenteurResponseBuilder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/fiche-detenteur')]
#[OA\Tag(name: 'Comptables')]
final class FicheDetenteurController extends AbstractController
{
    #[Route('/{userId<\d+>}', name: 'app_fiche_detenteur', methods: ['GET'])]
    #[OA\Get(
        path: '/fiche-detenteur/{userId}',
        summary: 'Fiche de détenteur',
        description: 'Retourne les biens et/ou consomptibles détenus par un utilisateur (détenteur), formatés pour la construction d\'une fiche de détenteur : numéro, désignation, description, date d\'acquisition, quantité, prix unitaire, valeur, date d\'affectation, lieu d\'affectation (le service), observation (toujours vide). La réponse est séparée en deux blocs, BIENS et CONSOMMABLES, filtrables via le paramètre "type".'
    )]
    #[OA\Parameter(name: 'userId', description: 'Identifiant du détenteur (utilisateur)', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 8)]
    #[OA\Parameter(
        name: 'type',
        description: 'Filtre le contenu retourné : "biens", "consommables".',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['biens', 'consommables'], default: 'tous')
    )]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Fiche du détenteur récupérée avec succès.',
                'data' => [
                    'detenteur' => [
                        'id' => 8,
                        'nom' => 'NGONO',
                        'prenom' => 'Jean Paul',
                        'matricule' => 'MAT-00123',
                        'service' => ['id' => 16, 'nom' => 'Direction des Systèmes d\'Information'],
                    ],
                    'BIENS' => [
                        [
                            'numero' => 1,
                            'designation' => 'Ordinateur Portable HP ProBook 450 G10',
                            'description' => 'Ordinateur portable affecté à la Direction des Systèmes d\'Information.',
                            'dateAcquisition' => '2026-07-30',
                            'quantite' => 1,
                            'prixUnitaire' => 850000,
                            'valeur' => 850000,
                            'dateAffectation' => '2026-08-01',
                            'lieuAffectation' => 'Direction des Systèmes d\'Information',
                            'observation' => '',
                        ],
                    ],
                    'CONSOMMABLES' => [
                        [
                            'numero' => 1,
                            'designation' => 'Ramette de papier A4',
                            'description' => 'Papier A4 80g',
                            'dateAcquisition' => '2026-06-10',
                            'quantite' => 25,
                            'prixUnitaire' => 3500,
                            'valeur' => 87500,
                            'dateAffectation' => '2026-06-12',
                            'lieuAffectation' => 'Direction des Systèmes d\'Information',
                            'observation' => '',
                        ],
                    ],
                ],
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'Le paramètre "type" doit être "biens", "consommables" ou "tous".', 'data' => null])
    )]
    #[OA\Response(
        response: 404,
        description: 'Not Found',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Détenteur introuvable.', 'data' => null])
    )]
    #[OA\Response(
        response: 401,
        description: 'Non authentifié',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null])
    )]
    public function __invoke(
        int $userId,
        Request $request,
        UserRepository $userRepository,
        FicheDetenteurResponseBuilder $responseBuilder,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $detenteur = $userRepository->find($userId);
        if (!$detenteur instanceof User || $detenteur->isDelete()) {
            return $apiResponse->error('Détenteur introuvable.', Response::HTTP_NOT_FOUND);
        }

        $type = strtolower((string) $request->query->get('type', 'tous'));
        if (!in_array($type, ['biens', 'consommables', 'tous'], true)) {
            return $apiResponse->error(
                'Le paramètre "type" doit être "biens", "consommables" ou "tous".',
                Response::HTTP_BAD_REQUEST
            );
        }

        $service = $detenteur->getService();

        $data = [
            'detenteur' => [
                'id' => $detenteur->getId(),
                'nom' => $detenteur->getLastName(),
                'prenom' => $detenteur->getFirstName(),
                'matricule' => $detenteur->getMatricule(),
                'service' => $service ? ['id' => $service->getId(), 'nom' => $service->getNom()] : null,
            ],
        ];

        if ('biens' === $type || 'tous' === $type) {
            $data['BIENS'] = $responseBuilder->buildBiens($detenteur);
        }

        if ('consommables' === $type || 'tous' === $type) {
            $data['CONSOMMABLES'] = $responseBuilder->buildConsommables($detenteur);
        }

        return $apiResponse->success($data, Response::HTTP_OK, 'Fiche du détenteur récupérée avec succès.');
    }
}
