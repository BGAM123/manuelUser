<?php

namespace App\Controller\Champs;

use App\Entity\Champ;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/champs')]
#[OA\Tag(name: 'Champs')]
final class ChampDetailController extends AbstractController
{
    #[Route('/{id}', name: 'app_champ_detail', methods: ['GET'])]
    #[OA\Get(path: '/champs/{id}', summary: 'Détail d\'un champ (avec ses catégories)')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Champ retourné avec succès.',
                'data' => [
                    'id' => 1,
                    'nom' => 'Couleur',
                    'type' => 'select',
                    'option' => 'Rouge,Vert,Bleu',
                    'subtype' => 'multiple',
                    'is_delete' => false,
                    'categories' => [
                        ['id' => 1, 'nom' => 'Catégorie 1'],
                        ['id' => 2, 'nom' => 'Catégorie 2']
                    ]
                ]
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Not Found')]
    public function __invoke(
        Champ $champ,
        SerializerInterface $serializer,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($champ->isDelete()) {
            return $apiResponse->error('Champ non trouvé.', Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($serializer->serialize($champ, 'json', ['groups' => ['champ:detail', 'category:list']]), true);

        return $apiResponse->success($data, Response::HTTP_OK, 'Champ retourné avec succès.');
    }
}
