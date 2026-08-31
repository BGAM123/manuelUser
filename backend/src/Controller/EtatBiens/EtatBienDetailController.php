<?php

namespace App\Controller\EtatBiens;

use App\Entity\EtatBien;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/etat-biens')]
#[OA\Tag(name: 'EtatBiens')]
final class EtatBienDetailController extends AbstractController
{
    #[Route('/{id}', name: 'app_etat_bien_detail', methods: ['GET'])]
    #[OA\Get(
        path: '/etat-biens/{id}',
        summary: 'Détail d\'un état de bien',
        description: 'Retourne l\'état de bien avec ses types de biens associés.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'État de bien retourné avec succès.',
                'data' => [
                    'id' => 1,
                    'nom' => 'En panne',
                    'assetTypes' => [
                        ['id' => 1, 'nom' => 'Véhicule'],
                        ['id' => 3, 'nom' => 'Groupe électrogène'],
                    ],
                ],
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Not Found')]
    public function __invoke(
        EtatBien $etatBien,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($etatBien->isDelete()) {
            return $apiResponse->error('État de bien non trouvé.', Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($serializer->serialize($etatBien, 'json', ['groups' => ['etat_bien:detail']]), true);

        return $apiResponse->success($data, Response::HTTP_OK, 'État de bien retourné avec succès.');
    }
}
