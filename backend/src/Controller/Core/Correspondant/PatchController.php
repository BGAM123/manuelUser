<?php

namespace App\Controller\Core\Correspondant;

use App\Entity\Core\Correspondant;
use App\Repository\Core\CategorieCorrespondantRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Correspondant")]
class PatchController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker,
        private CategorieCorrespondantRepository $categorieCorrespondantRepository,
        private EntityManagerInterface $entityManager,
        private UserActionLoggerService $actionLogger,
    ) {}

    #[Route('/core/correspondant/{id<([1-9][0-9]*)>}', name: 'app_core_correspondant_patch', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/correspondant/{id}',
        summary: 'Mettre à  jour un correspondant',
        description: 'Modifie les informations d\'un correspondant existant.',
        tags: ['Correspondant'],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'nom', type: 'string', example: 'Jean Dupont'),
                        new OA\Property(property: 'adresse', type: 'string', example: '15 Avenue de la République'),
                        new OA\Property(property: 'telephone', type: 'string', example: '0699887766'),
                        new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@newmail.com'),
                        new OA\Property(property: 'type', type: 'string', example: 'Particulier'),
                        new OA\Property(property: 'civilite', type: 'string', example: 'M.'),
                        new OA\Property(property: 'matricule', type: 'string', example: 'EMP-00231'),
                        new OA\Property(
                            property: 'categorieIds',
                            type: 'array',
                            items: new OA\Items(type: 'integer'),
                            example: [1, 3, 5],
                            description: 'Tableau des IDs des catégories à associer (remplace les anciennes)'
                        )
                    ]
                )
            )
        ),
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant du correspondant à modifier',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Correspondant mis à jour avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Correspondant mis à jour avec succès.'),
                        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2025-03-01T10:20:00+00:00')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Correspondant non trouvé.'),
            new OA\Response(response: 400, description: 'Requête invalide.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function update(Request $request, ?Correspondant $entity = null): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'PatchCorrespondant');

        if (!$entity) {
            return $this->json(['code' => 404, 'message' => 'Correspondant non trouvé.'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        try {
            // âœ… Capturer l'Ã©tat AVANT modification pour le log
            $oldValues = [
                'nom' => $entity->getNom(),
                'adresse' => $entity->getAdresse(),
                'telephone' => $entity->getTelephone(),
                'email' => $entity->getEmail(),
                'type' => $entity->getType(),
                'civilite' => $entity->getCivilite(),
                'matricule' => $entity->getMatricule(),
                'categories' => array_map(fn($cat) => [
                    'id' => $cat->getId(),
                    'nom' => $cat->getNom()
                ], $entity->getCategories()->toArray())
            ];

            // Mise Ã  jour manuelle des champs
            if (array_key_exists('nom', $data)) {
                $entity->setNom($data['nom']);
            }

            if (array_key_exists('adresse', $data)) {
                $entity->setAdresse($data['adresse']);
            }

            if (array_key_exists('telephone', $data)) {
                $entity->setTelephone($data['telephone']);
            }

            if (array_key_exists('email', $data)) {
                $entity->setEmail($data['email']);
            }

            if (array_key_exists('type', $data)) {
                $entity->setType($data['type']);
            }

            if (array_key_exists('civilite', $data)) {
                $entity->setCivilite($data['civilite']);
            }

            if (array_key_exists('matricule', $data)) {
                $entity->setMatricule($data['matricule']);
            }

            // Gestion des catÃ©gories
            if (array_key_exists('categorieIds', $data)) {
                // Supprimer toutes les catÃ©gories existantes
                foreach ($entity->getCategories() as $categorie) {
                    $entity->removeCategorie($categorie);
                }
                
                // Ajouter les nouvelles catÃ©gories
                if (!empty($data['categorieIds']) && is_array($data['categorieIds'])) {
                    foreach ($data['categorieIds'] as $categorieId) {
                        $categorie = $this->categorieCorrespondantRepository->find($categorieId);
                        if (!$categorie) {
                            return $this->json([
                                'code' => 404, 
                                'message' => "Catégorie avec l'ID {$categorieId} non trouvée."
                            ], 404);
                        }
                        $entity->addCategorie($categorie);
                    }
                }
            }

            // Persister les changements
            $this->entityManager->flush();

            // âœ… Capturer l'Ã©tat APRÃˆS modification
            $newValues = [
                'nom' => $entity->getNom(),
                'adresse' => $entity->getAdresse(),
                'telephone' => $entity->getTelephone(),
                'email' => $entity->getEmail(),
                'type' => $entity->getType(),
                'civilite' => $entity->getCivilite(),
                'matricule' => $entity->getMatricule(),
                'categories' => array_map(fn($cat) => [
                    'id' => $cat->getId(),
                    'nom' => $cat->getNom()
                ], $entity->getCategories()->toArray())
            ];

            // âœ… LOG ASYNCHRONE - Mise Ã  jour d'un correspondant
            $this->actionLogger->logUpdate(
                'Correspondant',
                $entity->getId(),
                'Mise à jour des informations du correspondant',
                [
                    'changes' => $data,
                    'old_values' => $oldValues,
                    'new_values' => $newValues
                ]
            );

            return $this->json([
                'code' => 200,
                'message' => 'Correspondant mis à jour avec succès.',
                'updatedAt' => $entity->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ], 200);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}
