<?php

namespace App\Controller\Core\User;

use App\Repository\Core\UserRepository;
use App\Service\Core\CrudService;
use App\Service\Core\FileService;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "User")]
class UploadAvatarController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private CrudService $crudService,
        private FileService $fileService,
        private AccessCheckerService $accessChecker,
        private UserActionLoggerService $actionLogger,
    ) {}

    #[Route('/core/user/{id}/avatar', name: 'app_core_user_upload_avatar', methods: ['POST'])]
    #[OA\Post(
        path: '/core/user/{id}/avatar',
        summary: 'Upload l\'avatar d\'un utilisateur',
        description: 'Permet d\'uploader ou de remplacer la photo d\'avatar d\'un utilisateur spécifique. Les formats acceptés sont: JPG, JPEG, PNG, GIF.',
        tags: ['User'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant de l\'utilisateur',
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['avatar'],
                    properties: [
                        new OA\Property(
                            property: 'avatar',
                            type: 'string',
                            format: 'binary',
                            description: 'Fichier image de l\'avatar (JPG, JPEG, PNG, GIF)'
                        ),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Avatar uploadé avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Avatar uploadé avec succès.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'username', type: 'string', example: 'john.doe'),
                                new OA\Property(property: 'avatar', type: 'string', example: '/uploads/core/avatars/avatar_1234567890.jpg'),
                                new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2025-12-17 10:30:00'),
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Fichier avatar manquant ou invalide.'),
            new OA\Response(response: 404, description: 'Utilisateur non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function uploadAvatar(Request $request, int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'UploadUserAvatar');

        $user = $this->userRepository->find($id);

        if (!$user) {
            return $this->json(['code' => 404, 'message' => 'Utilisateur non trouvé.'], 404);
        }

        // Vérifier qu'un fichier avatar a été envoyé
        $avatarFile = $request->files->get('avatar');
        
        if (!$avatarFile) {
            return $this->json([
                'code' => 400,
                'message' => 'Fichier avatar manquant. Veuillez fournir un fichier image.'
            ], 400);
        }

        // Valider le type de fichier (images seulement)
        $allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!in_array($avatarFile->getMimeType(), $allowedMimeTypes)) {
            return $this->json([
                'code' => 400,
                'message' => 'Format de fichier non accepté. Formats autorisés: JPG, JPEG, PNG, GIF.'
            ], 400);
        }

        // Valider la taille du fichier (max 5MB)
        $maxSize = 5 * 1024 * 1024; // 5MB en bytes
        if ($avatarFile->getSize() > $maxSize) {
            return $this->json([
                'code' => 400,
                'message' => 'Le fichier est trop volumineux. Taille maximale autorisée: 5MB.'
            ], 400);
        }

        try {
            // Supprimer l'ancien avatar si il existe
            if ($user->getAvatar()) {
                $oldAvatarPath = $this->getParameter('kernel.project_dir') . '/public' . $user->getAvatar();
                if (file_exists($oldAvatarPath)) {
                    @unlink($oldAvatarPath);
                }
            }

            // Upload du nouveau avatar
            $filePath = $this->fileService->uploadFile(
                $this->getParameter('app_uploads_core_avatars_directory'),
                $avatarFile
            );

            if ($filePath) {
                // Mettre Ã  jour l'utilisateur avec le chemin de l'avatar
                $avatarUrl = $this->getParameter('app_uploads_core_avatars') . $filePath;
                $user = $this->crudService->patchEntity($user, [
                    'avatar' => $avatarUrl
                ]);

                $responseData = [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'avatar' => $user->getAvatar(),
                    'updatedAt' => $user->getUpdatedAt()?->format('Y-m-d H:i:s'),
                ];

                // Logger l'action
                $this->actionLogger->logUpdate(
                    'User',
                    $user->getId(),
                    'Upload d\'avatar',
                    [
                        'user' => $responseData,
                        'old_avatar' => $user->getAvatar(),
                        'new_avatar' => $avatarUrl,
                    ]
                );

                return $this->json([
                    'code' => 200,
                    'message' => 'Avatar uploadé avec succès.',
                    'data' => $responseData
                ], 200);
            } else {
                return $this->json([
                    'code' => 500,
                    'message' => 'Erreur lors de l\'upload du fichier.'
                ], 500);
            }
        } catch (\Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => 'Erreur lors de l\'upload de l\'avatar: ' . $e->getMessage()
            ], 500);
        }
    }
}
