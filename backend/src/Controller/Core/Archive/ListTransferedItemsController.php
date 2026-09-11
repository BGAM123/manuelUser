<?php

namespace App\Controller\Core\Archive;

use App\Repository\Cour\CourrierRepository;
use App\Repository\Cour\TransmissionRepository;
use App\Repository\Cour\CourrierDepartRepository;
use App\Repository\Core\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Archive")]
class ListTransferedItemsController extends AbstractController
{
    public function __construct(
        private CourrierRepository $courrierRepository,
        private TransmissionRepository $transmissionRepository,
        private CourrierDepartRepository $courrierDepartRepository,
        private UserRepository $userRepository,
    ) {}

    #[Route('/core/archive/transfered-items', name: 'app_core_archive_transfered_items', methods: ['GET'])]
    #[OA\Get(
        path: '/core/archive/transfered-items',
        summary: 'Lister tous les éléments transférés (vidés) avec traçabilité',
        tags: ['Archive'],
        description: "Liste tous les courriers, transmissions et courriers départ qui ont isArchive=true et statutArchive='transféré' (c'est-à-dire ceux qui ont été vidés des coffres).
        
        **TRAÇABILITÉ** : Pour chaque élément vidé, les informations suivantes sont retournées :
        - `viderPar` : Qui a vidé (utilisateur), quand (date), de quelle salle et de quel coffre (traçabilité complète)
        - `salle` et `coffre` : Emplacement actuel (null pour les éléments transférés)
        
