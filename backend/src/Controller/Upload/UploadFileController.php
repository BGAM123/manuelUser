<?php

namespace App\Controller\Upload;

use App\Service\ApiResponseFactory;
use App\Service\FileUploadService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// #[Route('/upload')]
// #[OA\Tag(name: 'Upload')]
final class UploadFileController extends AbstractController
{
    #[Route('', name: 'app_upload_file', methods: ['POST'])]
    #[OA\Post(
        path: '/upload',
        summary: 'Uploader un fichier',
        description: 'Reçoit un fichier multipart, le stocke dans /public/uploads et crée automatiquement une PieceJointe (champs : id, nom, chemin).'
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['file'],
                properties: [
                    new OA\Property(property: 'file', type: 'string', format: 'binary', description: 'Fichier à uploader'),
                    new OA\Property(property: 'nom', type: 'string', nullable: true, description: 'Nom affiché optionnel'),
                ]
            )
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Created',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 201,
                'message' => 'Fichier uploadé avec succès.',
                'data' => [
                    'id' => 1,
                    'nom' => 'facture.pdf',
                    'chemin' => '/uploads/facture_a1b2c3d4.pdf',
                ],
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => null])
    )]
    public function __invoke(
        Request $request,
        FileUploadService $fileUploadService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $file = $request->files->get('file');
        if (null === $file) {
            return $apiResponse->error('Le champ file est obligatoire.', Response::HTTP_BAD_REQUEST);
        }

        $nom = $request->request->get('nom');
        $nom = null !== $nom && '' !== $nom ? (string) $nom : null;

        try {
            $pieceJointe = $fileUploadService->upload($file, FileUploadService::KIND_GENERIC, $nom);
        } catch (\InvalidArgumentException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return $apiResponse->error($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $apiResponse->success([
            'id' => $pieceJointe->getId(),
            'nom' => $pieceJointe->getNom(),
            'chemin' => $pieceJointe->getChemin(),
        ], Response::HTTP_CREATED, 'Fichier uploadé avec succès.');
    }
}
