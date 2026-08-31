<?php

namespace App\Controller\Champs;

use App\Entity\Champ;
use App\Repository\ChampRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/champs')]
#[OA\Tag(name: 'Champs')]
final class RestoreChampController extends AbstractController
{
    #[Route('/{id}/restore', name: 'app_champ_restore', methods: ['POST'])]
    #[OA\Post(path: '/champs/{id}/restore', summary: 'Restaurer un champ')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Success')]
    #[OA\Response(response: 409, description: 'Conflict')]
    public function __invoke(
        Champ $champ,
        ChampRepository $champRepository,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if (!$champ->isDelete()) {
            return $apiResponse->error('Ce champ n\'est pas supprimé.', Response::HTTP_CONFLICT);
        }

        $champRepository->restore($champ);
        $data = json_decode($serializer->serialize($champ, 'json', ['groups' => ['champ:detail']]), true);

        return $apiResponse->success($data, Response::HTTP_OK, 'Champ restauré avec succès.');
    }
}
