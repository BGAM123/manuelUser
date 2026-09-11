<?php

namespace App\Controller\Core\Archive;

use App\Repository\Core\ArchiveRepository;
use App\Repository\Core\SalleRepository;
use App\Repository\Core\CoffreRepository;
use App\Repository\Cour\CourrierRepository;
use App\Repository\Cour\TransmissionRepository;
use App\Repository\Cour\CourrierDepartRepository;
use App\Service\Core\AccessCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Archive")]
class PatchController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker,
        private EntityManagerInterface $entityManager,
        private ArchiveRepository $archiveRepository,
        private SalleRepository $salleRepository,
        private CoffreRepository $coffreRepository,
        private CourrierRepository $courrierRepository,
        private TransmissionRepository $transmissionRepository,
        private CourrierDepartRepository $courrierDepartRepository,
    ) {}

    #[Route('/core/archive/{id}', name: 'app_core_archive_patch', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[OA\Patch(
        path: '/core/archive/{id}',
        summary: 'Mettre à  jour une archive',
        tags: ['Archive'],
        description: "Met à jour partiellement une archive. Permet de modifier la salle, le coffre ou le statut actif. Si le coffre est changé, la capacité des coffres est automatiquement mise à jour.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de l\'archive à modifier',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'idSalle',
                        type: 'integer',
                        description: 'Nouvel ID de la salle',
                        example: 2,
                        nullable: true
                    ),
                    new OA\Property(
                        property: 'idCoffre',
                        type: 'integer',
                        description: 'Nouvel ID du coffre (doit appartenir à la salle)',
                        example: 3,
                        nullable: true
                    ),
                    new OA\Property(
                        property: 'idCourrier',
                        type: 'integer',
                        description: 'ID du courrier archivé',
                        example: 123,
                        nullable: true
                    ),
                    new OA\Property(
                        property: 'idTransmission',
                        type: 'integer',
                        description: 'ID de la transmission archivée',
                        example: 456,
                        nullable: true
                    ),
                    new OA\Property(
                        property: 'idCourrierDepart',
                        type: 'integer',
                        description: 'ID du courrier départ archivé',
                        example: 789,
                        nullable: true
                    ),
                    new OA\Property(
                        property: 'isActive',
                        type: 'boolean',
                        description: 'Statut actif de l\'archive',
                        example: true,
                        nullable: true
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Archive mise à jour avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Archive mise à jour avec succès'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'idSalle', type: 'integer', example: 2),
                                new OA\Property(property: 'idCoffre', type: 'integer', example: 3),
                                new OA\Property(property: 'isActive', type: 'boolean', example: true),
                                new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Données invalides.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.'),
            new OA\Response(response: 404, description: 'Archive, salle ou coffre introuvable.')
        ]
    )]
    public function patch(int $id, Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'PatchArchive');

        // RÃ©cupÃ©rer l'archive
        $archive = $this->archiveRepository->find($id);

        if (!$archive) {
            return $this->json([
                'error' => 'Archive introuvable'
            ], 404);
        }

        // VÃ©rifier si l'archive n'est pas supprimÃ©e
        if ($archive->isDelete()) {
            return $this->json([
                'error' => 'Impossible de modifier une archive supprimÃ©e'
            ], 400);
        }

        $data = json_decode($request->getContent(), true);

        if (empty($data)) {
            return $this->json([
                'error' => 'Aucune donnée à mettre à jour'
            ], 400);
        }

        $ancienCoffre = null;
        $nouveauCoffre = null;

        // Si on change le coffre, on doit gÃ©rer la capacitÃ©
        if (isset($data['idCoffre']) && $data['idCoffre'] !== $archive->getIdCoffre()) {
            $ancienCoffre = $this->coffreRepository->find($archive->getIdCoffre());
            $nouveauCoffre = $this->coffreRepository->find($data['idCoffre']);

            if (!$nouveauCoffre || $nouveauCoffre->isDelete()) {
                return $this->json([
                    'error' => 'Nouveau coffre introuvable ou supprimé'
                ], 404);
            }

            if (!$nouveauCoffre->isActive()) {
                return $this->json([
                    'error' => 'Le nouveau coffre n\'est pas actif'
                ], 400);
            }

            // VÃ©rifier que le nouveau coffre a de la place
            if ($nouveauCoffre->isPlein()) {
                return $this->json([
                    'error' => 'Le nouveau coffre est plein',
                    'capacite' => [
                        'actuelle' => $nouveauCoffre->getNombrePlaceActuelle(),
                        'maximale' => $nouveauCoffre->getTailleMaximale()
                    ]
                ], 400);
            }

            // Si on change aussi la salle, vÃ©rifier la cohÃ©rence
            $nouvelleSalleId = $data['idSalle'] ?? $archive->getIdSalle();
            if ($nouveauCoffre->getIdSalle() !== (int) $nouvelleSalleId) {
                return $this->json([
                    'error' => 'Le coffre ne correspond pas à la salle spécifiée'
                ], 400);
            }
        }

        // Mise à jour de la salle
        if (isset($data['idSalle'])) {
            $salle = $this->salleRepository->find($data['idSalle']);
            
            if (!$salle || $salle->isDelete()) {
                return $this->json([
                    'error' => 'Salle introuvable ou supprimée'
                ], 404);
            }

            if (!$salle->isActive()) {
                return $this->json([
                    'error' => 'La salle n\'est pas active'
                ], 400);
            }

            $archive->setIdSalle((int) $data['idSalle']);
        }

        // Mise Ã  jour du coffre avec gestion de la capacitÃ©
        if (isset($data['idCoffre'])) {
            $archive->setIdCoffre((int) $data['idCoffre']);

            // DÃ©crÃ©menter l'ancien coffre et incrÃ©menter le nouveau
            if ($ancienCoffre && $nouveauCoffre) {
                $ancienCoffre->decrementerPlace();
                $nouveauCoffre->incrementerPlace();
                
                $this->entityManager->persist($ancienCoffre);
                $this->entityManager->persist($nouveauCoffre);
            }
        }

        // Mise Ã  jour du statut actif
        if (isset($data['isActive'])) {
            $archive->setIsActive((bool) $data['isActive']);
        }

        // Mise Ã  jour des documents archivÃ©s (remplacement complet)
        if (isset($data['idCourriers'])) {
            if (is_array($data['idCourriers'])) {
                // Valider que tous les courriers existent
                foreach ($data['idCourriers'] as $idCourrier) {
                    $courrier = $this->courrierRepository->find($idCourrier);
                    if (!$courrier || $courrier->isDelete()) {
                        return $this->json([
                            'error' => "Courrier ID {$idCourrier} introuvable ou supprimé"
                        ], 404);
                    }
                }
                $archive->setIdCourriers(array_map('intval', $data['idCourriers']));
            }
        }

        if (isset($data['idTransmissions'])) {
            if (is_array($data['idTransmissions'])) {
                // Valider que toutes les transmissions existent
                foreach ($data['idTransmissions'] as $idTransmission) {
                    $transmission = $this->transmissionRepository->find($idTransmission);
                    if (!$transmission || $transmission->isDelete()) {
                        return $this->json([
                            'error' => "Transmission ID {$idTransmission} introuvable ou supprimée"
                        ], 404);
                    }
                }
                $archive->setIdTransmissions(array_map('intval', $data['idTransmissions']));
            }
        }

        if (isset($data['idCourriersDepart'])) {
            if (is_array($data['idCourriersDepart'])) {
                // Valider que tous les courriers dÃ©part existent
                foreach ($data['idCourriersDepart'] as $idCourrierDepart) {
                    $courrierDepart = $this->courrierDepartRepository->find($idCourrierDepart);
                    if (!$courrierDepart || $courrierDepart->isDelete()) {
                        return $this->json([
                            'error' => "Courrier départ ID {$idCourrierDepart} introuvable ou supprimé"
                        ], 404);
                    }
                }
                $archive->setIdCourriersDepart(array_map('intval', $data['idCourriersDepart']));
            }
        }

        // Ajout de documents individuels
        if (isset($data['addIdCourrier'])) {
            $courrier = $this->courrierRepository->find($data['addIdCourrier']);
            if (!$courrier || $courrier->isDelete()) {
                return $this->json(['error' => 'Courrier introuvable ou supprimé'], 404);
            }
            $archive->addIdCourrier((int) $data['addIdCourrier']);
        }

        if (isset($data['addIdTransmission'])) {
            $transmission = $this->transmissionRepository->find($data['addIdTransmission']);
            if (!$transmission || $transmission->isDelete()) {
                return $this->json(['error' => 'Transmission introuvable ou supprimée'], 404);
            }
            $archive->addIdTransmission((int) $data['addIdTransmission']);
        }

        if (isset($data['addIdCourrierDepart'])) {
            $courrierDepart = $this->courrierDepartRepository->find($data['addIdCourrierDepart']);
            if (!$courrierDepart || $courrierDepart->isDelete()) {
                return $this->json(['error' => 'Courrier départ introuvable ou supprimé'], 404);
            }
            $archive->addIdCourrierDepart((int) $data['addIdCourrierDepart']);
        }

        // Suppression de documents individuels
        if (isset($data['removeIdCourrier'])) {
            $archive->removeIdCourrier((int) $data['removeIdCourrier']);
        }

        if (isset($data['removeIdTransmission'])) {
            $archive->removeIdTransmission((int) $data['removeIdTransmission']);
        }

        if (isset($data['removeIdCourrierDepart'])) {
            $archive->removeIdCourrierDepart((int) $data['removeIdCourrierDepart']);
        }

        // Sauvegarder
        $this->entityManager->persist($archive);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Archive mise Ã  jour avec succÃ¨s',
            'data' => [
                'id' => $archive->getId(),
                'idSalle' => $archive->getIdSalle(),
                'idCoffre' => $archive->getIdCoffre(),
                'idCourriers' => $archive->getIdCourriers(),
                'idTransmissions' => $archive->getIdTransmissions(),
                'idCourriersDepart' => $archive->getIdCourriersDepart(),
                'isActive' => $archive->isActive(),
                'isDelete' => $archive->isDelete(),
                'updatedAt' => $archive->getUpdatedAt()?->format('c'),
            ]
        ], 200);
    }
}