        Ceci permet de tracer l'historique complet de tous les éléments archivés puis transférés.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'page',
                in: 'query',
                required: false,
                description: 'Numéro de page (pagination)',
                schema: new OA\Schema(type: 'integer', example: 1, default: 1)
            ),
            new OA\Parameter(
                name: 'limit',
                in: 'query',
                required: false,
                description: 'Nombre d\'éléments par page',
                schema: new OA\Schema(type: 'integer', example: 50, default: 50)
            ),
            new OA\Parameter(
                name: 'type',
                in: 'query',
                required: false,
                description: 'Filtrer par type (courrier, transmission, courrierDepart)',
                schema: new OA\Schema(type: 'string', enum: ['courrier', 'transmission', 'courrierDepart'])
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des éléments transférés récupérée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Liste des éléments transférés récupérée avec succès.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'total', type: 'integer', example: 150, description: 'Nombre total d\'éléments'),
                                new OA\Property(property: 'totalCourriers', type: 'integer', example: 50),
                                new OA\Property(property: 'totalTransmissions', type: 'integer', example: 60),
                                new OA\Property(property: 'totalCourriersDepart', type: 'integer', example: 40),
                                new OA\Property(property: 'page', type: 'integer', example: 1),
                                new OA\Property(property: 'limit', type: 'integer', example: 50),
                                new OA\Property(property: 'totalPages', type: 'integer', example: 3),
                                new OA\Property(
                                    property: 'items',
                                    type: 'array',
                                    items: new OA\Items(
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                            new OA\Property(property: 'type', type: 'string', example: 'courrier', description: 'Type d\'élément: courrier, transmission ou courrierDepart'),
                                            new OA\Property(property: 'numero', type: 'string', example: 'C-2024-001'),
                                            new OA\Property(property: 'objet', type: 'string', example: 'Demande de renseignements'),
                                            new OA\Property(property: 'document', type: 'string', example: '/uploads/courrier/document/file.pdf', nullable: true),
                                            new OA\Property(property: 'dateCreation', type: 'string', format: 'date-time', example: '2024-01-15 10:30:00'),
                                            new OA\Property(property: 'isArchive', type: 'boolean', example: true),
                                            new OA\Property(property: 'statutArchive', type: 'string', example: 'transféré'),
                                            new OA\Property(property: 'dateArchivage', type: 'string', format: 'date-time', example: '2024-03-20 14:00:00', nullable: true),
                                            new OA\Property(
                                                property: 'viderPar',
                                                type: 'object',
                                                nullable: true,
                                                description: 'Informations sur qui a vidé le coffre et d\'où',
                                                properties: [
                                                    new OA\Property(property: 'id', type: 'integer', example: 5),
                                                    new OA\Property(property: 'fullname', type: 'string', example: 'Jean Dupont'),
                                                    new OA\Property(property: 'nom', type: 'string', example: 'Dupont'),
                                                    new OA\Property(property: 'prenom', type: 'string', example: 'Jean'),
                                                    new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@example.com'),
                                                    new OA\Property(
                                                        property: 'service',
                                                        type: 'object',
                                                        nullable: true,
                                                        properties: [
                                                            new OA\Property(property: 'id', type: 'integer', example: 10),
                                                            new OA\Property(property: 'nom', type: 'string', example: 'Service Archivage'),
                                                        ]
                                                    ),
                                                    new OA\Property(property: 'date', type: 'string', example: '2024-03-20 14:00:00'),
                                                    new OA\Property(
                                                        property: 'salle',
                                                        type: 'object',
                                                        nullable: true,
                                                        description: 'Salle d\'où l\'élément a été vidé',
                                                        properties: [
                                                            new OA\Property(property: 'id', type: 'integer', example: 2),
                                                            new OA\Property(property: 'nom', type: 'string', example: 'Salle B'),
                                                        ]
                                                    ),
                                                    new OA\Property(
                                                        property: 'coffre',
                                                        type: 'object',
                                                        nullable: true,
                                                        description: 'Coffre d\'où l\'élément a été vidé',
                                                        properties: [
                                                            new OA\Property(property: 'id', type: 'integer', example: 10),
                                                            new OA\Property(property: 'nom', type: 'string', example: 'Coffre B2'),
                                                        ]
                                                    ),
                                                    new OA\Property(property: 'fichierJustificatif', type: 'string', example: 'vidage-coffre-20241209-153045.pdf', nullable: true),
                                                ]
                                            ),
                                        ]
                                    )
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Accès non autorisé - authentification requise.'),
            new OA\Response(response: 403, description: 'Accès interdit - permissions insuffisantes.')
        ]
    )]
    public function listTransferedItems(Request $request): JsonResponse
    {
        // Paramètres de pagination
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(200, (int) $request->query->get('limit', 50)));
        $type = $request->query->get('type');

        $offset = ($page - 1) * $limit;

        try {
            $items = [];
            $totalCourriers = 0;
            $totalTransmissions = 0;
            $totalCourriersDepart = 0;

            // Récupérer les courriers transférés
            if (!$type || $type === 'courrier') {
                $courriers = $this->courrierRepository->createQueryBuilder('c')
                    ->where('c.isArchive = :isArchive')
                    ->andWhere('c.statutArchive = :statutArchive')
                    ->andWhere('c.isDelete = :isDelete')
                    ->setParameter('isArchive', true)
                    ->setParameter('statutArchive', 'transfÃ©rÃ©')
                    ->setParameter('isDelete', false)
                    ->orderBy('c.createdAt', 'DESC')
                    ->getQuery()
                    ->getResult();

                $totalCourriers = count($courriers);

                foreach ($courriers as $courrier) {
                    $items[] = [
                        'id' => $courrier->getId(),
                        'type' => 'courrier',
                        'numero' => $courrier->getNumero() ?? 'N/A',
                        'objet' => $courrier->getObjet() ?? 'Sans objet',
                        'document' => $courrier->getDocument() ?? null,
                        'dateCreation' => $courrier->getCreatedAt()?->format('Y-m-d H:i:s'),
                        'isArchive' => $courrier->isArchive(),
                        'statutArchive' => $courrier->getStatutArchive(),
                        'dateArchivage' => $courrier->getUpdatedAt()?->format('Y-m-d H:i:s'),
                        'viderPar' => $this->enrichirViderPar($courrier->getViderPar()),
                    ];
                }
            }
            // Récupérer les transmissions transférées
            if (!$type || $type === 'transmission') {
                $transmissions = $this->transmissionRepository->createQueryBuilder('t')
                    ->where('t.isArchive = :isArchive')
                    ->andWhere('t.statutArchive = :statutArchive')
                    ->andWhere('t.isDelete = :isDelete')
                    ->setParameter('isArchive', true)
                    ->setParameter('statutArchive', 'transféré')
                    ->setParameter('isDelete', false)
                    ->orderBy('t.createdAt', 'DESC')
                    ->getQuery()
                    ->getResult();

                $totalTransmissions = count($transmissions);

                foreach ($transmissions as $transmission) {
                    // La transmission n'a pas de numéro propre, elle est liée à un courrier
                    $courrierLie = $transmission->getIdCourrier();
                    $numero = $courrierLie ? ($courrierLie->getNumero() ?? 'N/A') : 'N/A';
                    
                    $items[] = [
                        'id' => $transmission->getId(),
                        'type' => 'transmission',
                        'numero' => $numero,
                        'objet' => $courrierLie ? ($courrierLie->getObjet() ?? 'Sans objet') : 'Sans objet',
                        'statut' => $transmission->getStatut() ?? 'N/A',
                        'instruction' => $transmission->getInstruction() ?? null,
                        'document' => $courrierLie ? $courrierLie->getDocument() : null,
                        'dateCreation' => $transmission->getCreatedAt()?->format('Y-m-d H:i:s'),
                        'isArchive' => $transmission->isArchive(),
                        'statutArchive' => $transmission->getStatutArchive(),
                        'dateArchivage' => $transmission->getUpdatedAt()?->format('Y-m-d H:i:s'),
                        'viderPar' => $this->enrichirViderPar($transmission->getViderPar()),
                    ];
                }
            }

            // Récupérer les courriers départ transférés
            if (!$type || $type === 'courrierDepart') {
                $courriersDepart = $this->courrierDepartRepository->createQueryBuilder('cd')
                    ->where('cd.isArchive = :isArchive')
                    ->andWhere('cd.statutArchive = :statutArchive')
                    ->andWhere('cd.isDelete = :isDelete')
                    ->setParameter('isArchive', true)
                    ->setParameter('statutArchive', 'transfÃ©rÃ©')
                    ->setParameter('isDelete', false)
                    ->orderBy('cd.createdAt', 'DESC')
                    ->getQuery()
                    ->getResult();

                $totalCourriersDepart = count($courriersDepart);

                foreach ($courriersDepart as $courrierDepart) {
                    // Le courrier départ est aussi lié à un courrier
                    $courrierLie = $courrierDepart->getIdCourrier();
                    $numero = $courrierLie ? ($courrierLie->getNumero() ?? 'N/A') : 'N/A';
                    $destinataireObj = $courrierDepart->getDestinataire();
                    $destinataireNom = $destinataireObj ? $destinataireObj->getNom() : 'N/A';
                    
                    $items[] = [
                        'id' => $courrierDepart->getId(),
                        'type' => 'courrierDepart',
                        'numero' => $numero,
                        'numeroReference' => $courrierDepart->getNumeroReference() ?? 'N/A',
                        'objet' => $courrierLie ? ($courrierLie->getObjet() ?? 'Sans objet') : 'Sans objet',
                        'destinataire' => $destinataireNom,
                        'typeCourrier' => $courrierDepart->getTypeCourrier() ?? 'N/A',
                        'document' => $courrierDepart->getDocument() ?? null,
                        'dateCreation' => $courrierDepart->getCreatedAt()?->format('Y-m-d H:i:s'),
                        'isArchive' => $courrierDepart->isArchive(),
                        'statutArchive' => $courrierDepart->getStatutArchive(),
                        'dateArchivage' => $courrierDepart->getUpdatedAt()?->format('Y-m-d H:i:s'),
                        'viderPar' => $this->enrichirViderPar($courrierDepart->getViderPar()),
                    ];
                }
            }

            // Tri par date de crÃ©ation (plus rÃ©cent en premier)
            usort($items, function($a, $b) {
                return strtotime($b['dateCreation']) <=> strtotime($a['dateCreation']);
            });

            $total = count($items);
            
            // Appliquer la pagination
            $paginatedItems = array_slice($items, $offset, $limit);
            
            $totalPages = ceil($total / $limit);

            return $this->json([
                'code' => 200,
                'message' => 'Liste des éléments transférés récupérée avec succès.',
                'data' => [
                    'total' => $total,
                    'totalCourriers' => $totalCourriers,
                    'totalTransmissions' => $totalTransmissions,
                    'totalCourriersDepart' => $totalCourriersDepart,
                    'page' => $page,
                    'limit' => $limit,
                    'totalPages' => $totalPages,
                    'items' => $paginatedItems,
                ]
            ], 200);

        } catch (\Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => 'Une erreur est survenue lors de la récupération des éléments transférés.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enrichit les informations de viderPar avec les dÃ©tails complets de l'utilisateur
     * CONSERVE toutes les donnÃ©es existantes (salle, coffre, date, fichierJustificatif)
     * Transforme fichierJustificatif en chemin complet
     */
    private function enrichirViderPar(?array $viderPar): ?array
    {
        if (!$viderPar || !isset($viderPar['id'])) {
            return $viderPar;
        }

        // RÃ©cupÃ©rer l'utilisateur complet depuis la base de donnÃ©es
        $user = $this->userRepository->find($viderPar['id']);
        
        if (!$user) {
            return $viderPar; // Retourner les donnÃ©es de base si l'utilisateur n'existe plus
        }

        // CONSERVER toutes les donnÃ©es existantes et enrichir avec les informations utilisateur
        $enrichedData = $viderPar; // Garde salle, coffre, date, fichierJustificatif, etc.
        
        // Ajouter/remplacer les informations utilisateur
        $enrichedData['id'] = $user->getId();
        $enrichedData['fullname'] = $user->getFullName();
        $enrichedData['nom'] = $user->getLastName();
        $enrichedData['prenom'] = $user->getFirstName();
        $enrichedData['email'] = $user->getEmail();

        // Ajouter le service si disponible
        $service = $user->getIdService();
        if ($service) {
            $enrichedData['service'] = [
                'id' => $service->getId(),
                'nom' => $service->getNom(),
            ];
        } else {
            $enrichedData['service'] = null;
        }

        // Transformer le fichierJustificatif en chemin complet et ajouter le nom seul
        if (isset($enrichedData['fichierJustificatif']) && !empty($enrichedData['fichierJustificatif'])) {
            // Garder le nom du fichier dans un champ sÃ©parÃ©
            $nomFichier = $enrichedData['fichierJustificatif'];
            $enrichedData['fichierJustificatifNom'] = $nomFichier;
            
            // Transformer en chemin complet si nÃ©cessaire
            if (strpos($enrichedData['fichierJustificatif'], '/uploads/') !== 0) {
                $enrichedData['fichierJustificatif'] = '/uploads/courrier/document/' . $enrichedData['fichierJustificatif'];
            }
        }

        return $enrichedData;
    }
}
