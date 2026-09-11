<?php

namespace App\Controller\Core\Archive;

use App\Entity\Core\Archive;
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
class PostController extends AbstractController
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

    #[Route('/core/archive', name: 'app_core_archive_post', methods: ['POST'])]
    #[OA\Post(
        path: '/core/archive',
        summary: 'Archiver un document',
        tags: ['Archive'],
        description: "Enregistre l'emplacement d'un document (courrier, transmission ou courrier départ) dans une salle et un coffre spécifiques.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['idSalle', 'idCoffre'],
                properties: [
                    new OA\Property(
                        property: 'idCourriers',
                        type: 'array',
                        description: 'Liste des IDs des courriers à archiver',
                        items: new OA\Items(type: 'integer'),
                        example: [1, 2, 3],
                        nullable: true
                    ),
                    new OA\Property(
                        property: 'idTransmissions',
                        type: 'array',
                        description: 'Liste des IDs des transmissions à archiver',
                        items: new OA\Items(type: 'integer'),
                        example: [4, 5],
                        nullable: true
                    ),
                    new OA\Property(
                        property: 'idCourriersDepart',
                        type: 'array',
                        description: 'Liste des IDs des courriers départ à archiver',
                        items: new OA\Items(type: 'integer'),
                        example: [6, 7, 8],
                        nullable: true
                    ),
                    new OA\Property(
                        property: 'idSalle',
                        type: 'integer',
                        description: 'ID de la salle où se trouve le document',
                        example: 1
                    ),
                    new OA\Property(
                        property: 'idCoffre',
                        type: 'integer',
                        description: 'ID du coffre où se trouve le document',
                        example: 1
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Archive créée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Archive créée avec succès'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'idCourrier', type: 'integer', example: 1, nullable: true),
                                new OA\Property(property: 'idTransmission', type: 'integer', example: 2, nullable: true),
                                new OA\Property(property: 'idCourrierDepart', type: 'integer', example: 3, nullable: true),
                                new OA\Property(property: 'idSalle', type: 'integer', example: 1),
                                new OA\Property(property: 'idCoffre', type: 'integer', example: 1),
                                new OA\Property(property: 'isActive', type: 'boolean', example: true),
                                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-12-02T10:30:00+00:00'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Données invalides.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.'),
            new OA\Response(response: 404, description: 'Salle ou coffre introuvable.')
        ]
    )]
    public function create(Request $request): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'PostArchive');

        $data = json_decode($request->getContent(), true);

        // Validation des champs requis
        if (!isset($data['idSalle']) || !isset($data['idCoffre'])) {
            return $this->json([
                'error' => 'Les champs idSalle et idCoffre sont obligatoires'
            ], 400);
        }

        // Au moins un type de document doit Ãªtre fourni
        if (empty($data['idCourriers']) && empty($data['idTransmissions']) && empty($data['idCourriersDepart'])) {
            return $this->json([
                'error' => 'Au moins un des champs idCourriers, idTransmissions ou idCourriersDepart doit être fourni'
            ], 400);
        }

        // VÃ©rifier que la salle existe et est active
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

        // VÃ©rifier que le coffre existe et est actif
        $coffre = $this->coffreRepository->find($data['idCoffre']);
        if (!$coffre || $coffre->isDelete()) {
            return $this->json([
                'error' => 'Coffre introuvable ou supprimé'
            ], 404);
        }

        if (!$coffre->isActive()) {
            return $this->json([
                'error' => 'Le coffre n\'est pas actif'
            ], 400);
        }

        // VÃ©rifier que le coffre appartient bien Ã  la salle
        if ($coffre->getIdSalle() !== (int) $data['idSalle']) {
            return $this->json([
                'error' => 'Le coffre ne correspond pas à la salle spécifiée'
            ], 400);
        }

        // VÃ©rifier si le coffre est plein
        if ($coffre->isPlein()) {
            return $this->json([
                'error' => 'On ne peut plus ajouter une archive dans ce coffre car il a déjà atteint la taille maximale',
                'capacite' => [
                    'actuelle' => $coffre->getNombrePlaceActuelle(),
                    'maximale' => $coffre->getTailleMaximale()
                ]
            ], 400);
        }

        // CrÃ©er l'archive
        $archive = new Archive();
        $archive->setIdSalle((int) $data['idSalle']);
        $archive->setIdCoffre((int) $data['idCoffre']);

        // Mettre Ã  jour les courriers si fournis
        if (!empty($data['idCourriers']) && is_array($data['idCourriers'])) {
            $idCourriers = array_map('intval', $data['idCourriers']);
            $archive->setIdCourriers($idCourriers);
            
            // Mettre Ã  jour le statut, isArchive et statutArchive de chaque courrier
            foreach ($idCourriers as $idCourrier) {
                $courrier = $this->courrierRepository->find($idCourrier);
                if ($courrier && !$courrier->isDelete()) {
                    $courrier->setStatut('archivé');
                    $courrier->setArchive(true);
                    $courrier->setStatutArchive('non transféré');
                    $this->entityManager->persist($courrier);
                }
            }
        }

        // Mettre Ã  jour les transmissions si fournies
        if (!empty($data['idTransmissions']) && is_array($data['idTransmissions'])) {
            $idTransmissions = array_map('intval', $data['idTransmissions']);
            $archive->setIdTransmissions($idTransmissions);
            
            // Mettre Ã  jour le statut, isArchive et statutArchive de chaque transmission
            foreach ($idTransmissions as $idTransmission) {
                $transmission = $this->transmissionRepository->find($idTransmission);
                if ($transmission && !$transmission->isDelete()) {
                    $transmission->setStatut('archivé');
                    $transmission->setArchive(true);
                    $transmission->setStatutArchive('non transféré');
                    $this->entityManager->persist($transmission);
                }
            }
        }

        // Mettre Ã  jour les courriers dÃ©part si fournis
        if (!empty($data['idCourriersDepart']) && is_array($data['idCourriersDepart'])) {
            $idCourriersDepart = array_map('intval', $data['idCourriersDepart']);
            $archive->setIdCourriersDepart($idCourriersDepart);
            
            // Mettre Ã  jour isArchive et statutArchive de chaque courrier dÃ©part
            foreach ($idCourriersDepart as $idCourrierDepart) {
                $courrierDepart = $this->courrierDepartRepository->find($idCourrierDepart);
                if ($courrierDepart && !$courrierDepart->isDelete()) {
                    $courrierDepart->setArchive(true);
                    $courrierDepart->setStatutArchive('non transféré');
                    $this->entityManager->persist($courrierDepart);
                }
            }
        }

        // IncrÃ©menter le nombre de places actuelles du coffre
        $coffre->incrementerPlace();

        // Sauvegarder l'archive et le coffre
        $this->entityManager->persist($archive);
        $this->entityManager->persist($coffre);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Archive crée avec succès',
            'data' => [
                'id' => $archive->getId(),
                'idCourriers' => $archive->getIdCourriers(),
                'idTransmissions' => $archive->getIdTransmissions(),
                'idCourriersDepart' => $archive->getIdCourriersDepart(),
                'idSalle' => $archive->getIdSalle(),
                'idCoffre' => $archive->getIdCoffre(),
                'isActive' => $archive->isActive(),
                'createdAt' => $archive->getCreatedAt()?->format('c'),
            ]
        ], 201);
    }
}
