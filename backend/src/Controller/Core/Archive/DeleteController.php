<?php

namespace App\Controller\Core\Archive;

use App\Repository\Core\ArchiveRepository;
use App\Repository\Core\CoffreRepository;
use App\Service\Core\AccessCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Archive")]
class DeleteController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker,
        private EntityManagerInterface $entityManager,
        private ArchiveRepository $archiveRepository,
        private CoffreRepository $coffreRepository,
    ) {}

    #[Route('/core/archive/{id}', name: 'app_core_archive_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(
        path: '/core/archive/{id}',
        summary: 'Supprimer une archive',
        tags: ['Archive'],
        description: "Supprime une archive (soft delete) et décrémente automatiquement le nombre de places utilisées dans le coffre associé.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de l\'archive à  supprimer',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Archive supprimée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Archive supprimée avec succès'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'idCoffre', type: 'integer', example: 1),
                                new OA\Property(
                                    property: 'coffre',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'nombrePlaceActuelle', type: 'integer', example: 14),
                                        new OA\Property(property: 'tailleMaximale', type: 'integer', example: 20),
                                        new OA\Property(property: 'placesDisponibles', type: 'integer', example: 6),
                                    ]
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'L\'archive est déjà supprimée.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.'),
            new OA\Response(response: 404, description: 'Archive introuvable.')
        ]
    )]
    public function delete(int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'DeleteArchive');

        // RÃ©cupÃ©rer l'archive
        $archive = $this->archiveRepository->find($id);

        if (!$archive) {
            return $this->json([
                'error' => 'Archive introuvable'
            ], 404);
        }

        // VÃ©rifier si l'archive n'est pas dÃ©jÃ  supprimÃ©e
        if ($archive->isDelete()) {
            return $this->json([
                'error' => 'Cette archive est déjà supprimée'
            ], 400);
        }

        // RÃ©cupÃ©rer le coffre pour dÃ©crÃ©menter le nombre de places
        $coffre = $this->coffreRepository->find($archive->getIdCoffre());
        
        if (!$coffre) {
            return $this->json([
                'error' => 'Coffre associé introuvable'
            ], 404);
        }

        // Marquer l'archive comme supprimée (soft delete)
        $archive->setIsDelete(true);
        $archive->setIsActive(false);

        // Décrémenter le nombre de places dans le coffre
        $coffre->decrementerPlace();

        // Sauvegarder les modifications
        $this->entityManager->persist($archive);
        $this->entityManager->persist($coffre);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Archive supprimée avec succès',
            'data' => [
                'id' => $archive->getId(),
                'idCoffre' => $coffre->getId(),
                'coffre' => [
                    'nombrePlaceActuelle' => $coffre->getNombrePlaceActuelle(),
                    'tailleMaximale' => $coffre->getTailleMaximale(),
                    'placesDisponibles' => $coffre->getPlacesDisponibles(),
                ]
            ]
        ], 200);
    }
}
