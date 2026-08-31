<?php

namespace App\Controller\EtatBiens;

use App\Entity\EtatBien;
use App\Repository\EtatBienRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/etat-biens')]
#[OA\Tag(name: 'EtatBiens')]
final class RestoreEtatBienController extends AbstractController
{
    #[Route('/{id}/restore', name: 'app_etat_bien_restore', methods: ['POST'])]
    #[OA\Post(path: '/etat-biens/{id}/restore', summary: 'Restaurer un état de bien')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Success')]
    #[OA\Response(response: 404, description: 'Not Found')]
    #[OA\Response(response: 409, description: 'Conflict')]
    public function __invoke(
        EtatBien $etatBien,
        EtatBienRepository $etatBienRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if (!$etatBien->isDelete()) {
            return $apiResponse->error('Cet état de bien n\'est pas supprimé.', Response::HTTP_CONFLICT);
        }

        $etatBienRepository->restore($etatBien);
        $data = json_decode($serializer->serialize($etatBien, 'json', ['groups' => ['etat_bien:detail']]), true);

        return $apiResponse->success($data, Response::HTTP_OK, 'État de bien restauré avec succès.');
    }
}
