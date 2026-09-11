<?php

namespace App\Controller\Core\BordereauTransmission;

use App\Entity\Core\BordereauTransmission;
use App\Service\Core\CrudService;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "BordereauTransmission")]
class PostController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
    ) {}

    #[Route('/core/bordereau-transmission', name: 'app_core_bordereau_transmission_post', methods: ['POST'])]
    #[OA\Post(
        path: '/core/bordereau-transmission',
        summary: 'Créer un ou plusieurs bordereaux de transmission',
        tags: ['BordereauTransmission'],
        description: "Crée un ou plusieurs bordereaux de transmission. Chaque bordereau est associé à un correspondant et contient une liste de courriers.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['bordereauData', 'numeroReference', 'nombrePieceJointe'],
                properties: [
                    new OA\Property(
                        property: 'bordereauData',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            required: ['correspondantId', 'courrierIds'],
                            properties: [
                                new OA\Property(property: 'correspondantId', type: 'integer', example: 1),
                                new OA\Property(
                                    property: 'courrierIds',
                                    type: 'array',
                                    items: new OA\Items(type: 'integer'),
                                    example: [5, 8, 12]
                                )
                            ]
                        ),
                        example: [
                            ['correspondantId' => 1, 'courrierIds' => [5, 8, 12]],
                            ['correspondantId' => 2, 'courrierIds' => [3, 7]]
                        ],
                        description: 'Liste des bordereaux à créer avec leur correspondant et courriers'
                    ),
                    new OA\Property(
                        property: 'numeroReference', 
                        type: 'string', 
                        example: 'BT-2025-00001',
                        description: 'Numéro de référence de base (sera incrémenté pour chaque bordereau)'
                    ),
                    new OA\Property(
                        property: 'nombrePieceJointe', 
                        type: 'integer', 
                        example: 5,
                        description: 'Nombre total de pièces jointes'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Bordereau(x) de transmission crée(s) avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'bordereaux',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'correspondantId', type: 'integer', example: 1),
                                    new OA\Property(
                                        property: 'courrierIds',
                                        type: 'array',
                                        items: new OA\Items(type: 'integer'),
                                        example: [5, 8, 12]
                                    ),
                                    new OA\Property(property: 'numeroReference', type: 'string', example: 'BT-2025-00001'),
                                    new OA\Property(property: 'nombrePieceJointe', type: 'integer', example: 5),
                                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-12-08T10:30:00+00:00'),
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Requête invalide - données manquantes ou incorrectes.'),
            new OA\Response(response: 401, description: 'Accès non autorisé - authentification requise.'),
            new OA\Response(response: 403, description: 'Accès interdit - permissions insuffisantes.')
        ]
    )]
    public function create(Request $request): Response
    {
        // Vérification des permissions
        $this->accessChecker->checker(
            $this->getUser(), 
            $this->isGranted('ROLE_ADMIN'), 
            'PostBordereauTransmission'
        );

        // RÃ©cupÃ©ration et validation des donnÃ©es
        $data = json_decode($request->getContent(), true);
        
        // Validation basique des donnÃ©es
        if (!$data) {
            return $this->json([
                'code' => 400,
                'message' => 'Données JSON invalides.'
            ], 400);
        }

        // Validation : bordereauData doit Ãªtre un tableau
        if (!isset($data['bordereauData']) || !is_array($data['bordereauData'])) {
            return $this->json([
                'code' => 400,
                'message' => 'Le champ "bordereauData" est requis et doit être un tableau.'
            ], 400);
        }

        // Validation : bordereauData ne doit pas Ãªtre vide
        if (empty($data['bordereauData'])) {
            return $this->json([
                'code' => 400,
                'message' => 'Le champ "bordereauData" ne peut pas être vide.'
            ], 400);
        }

        // Validation : numeroReference requis
        if (empty($data['numeroReference'])) {
            return $this->json([
                'code' => 400,
                'message' => 'Le champ "numeroReference" est requis.'
            ], 400);
        }

        // Validation : nombrePieceJointe requis et doit Ãªtre un entier
        if (!isset($data['nombrePieceJointe']) || !is_numeric($data['nombrePieceJointe'])) {
            return $this->json([
                'code' => 400,
                'message' => 'Le champ "nombrePieceJointe" est requis et doit être un nombre entier.'
            ], 400);
        }

        // Validation de chaque Ã©lÃ©ment de bordereauData
        foreach ($data['bordereauData'] as $index => $item) {
            if (!isset($item['correspondantId']) || !is_numeric($item['correspondantId'])) {
                return $this->json([
                    'code' => 400,
                    'message' => "L'élément $index de bordereauData doit contenir un 'correspondantId' valide."
                ], 400);
            }

            if (!isset($item['courrierIds']) || !is_array($item['courrierIds']) || empty($item['courrierIds'])) {
                return $this->json([
                    'code' => 400,
                    'message' => "L'élément $index de bordereauData doit contenir un tableau 'courrierIds' non vide."
                ], 400);
            }
        }

        try {
            $createdBordereaux = [];
            $baseNumeroReference = $data['numeroReference'];

            // CrÃ©er un bordereau pour chaque correspondant
            foreach ($data['bordereauData'] as $index => $bordereauItem) {
                // Générer un numéro de référence unique pour chaque bordereau
                $numeroReference = $index === 0 
                    ? $baseNumeroReference 
                    : $baseNumeroReference . '-' . ($index + 1);

                // CrÃ©ation de l'entitÃ© BordereauTransmission
                $bordereau = new BordereauTransmission();
                
                // PrÃ©parer les donnÃ©es pour ce bordereau
                $bordereauData = [
                    'correspondantId' => $bordereauItem['correspondantId'],
                    'courrierIds' => $bordereauItem['courrierIds'],
                    'numeroReference' => $numeroReference,
                    'nombrePieceJointe' => $data['nombrePieceJointe'],
                ];

                // Utilisation du CrudService pour crÃ©er l'entitÃ©
                $bordereau = $this->crudService->postEntity($bordereau, $bordereauData);

                $createdBordereaux[] = [
                    'id' => $bordereau->getId(),
                    'correspondantId' => $bordereau->getCorrespondantId(),
                    'courrierIds' => $bordereau->getCourrierIds(),
                    'numeroReference' => $bordereau->getNumeroReference(),
                    'nombrePieceJointe' => $bordereau->getNombrePieceJointe(),
                    'createdAt' => $bordereau->getCreatedAt()?->format('c'),
                ];
            }

            // Retour de la rÃ©ponse avec tous les bordereaux crÃ©Ã©s
            return $this->json([
                'code' => 201,
                'message' => count($createdBordereaux) . ' bordereau(x) de transmission crée(s) avec succès.',
                'data' => [
                    'bordereaux' => $createdBordereaux
                ]
            ], 201);

        } catch (\Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => 'Une erreur est survenue lors de la création des bordereaux.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
