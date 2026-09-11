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
class RemoveCourrierFromCoffreController extends AbstractController
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

    #[Route('/core/archive/remove-courrier-from-coffre', name: 'app_core_archive_remove_courrier_from_coffre', methods: ['POST'])]
    #[OA\Post(
        path: '/core/archive/remove-courrier-from-coffre',
        summary: 'Retirer des courriers spécifiques de coffres',
        tags: ['Archive'],
        description: "Retire des courriers, transmissions ou courriers départ spécifiques d'un ou plusieurs coffres et met à jour le nombre de places actuelles. Un fichier justificatif est obligatoire.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['coffres', 'fichier'],
                    properties: [
                        new OA\Property(
                            property: 'coffres',
                            type: 'string',
                            description: 'JSON contenant la liste des coffres avec leurs éléments à retirer',
                            example: '[{"coffreId":10,"courrierIds":[1,3,5],"transmissionIds":[2,4],"courrierDepartIds":[6,8]},{"coffreId":20,"courrierIds":[15,18],"transmissionIds":[],"courrierDepartIds":[25,30]}]'
                        ),
                        new OA\Property(
                            property: 'fichier',
                            type: 'string',
                            format: 'binary',
                            description: 'Fichier justificatif du retrait (obligatoire) - PDF, Word, Image, etc.'
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Courriers retirés avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: '8 élément(s) retiré(s) de 3 coffre(s) avec succès.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'nombreCoffresTraites', type: 'integer', example: 3),
                                new OA\Property(property: 'archivesModifiees', type: 'integer', example: 5),
                                new OA\Property(property: 'courriersRetires', type: 'integer', example: 3),
                                new OA\Property(property: 'transmissionsRetirees', type: 'integer', example: 2),
                                new OA\Property(property: 'courriersDepartRetires', type: 'integer', example: 3),
                                new OA\Property(property: 'totalElementsRetires', type: 'integer', example: 8),
                                new OA\Property(property: 'fichierJustificatif', type: 'string', example: 'retrait-coffre-20251209-153045.pdf', description: 'Nom du fichier uploadé'),
                                new OA\Property(
                                    property: 'coffresDetails',
                                    type: 'array',
                                    items: new OA\Items(
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'coffreId', type: 'integer', example: 10),
                                            new OA\Property(property: 'coffreNom', type: 'string', example: 'Coffre A1'),
                                            new OA\Property(property: 'ancienNombrePlaces', type: 'integer', example: 15),
                                            new OA\Property(property: 'nouveauNombrePlaces', type: 'integer', example: 7),
                                            new OA\Property(property: 'placesDisponibles', type: 'integer', example: 13),
                                            new OA\Property(property: 'tailleMaximale', type: 'integer', example: 20),
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
            new OA\Response(response: 403, description: 'Accès interdit - permissions insuffisantes.'),
            new OA\Response(response: 404, description: 'Coffre non trouvé.')
        ]
    )]
    public function removeCourrierFromCoffre(Request $request): Response
    {
        // VÃ©rification des permissions
        $this->accessChecker->checker(
            $this->getUser(), 
            $this->isGranted('ROLE_ADMIN'), 
            'RemoveCourrierFromCoffre'
        );

        // RÃ©cupÃ©ration du fichier (obligatoire)
        $fichier = $request->files->get('fichier');
        
        if (!$fichier) {
            return $this->json([
                'code' => 400,
                'message' => 'Le fichier justificatif est obligatoire pour le retrait des éléments.'
            ], 400);
        }

        // RÃ©cupÃ©ration et validation des donnÃ©es JSON
        $coffresJson = $request->request->get('coffres');
        
        if (!$coffresJson) {
            return $this->json([
                'code' => 400,
                'message' => 'Le champ "coffres" est requis.'
            ], 400);
        }

        // DÃ©coder le JSON
        $coffresData = json_decode($coffresJson, true);
        
        if (!$coffresData || !is_array($coffresData) || empty($coffresData)) {
            return $this->json([
                'code' => 400,
                'message' => 'Le champ "coffres" doit être un JSON valide contenant un tableau non vide.'
            ], 400);
        }

        // Valider chaque coffre
        foreach ($coffresData as $index => $coffreData) {
            if (!isset($coffreData['coffreId'])) {
                return $this->json([
                    'code' => 400,
                    'message' => "Le champ \"coffreId\" est requis pour l'élément à l'index $index."
                ], 400);
            }

            $courrierIds = $coffreData['courrierIds'] ?? [];
            $transmissionIds = $coffreData['transmissionIds'] ?? [];
            $courrierDepartIds = $coffreData['courrierDepartIds'] ?? [];

            // VÃ©rifier qu'au moins un type d'Ã©lÃ©ment est fourni pour ce coffre
            if (empty($courrierIds) && empty($transmissionIds) && empty($courrierDepartIds)) {
                return $this->json([
                    'code' => 400,
                    'message' => "Vous devez fournir au moins un élément à retirer pour le coffre ID {$coffreData['coffreId']} (courrierIds, transmissionIds ou courrierDepartIds)."
                ], 400);
            }
        }

        try {
            // Upload du fichier justificatif
            $uploadsDirectory = $this->params->get('app_uploads_courrier_directory');
            $originalFilename = pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $this->slugger->slug($originalFilename);
            $extension = $fichier->guessExtension();
            $newFilename = 'retrait-coffre-' . date('Ymd-His') . '-' . $safeFilename . '.' . $extension;

            try {
                $fichier->move($uploadsDirectory, $newFilename);
            } catch (FileException $e) {
                return $this->json([
                    'code' => 500,
                    'message' => 'Erreur lors de l\'upload du fichier justificatif.',
                    'error' => $e->getMessage()
                ], 500);
            }
            // Extraire les IDs des coffres
            $coffreIds = array_map(fn($c) => (int)$c['coffreId'], $coffresData);
            
            // RÃ©cupÃ©rer les coffres
            $coffres = $this->entityManager->getRepository(Coffre::class)
                ->createQueryBuilder('c')
                ->where('c.id IN (:coffreIds)')
                ->setParameter('coffreIds', $coffreIds)
                ->getQuery()
                ->getResult();
            
            if (empty($coffres)) {
                return $this->json([
                    'code' => 404,
                    'message' => 'Aucun coffre trouvé avec les IDs fournis.'
                ], 404);
            }

            // CrÃ©er un mapping des coffres par ID
            $coffresById = [];
            foreach ($coffres as $coffre) {
                $coffresById[$coffre->getId()] = $coffre;
            }

            // Variables globales pour les statistiques
            $archivesModifiees = 0;
            $courriersRetires = 0;
            $transmissionsRetirees = 0;
            $courriersDepartRetires = 0;
            $coffresDetails = [];

            // Traiter chaque coffre avec ses propres courriers
            foreach ($coffresData as $coffreData) {
                $coffreId = (int)$coffreData['coffreId'];
                
                // VÃ©rifier si le coffre existe
                if (!isset($coffresById[$coffreId])) {
                    continue; // Ignorer les coffres non trouvÃ©s
                }
                
                $coffre = $coffresById[$coffreId];
                
                // RÃ©cupÃ©rer les IDs spÃ©cifiques pour ce coffre
                $courrierIds = $coffreData['courrierIds'] ?? [];
                $transmissionIds = $coffreData['transmissionIds'] ?? [];
                $courrierDepartIds = $coffreData['courrierDepartIds'] ?? [];
                
                // Sauvegarder l'ancien nombre de places
                $ancienNombrePlaces = $coffre->getNombrePlaceActuelle();

                // RÃ©cupÃ©rer toutes les archives de ce coffre
                $archives = $this->entityManager->getRepository(Archive::class)
                    ->createQueryBuilder('a')
                    ->where('a.idCoffre = :coffreId')
                    ->andWhere('a.isDelete = false')
                    ->setParameter('coffreId', $coffreId)
                    ->getQuery()
                    ->getResult();

                $archivesModifieesParCoffre = 0;
                $courriersRetiresParCoffre = 0;
                $transmissionsRetireesParCoffre = 0;
                $courriersDepartRetiresParCoffre = 0;

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

                foreach ($archives as $archive) {
                    $modifie = false;

                    // Retirer les courriers spÃ©cifiÃ©s
                    if (!empty($courrierIds)) {
                        $courriersActuels = $archive->getIdCourriers() ?? [];
                        
                        // Trouver les courriers qui vont Ãªtre retirÃ©s pour mettre Ã  jour leur statut
                        $courriersARetirer = array_filter(
                            $courriersActuels,
                            fn($id) => in_array($id, $courrierIds)
                        );
                        
                        // Mettre Ã  jour le statutArchive de chaque courrier retirÃ©
                        foreach ($courriersARetirer as $courrierId) {
                            $courrier = $this->courrierRepository->find($courrierId);
                            if ($courrier) {
                                // S'assurer que isArchive est Ã  true (au cas oÃ¹)
                                if (!$courrier->isArchive()) {
                                    $courrier->setArchive(true);
                                }
                                $courrier->setStatutArchive('transféré');
                                if ($viderParData) {
                                    $courrier->setViderPar($viderParData);
                                }
                                $this->entityManager->persist($courrier);
                            }
                        }
                        
                        $courriersRestants = array_values(array_filter(
                            $courriersActuels,
                            fn($id) => !in_array($id, $courrierIds)
                        ));
                        
                        $nbRetires = count($courriersActuels) - count($courriersRestants);
                        if ($nbRetires > 0) {
                            $archive->setIdCourriers($courriersRestants);
                            $courriersRetiresParCoffre += $nbRetires;
                            $modifie = true;
                        }
                    }

                    // Retirer les transmissions spÃ©cifiÃ©es
                    if (!empty($transmissionIds)) {
                        $transmissionsActuelles = $archive->getIdTransmissions() ?? [];
                        
                        // Trouver les transmissions qui vont Ãªtre retirÃ©es pour mettre Ã  jour leur statut
                        $transmissionsARetirer = array_filter(
                            $transmissionsActuelles,
                            fn($id) => in_array($id, $transmissionIds)
                        );
                        
                        // Mettre Ã  jour le statutArchive de chaque transmission retirÃ©e
                        foreach ($transmissionsARetirer as $transmissionId) {
                            $transmission = $this->transmissionRepository->find($transmissionId);
                            if ($transmission) {
                                // S'assurer que isArchive est Ã  true (au cas oÃ¹)
                                if (!$transmission->isArchive()) {
                                    $transmission->setArchive(true);
                                }
                                $transmission->setStatutArchive('transféré');
                                if ($viderParData) {
                                    $transmission->setViderPar($viderParData);
                                }
                                $this->entityManager->persist($transmission);
                            }
                        }
                        
                        $transmissionsRestantes = array_values(array_filter(
                            $transmissionsActuelles,
                            fn($id) => !in_array($id, $transmissionIds)
                        ));
                        
                        $nbRetires = count($transmissionsActuelles) - count($transmissionsRestantes);
                        if ($nbRetires > 0) {
                            $archive->setIdTransmissions($transmissionsRestantes);
                            $transmissionsRetireesParCoffre += $nbRetires;
                            $modifie = true;
                        }
                    }

                    // Retirer les courriers dÃ©part spÃ©cifiÃ©s
                    if (!empty($courrierDepartIds)) {
                        $courriersDepartActuels = $archive->getIdCourriersDepart() ?? [];
                        
                        // Trouver les courriers dÃ©part qui vont Ãªtre retirÃ©s pour mettre Ã  jour leur statut
                        $courriersDepartARetirer = array_filter(
                            $courriersDepartActuels,
                            fn($id) => in_array($id, $courrierDepartIds)
                        );
                        
                        // Mettre Ã  jour le statutArchive de chaque courrier dÃ©part retirÃ©
                        foreach ($courriersDepartARetirer as $courrierDepartId) {
                            $courrierDepart = $this->courrierDepartRepository->find($courrierDepartId);
                            if ($courrierDepart) {
                                // S'assurer que isArchive est Ã  true (au cas oÃ¹)
                                if (!$courrierDepart->isArchive()) {
                                    $courrierDepart->setArchive(true);
                                }
                                $courrierDepart->setStatutArchive('transféré');
                                if ($viderParData) {
                                    $courrierDepart->setViderPar($viderParData);
                                }
                                $this->entityManager->persist($courrierDepart);
                            }
                        }
                        
                        $courriersDepartRestants = array_values(array_filter(
                            $courriersDepartActuels,
                            fn($id) => !in_array($id, $courrierDepartIds)
                        ));
                        
                        $nbRetires = count($courriersDepartActuels) - count($courriersDepartRestants);
                        if ($nbRetires > 0) {
                            $archive->setIdCourriersDepart($courriersDepartRestants);
                            $courriersDepartRetiresParCoffre += $nbRetires;
                            $modifie = true;
                        }
                    }

                    if ($modifie) {
                        $this->entityManager->persist($archive);
                        $archivesModifieesParCoffre++;
                    }
                }

                // Calculer le total d'Ã©lÃ©ments retirÃ©s pour ce coffre
                $totalElementsRetiresParCoffre = $courriersRetiresParCoffre + $transmissionsRetireesParCoffre + $courriersDepartRetiresParCoffre;

                // Mettre Ã  jour le nombre de places actuelles du coffre
                $nouveauNombrePlaces = max(0, $ancienNombrePlaces - $totalElementsRetiresParCoffre);
                $coffre->setNombrePlaceActuelle($nouveauNombrePlaces);
                $this->entityManager->persist($coffre);

                // Ajouter les dÃ©tails de ce coffre
                $coffresDetails[] = [
                    'coffreId' => $coffre->getId(),
                    'coffreNom' => $coffre->getNom(),
                    'salleId' => $coffre->getIdSalle(),
                    'ancienNombrePlaces' => $ancienNombrePlaces,
                    'nouveauNombrePlaces' => $nouveauNombrePlaces,
                    'placesDisponibles' => $coffre->getPlacesDisponibles(),
                    'tailleMaximale' => $coffre->getTailleMaximale(),
                    'isPlein' => $coffre->isPlein(),
                ];

                // Ajouter aux totaux globaux
                $archivesModifiees += $archivesModifieesParCoffre;
                $courriersRetires += $courriersRetiresParCoffre;
                $transmissionsRetirees += $transmissionsRetireesParCoffre;
                $courriersDepartRetires += $courriersDepartRetiresParCoffre;
            }

            // Calculer le total d'Ã©lÃ©ments retirÃ©s
            $totalElementsRetires = $courriersRetires + $transmissionsRetirees + $courriersDepartRetires;

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
                'message' => $totalElementsRetires . ' élément(s) retiré(s) de ' . count($coffresDetails) . ' coffre(s) avec succès.',
                'data' => [
                    'nombreCoffresTraites' => count($coffresDetails),
                    'archivesModifiees' => $archivesModifiees,
                    'courriersRetires' => $courriersRetires,
                    'transmissionsRetirees' => $transmissionsRetirees,
                    'courriersDepartRetires' => $courriersDepartRetires,
                    'totalElementsRetires' => $totalElementsRetires,
                    'fichierJustificatif' => $newFilename,
                    'retireParUtilisateur' => $userInfo,
                    'dateOperation' => (new \DateTime())->format('Y-m-d H:i:s'),
                    'coffresDetails' => $coffresDetails,
                ]
            ], 200);

        } catch (\Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => 'Une erreur est survenue lors du retrait des courriers.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
