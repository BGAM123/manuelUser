<?php

namespace App\Controller\Core\Archive;

use App\Entity\Core\Archive;
use App\Entity\Core\Coffre;
use App\Repository\Cour\CourrierRepository;
use App\Repository\Cour\TransmissionRepository;
use App\Repository\Cour\CourrierDepartRepository;
use App\Repository\Core\SalleRepository;
use App\Service\Core\AccessCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[OA\Tag(name: "Archive")]
class EmptyCoffreController extends AbstractController
{
    public function __construct(
        private AccessCheckerService $accessChecker,
        private EntityManagerInterface $entityManager,
        private CourrierRepository $courrierRepository,
        private TransmissionRepository $transmissionRepository,
        private CourrierDepartRepository $courrierDepartRepository,
        private SalleRepository $salleRepository,
        private SluggerInterface $slugger,
        private ParameterBagInterface $params,
    ) {}

    #[Route('/core/archive/empty-coffre', name: 'app_core_archive_empty_coffre', methods: ['POST'])]
    #[OA\Post(
        path: '/core/archive/empty-coffre',
        summary: 'Vider un ou plusieurs coffres d\'archives',
        tags: ['Archive'],
        description: "Vide complètement le contenu d'un ou plusieurs coffres en supprimant tous les courriers, transmissions et courriers dÃ©part qu'ils contiennent. Un fichier justificatif est obligatoire.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['coffreIds', 'fichier'],
                    properties: [
                        new OA\Property(
                            property: 'coffreIds',
                            type: 'string',
                            example: '1,3,5',
                            description: 'Liste des IDs des coffres à  vider (séparés par des virgules)'
                        ),
                        new OA\Property(
                            property: 'fichier',
                            type: 'string',
                            format: 'binary',
                            description: 'Fichier justificatif du vidage (obligatoire) - PDF, Word, Image, etc.'
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Coffres vidés avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: '3 coffre(s) vidé(s) avec succès.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'coffresVides', type: 'integer', example: 3, description: 'Nombre de coffres vidés'),
                                new OA\Property(property: 'archivesModifiees', type: 'integer', example: 15, description: 'Nombre d\'archives modifiées'),
                                new OA\Property(property: 'fichierJustificatif', type: 'string', example: 'vidage-coffre-20251209-153045.pdf', description: 'Nom du fichier uploadé'),
                                new OA\Property(
                                    property: 'details',
                                    type: 'array',
                                    items: new OA\Items(
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'coffreId', type: 'integer', example: 1),
                                            new OA\Property(property: 'coffreNom', type: 'string', example: 'Coffre A'),
                                            new OA\Property(property: 'salleId', type: 'integer', example: 1),
                                            new OA\Property(property: 'archivesVidees', type: 'integer', example: 5),
                                            new OA\Property(property: 'courriersSupprimes', type: 'integer', example: 12),
                                            new OA\Property(property: 'transmissionsSupprimes', type: 'integer', example: 8),
                                            new OA\Property(property: 'courriersDepartSupprimes', type: 'integer', example: 6),
                                            new OA\Property(property: 'ancienNombrePlaces', type: 'integer', example: 26),
                                            new OA\Property(property: 'nouveauNombrePlaces', type: 'integer', example: 0),
                                            new OA\Property(property: 'tailleMaximale', type: 'integer', example: 20),
                                            new OA\Property(property: 'placesDisponibles', type: 'integer', example: 20),
                                            new OA\Property(property: 'isPlein', type: 'boolean', example: false),
                                        ]
                                    )
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Requête invalide - données incorrectes ou fichier manquant.'),
            new OA\Response(response: 401, description: 'Accès non autorisé - authentification requise.'),
            new OA\Response(response: 403, description: 'Accès interdit - permissions insuffisantes.')
        ]
    )]
    public function emptyCoffre(Request $request): Response
    {
        // VÃ©rification des permissions
        $this->accessChecker->checker(
            $this->getUser(), 
            $this->isGranted('ROLE_ADMIN'), 
            'EmptyCoffreArchive'
        );

        // RÃ©cupÃ©ration des donnÃ©es du formulaire multipart
        $coffreIdsString = $request->request->get('coffreIds');
        $fichier = $request->files->get('fichier');
        
        // Validation du fichier (obligatoire)
        if (!$fichier) {
            return $this->json([
                'code' => 400,
                'message' => 'Le fichier justificatif est obligatoire pour le vidage des coffres.'
            ], 400);
        }

        // Validation des IDs de coffres
        if (!$coffreIdsString) {
            return $this->json([
                'code' => 400,
                'message' => 'Le champ "coffreIds" est requis et doit contenir une liste d\'IDs de coffres séparés par des virgules.'
            ], 400);
        }

        // Convertir la chaîne "1,3,5" en tableau d'entiers
        $coffreIds = array_map('intval', array_filter(explode(',', $coffreIdsString)));

        if (empty($coffreIds)) {
            return $this->json([
                'code' => 400,
                'message' => 'Le champ "coffreIds" doit contenir au moins un identifiant valide.'
            ], 400);
        }

        try {
            // Upload du fichier justificatif
            $uploadsDirectory = $this->params->get('app_uploads_courrier_directory');
            $originalFilename = pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $this->slugger->slug($originalFilename);
            $extension = $fichier->guessExtension();
            $newFilename = 'vidage-coffre-' . date('Ymd-His') . '-' . $safeFilename . '.' . $extension;

            try {
                $fichier->move($uploadsDirectory, $newFilename);
            } catch (FileException $e) {
                return $this->json([
                    'code' => 500,
                    'message' => 'Erreur lors de l\'upload du fichier justificatif.',
                    'error' => $e->getMessage()
                ], 500);
            }

            $details = [];
            $totalArchivesModifiees = 0;
            $totalCourriersSupprimes = 0;
            $totalTransmissionsSupprimes = 0;
            $totalCourriersDepartSupprimes = 0;
            $totalCoffresReinitialises = 0;

            foreach ($coffreIds as $coffreId) {
                if (!is_numeric($coffreId)) {
                    continue;
                }

                // RÃ©cupÃ©rer le coffre
                $coffre = $this->entityManager->getRepository(Coffre::class)->find((int) $coffreId);
                
                if (!$coffre) {
                    // Si le coffre n'existe pas, on passe au suivant
                    continue;
                }

                // RÃ©cupÃ©rer toutes les archives de ce coffre
                $archives = $this->entityManager->getRepository(Archive::class)
                    ->createQueryBuilder('a')
                    ->where('a.idCoffre = :coffreId')
                    ->andWhere('a.isDelete = false')
                    ->setParameter('coffreId', (int) $coffreId)
                    ->getQuery()
                    ->getResult();

                $archivesVidees = 0;
                $courriersSupprimes = 0;
                $transmissionsSupprimes = 0;
                $courriersDepartSupprimes = 0;

                foreach ($archives as $archive) {
                    // RÃ©cupÃ©rer les IDs avant de vider
                    $idCourriers = $archive->getIdCourriers() ?? [];
                    $idTransmissions = $archive->getIdTransmissions() ?? [];
                    $idCourriersDepart = $archive->getIdCourriersDepart() ?? [];
                    
                    // Compter les Ã©lÃ©ments avant de vider
                    $courriersSupprimes += count($idCourriers);
                    $transmissionsSupprimes += count($idTransmissions);
                    $courriersDepartSupprimes += count($idCourriersDepart);

                    // RÃ©cupÃ©rer les informations de l'utilisateur connectÃ© pour viderPar
                    $currentUser = $this->getUser();
                    $viderParData = null;
                    if ($currentUser instanceof \App\Entity\Core\User) {
                        // RÃ©cupÃ©rer l'entitÃ© Salle complÃ¨te
                        $salleId = $coffre->getIdSalle();
                        $salle = $salleId ? $this->salleRepository->find($salleId) : null;
                        
                        $viderParData = [
                            'id' => $currentUser->getId(),
                            'fullName' => $currentUser->getFullName(),
                            'date' => (new \DateTime())->format('Y-m-d H:i:s'),
                            'salle' => $salle ? [
                                'id' => $salle->getId(),
                                'nom' => $salle->getNom(),
                            ] : null,
                            'coffre' => [
                                'id' => $coffre->getId(),
                                'nom' => $coffre->getNom(),
                            ],
                            'fichierJustificatif' => $newFilename,
                        ];
                    }

                    // Mettre Ã  jour le statutArchive des courriers Ã  "transfÃ©rÃ©" et enregistrer viderPar
                    foreach ($idCourriers as $idCourrier) {
                        $courrier = $this->courrierRepository->find($idCourrier);
                        if ($courrier && !$courrier->isDelete()) {
                            $courrier->setStatutArchive('transfÃ©rÃ©');
                            if ($viderParData) {
                                $courrier->setViderPar($viderParData);
                            }
                            $this->entityManager->persist($courrier);
                        }
                    }

                    // Mettre Ã  jour le statutArchive des transmissions Ã  "transfÃ©rÃ©" et enregistrer viderPar
                    foreach ($idTransmissions as $idTransmission) {
                        $transmission = $this->transmissionRepository->find($idTransmission);
                        if ($transmission && !$transmission->isDelete()) {
                            $transmission->setStatutArchive('transféré');
                            if ($viderParData) {
                                $transmission->setViderPar($viderParData);
                            }
                            $this->entityManager->persist($transmission);
                        }
                    }

                    // Mettre à  jour le statutArchive des courriers départ à  "transféré" et enregistrer viderPar
                    foreach ($idCourriersDepart as $idCourrierDepart) {
                        $courrierDepart = $this->courrierDepartRepository->find($idCourrierDepart);
                        if ($courrierDepart && !$courrierDepart->isDelete()) {
                            $courrierDepart->setStatutArchive('transféré');
                            if ($viderParData) {
                                $courrierDepart->setViderPar($viderParData);
                            }
                            $this->entityManager->persist($courrierDepart);
                        }
                    }

                    // Vider le contenu de l'archive
                    $archive->setIdCourriers([]);
                    $archive->setIdTransmissions([]);
                    $archive->setIdCourriersDepart([]);

                    $this->entityManager->persist($archive);
                    $archivesVidees++;
                }

                // RÃ©initialiser les statistiques du coffre
                $coffre->setNombrePlaceActuelle(0);
                $this->entityManager->persist($coffre);
                $totalCoffresReinitialises++;

                $totalArchivesModifiees += $archivesVidees;
                $totalCourriersSupprimes += $courriersSupprimes;
                $totalTransmissionsSupprimes += $transmissionsSupprimes;
                $totalCourriersDepartSupprimes += $courriersDepartSupprimes;

                // Ajouter les dÃ©tails pour ce coffre
                $details[] = [
                    'coffreId' => (int) $coffreId,
                    'coffreNom' => $coffre->getNom(),
                    'salleId' => $coffre->getIdSalle(),
                    'archivesVidees' => $archivesVidees,
                    'courriersSupprimes' => $courriersSupprimes,
                    'transmissionsSupprimes' => $transmissionsSupprimes,
                    'courriersDepartSupprimes' => $courriersDepartSupprimes,
                    'ancienNombrePlaces' => $courriersSupprimes + $transmissionsSupprimes + $courriersDepartSupprimes,
                    'nouveauNombrePlaces' => 0,
                    'tailleMaximale' => $coffre->getTailleMaximale(),
                    'placesDisponibles' => $coffre->getTailleMaximale(),
                    'isPlein' => false,
                ];
            }

            // Sauvegarder toutes les modifications
            $this->entityManager->flush();

            // RÃ©cupÃ©rer les informations de l'utilisateur connectÃ©
            $currentUser = $this->getUser();
            $userInfo = null;
            if ($currentUser instanceof \App\Entity\Core\User) {
                $userInfo = [
                    'id' => $currentUser->getId(),
                    'nom' => $currentUser->getLastName() ?? 'N/A',
                    'prenom' => $currentUser->getFirstName() ?? 'N/A',
                    'nomComplet' => $currentUser->getFullName(),
                    'email' => $currentUser->getEmail() ?? 'N/A',
                ];
            }

            return $this->json([
                'code' => 200,
                'message' => $totalCoffresReinitialises . ' coffre(s) vidé(s) avec succès.',
                'data' => [
                    'coffresVides' => $totalCoffresReinitialises,
                    'archivesModifiees' => $totalArchivesModifiees,
                    'totalCourriersSupprimes' => $totalCourriersSupprimes,
                    'totalTransmissionsSupprimes' => $totalTransmissionsSupprimes,
                    'totalCourriersDepartSupprimes' => $totalCourriersDepartSupprimes,
                    'fichierJustificatif' => $newFilename,
                    'videParUtilisateur' => $userInfo,
                    'dateOperation' => (new \DateTime())->format('Y-m-d H:i:s'),
                    'details' => $details
                ]
            ], 200);

        } catch (\Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => 'Une erreur est survenue lors du vidage des coffres.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
