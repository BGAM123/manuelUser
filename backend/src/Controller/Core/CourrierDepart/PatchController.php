<?php

namespace App\Controller\Core\CourrierDepart;

use App\Entity\Cour\CourrierDepart;
use App\Entity\Cour\PieceJointe;
use App\Repository\Cour\CourrierDepartRepository;
use App\Repository\Cour\CourrierInterneRepository;
use App\Repository\Cour\PieceJointeRepository;
use App\Repository\Cour\CourrierRepository;
use App\Repository\Core\UserRepository;
use App\Repository\Core\CorrespondantRepository;
use App\Service\Core\CrudService;
use App\Service\Core\FileService;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "CourrierDepart")]
class PatchController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FileService $fileService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private CourrierDepartRepository $courrierDepartRepository,
        private PieceJointeRepository $pieceJointeRepository,
        private CourrierRepository $courrierRepository,
        private CourrierInterneRepository $courrierInterneRepository,
        private UserRepository $userRepository,
        private CorrespondantRepository $correspondantRepository,
        private UserActionLoggerService $actionLogger,
    ) {}

    #[Route('/core/courrier-depart/{id<\d+>}', name: 'app_core_courrier_depart_patch', methods: ['POST'])]
    #[OA\Post(
        path: '/core/courrier-depart/{id}',
        summary: 'Modifier un courrier de départ avec document et pièces jointes',
        description: 'Met à jour les attributs d\'un courrier de départ existant, gère le document principal et les pièces jointes.',
        tags: ['CourrierDepart'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant du courrier de départ',
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'idCourrier', type: 'integer', example: 1),
                        new OA\Property(property: 'idCourrierInterne', type: 'integer', example: 1),
                        new OA\Property(property: 'dateSignature', type: 'string', format: 'date', example: '2025-02-14'),
                        new OA\Property(property: 'typeCourrier', type: 'string', example: 'Lettre officielle'),
                        new OA\Property(property: 'commentaire', type: 'string', example: 'Courrier urgent'),
                        new OA\Property(property: 'classeCourrier', type: 'string', example: 'Urgent', description: 'Classe du courrier (ex: Urgent, Normal, Confidentiel)'),
                        new OA\Property(property: 'categorie', type: 'string', example: 'Administrative', description: 'Catégorie du courrier de départ'),
                        new OA\Property(property: 'idSignataire', type: 'integer', example: 2),
                        new OA\Property(property: 'destinataire', type: 'integer', example: 5),
                        new OA\Property(property: 'numeroReference', type: 'string', example: 'CD-2025-001'),
                        new OA\Property(property: 'numeroActe', type: 'string', example: 'ACTE-2025-001', description: 'Numéro d\'acte du courrier de départ (optionnel)'),
                        new OA\Property(property: 'email', type: 'string', example: 'contact@example.com', description: 'Adresse email du destinataire'),
                        new OA\Property(property: 'numeroTelephone', type: 'string', example: '+237 6 XX XX XX XX', description: 'Numéro de téléphone du destinataire'),
                        new OA\Property(property: 'nombrePieceJointe', type: 'integer', example: 2, description: 'Nombre de pièces jointes (simple champ entré par l\'utilisateur, aucune vérification)'),
                        new OA\Property(
                            property: 'provenancesCopie',
                            type: 'string',
                            example: '1,2,3',
                            description: 'IDs des correspondants en copie séparés par virgules'
                        ),
                        
                        // ðŸ“‚ FICHIERS
                        new OA\Property(property: 'document', type: 'string', format: 'binary', description: 'Nouveau document principal (remplace l\'ancien si fourni)'),
                        new OA\Property(
                            property: 'piecesJointes[]',
                            type: 'array',
                            items: new OA\Items(type: 'string', format: 'binary'),
                            description: 'Nouvelles pièces jointes à ajouter'
                        ),
                        new OA\Property(
                            property: 'intitulesPiecesJointes',
                            type: 'string',
                            example: '["Rapport financier", "Justificatif", "Annexe"]',
                            description: 'Intitulés des nouvelles pièces jointes. Formats acceptés: JSON array, séparation par retour à la ligne, par "|||" ou par ";"'
                        ),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Courrier de départ mis à jour avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Courrier de départ mis à jour avec succès.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'numeroReference', type: 'string', example: 'CD-2025-001'),
                                new OA\Property(property: 'classeCourrier', type: 'string', example: 'Urgent'),
                                new OA\Property(property: 'categorie', type: 'string', example: 'Administrative'),
                                new OA\Property(property: 'document', type: 'string', example: '/uploads/courrier_depart/document/65ff44c4a8b1f.pdf'),
                                new OA\Property(property: 'nombrePieceJointe', type: 'integer', example: 2),
                                new OA\Property(property: 'provenancesCopie', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3]),
                                new OA\Property(property: 'piecesJointesAdded', type: 'integer', example: 2),
                                new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2025-02-14 10:30:00'),
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Courrier de départ non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function update(Request $request, int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'PatchCourrierDepart');

        $courrierDepart = $this->courrierDepartRepository->find($id);

        if (!$courrierDepart) {
            return $this->json(['code' => 404, 'message' => 'Courrier de départ non trouvé.'], 404);
        }

        // Récupérer les données du formulaire et les fichiers
        $data = $request->request->all();

        if (empty($data) && !$request->files->count()) {
            return $this->json(['code' => 400, 'message' => 'Aucune donnée à mettre à jour.'], 400);
        }

        $oldDocument = $courrierDepart->getDocument();

        try {
            // Gestion des relations
            if (isset($data['idCourrier'])) {
                if ($data['idCourrier'] === null || $data['idCourrier'] === '') {
                    $courrierDepart->setIdCourrier(null);
                } else {
                    $courrier = $this->courrierRepository->find($data['idCourrier']);
                    if ($courrier) {
                        $courrierDepart->setIdCourrier($courrier);
                    }
                }
                unset($data['idCourrier']);
            }

            if (isset($data['idCourrierInterne'])) {
                if ($data['idCourrierInterne'] === null || $data['idCourrierInterne'] === '') {
                    $courrierDepart->setIdCourrierInterne(null);
                } else {
                    $courrierInterne = $this->courrierInterneRepository->find($data['idCourrierInterne']);
                    if ($courrierInterne) {
                        $courrierDepart->setIdCourrierInterne($courrierInterne);
                    }
                }
                unset($data['idCourrierInterne']);
            }

            if (isset($data['idSignataire'])) {
                if ($data['idSignataire'] === null || $data['idSignataire'] === '') {
                    $courrierDepart->setIdSignataire(null);
                } else {
                    $signataire = $this->userRepository->find($data['idSignataire']);
                    if ($signataire) {
                        $courrierDepart->setIdSignataire($signataire);
                    }
                }
                unset($data['idSignataire']);
            }

            if (isset($data['destinataire'])) {
                if ($data['destinataire'] === null || $data['destinataire'] === '') {
                    $courrierDepart->setDestinataire(null);
                } else {
                    $destinataire = $this->correspondantRepository->find($data['destinataire']);
                    if ($destinataire) {
                        $courrierDepart->setDestinataire($destinataire);
                    }
                }
                unset($data['destinataire']);
            }

            // ðŸ“‚ Gestion du document principal
            if ($request->files->get('document')) {
                // Supprimer l'ancien document si il existe
                if ($oldDocument) {
                    $oldFilePath = $this->getParameter('kernel.project_dir') . '/public' . $oldDocument;
                    if (file_exists($oldFilePath)) {
                        @unlink($oldFilePath);
                    }
                }

                // Upload du nouveau document
                $filePath = $this->fileService->uploadFile(
                    $this->getParameter('app_uploads_courrier_depart_directory'),
                    $request->files->get('document')
                );
                if ($filePath) {
                    $data['document'] = $this->getParameter('app_uploads_courrier_depart') . $filePath;
                }
            }

            // âœ… Gestion de provenancesCopie (format: "1,2,3" â†’ [1, 2, 3])
            if (isset($data['provenancesCopie'])) {
                if (!empty($data['provenancesCopie']) && is_string($data['provenancesCopie'])) {
                    $ids = array_map('intval', array_filter(explode(',', $data['provenancesCopie'])));
                    $data['provenancesCopie'] = !empty($ids) ? $ids : null;
                } else {
                    $data['provenancesCopie'] = null;
                }
            }

            // âœ… Conversion du nombrePieceJointe en entier
            if (isset($data['nombrePieceJointe'])) {
                $data['nombrePieceJointe'] = (int)$data['nombrePieceJointe'];
            }

            // ðŸ“Ž Gestion des piÃ¨ces jointes - Upload simple comme Ã  la crÃ©ation
            $uploadedCount = 0;
            
            // RÃ©cupÃ©rer les intitulÃ©s (optionnel)
            $intitulesInput = $request->request->get('intitulesPiecesJointes', '');
            $intitules = $this->parseIntitules($intitulesInput);
            
            if (!empty($request->files->get('piecesJointes'))) {
                foreach ($request->files->get('piecesJointes') as $index => $file) {
                    $filePath = $this->fileService->uploadFile(
                        $this->getParameter('app_uploads_courrier_depart_piece_directory'),
                        $file
                    );

                    if ($filePath) {
                        $piece = new PieceJointe();
                        $piece->setNom($file->getClientOriginalName());
                        $piece->setIntitule($intitules[$index] ?? null);
                        $piece->setChemin($this->getParameter('app_uploads_courrier_depart_piece') . $filePath);
                        $piece->setType($file->getClientMimeType());
                        $piece->setIdParent($courrierDepart->getId());
                        $piece->setTypeParent('CourrierDepart');

                        $this->crudService->postEntity($piece, []);
                        $uploadedCount++;
                    }
                }
            }

            // Exclusion des champs systÃ¨me
            $data = $this->functionService->excludeFields($data, [
                'createdAt', 'updatedAt'
            ]);
            
            $courrierDepart = $this->crudService->patchEntity($courrierDepart, $data);

            $responseData = [
                'id' => $courrierDepart->getId(),
                'numeroReference' => $courrierDepart->getNumeroReference(),
                'numeroActe' => $courrierDepart->getNumeroActe(),
                'classeCourrier' => $courrierDepart->getClasseCourrier(),
                'categorie' => $courrierDepart->getCategorie(),
                'document' => $courrierDepart->getDocument(),
                'email' => $courrierDepart->getEmail(),
                'numeroTelephone' => $courrierDepart->getNumeroTelephone(),
                'nombrePieceJointe' => $courrierDepart->getNombrePieceJointe(),
                'provenancesCopie' => $courrierDepart->getProvenancesCopie(), // âœ…
                'piecesJointesAdded' => $uploadedCount,
                'updatedAt' => $courrierDepart->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ];

            // Logger la modification avec TOUTES les donnÃ©es
            $this->actionLogger->logUpdate(
                'CourrierDepart',
                $courrierDepart->getId(),
                'Modification d\'un courrier départ',
                [
                    'courrier' => $responseData,
                    'changes' => $request->request->all(),
                    'document_changed' => $request->files->get('document') ? true : false,
                    'old_document' => $oldDocument
                ]
            );

            return $this->json([
                'code' => 200,
                'message' => 'Courrier de départ mis à jour avec succès.',
                'data' => $responseData
            ], 200);
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Analyse une chaÃ®ne d'intitulÃ©s dans diffÃ©rents formats supportÃ©s
     * 
     * Formats acceptÃ©s :
     * - JSON array: ["Titre1", "Titre2"]
     * - SÃ©parÃ©s par retour Ã  la ligne: "Titre1\nTitre2"
     * - SÃ©parÃ©s par |||: "Titre1|||Titre2"
     * - SÃ©parÃ©s par point-virgule: "Titre1;Titre2"
     */
    private function parseIntitules(string $input): array
    {
        if (empty(trim($input))) {
            return [];
        }

        // Tenter de dÃ©coder comme JSON
        $decoded = json_decode($input, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return array_map('trim', $decoded);
        }

        // VÃ©rifier sÃ©parateur |||
        if (strpos($input, '|||') !== false) {
            return array_map('trim', explode('|||', $input));
        }

        // VÃ©rifier sÃ©parateur point-virgule
        if (strpos($input, ';') !== false) {
            return array_map('trim', explode(';', $input));
        }

        // Par dÃ©faut, sÃ©parer par retour Ã  la ligne
        return array_map('trim', preg_split('/\r\n|\r|\n/', $input));
    }
}
