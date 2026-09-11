<?php

namespace App\Controller\Core\BordereauTransmission;

use App\Entity\Core\BordereauTransmission;
use App\Service\Core\CrudService;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "BordereauTransmission")]
class PatchController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private EntityManagerInterface $entityManager,
    ) {}

    #[Route('/core/bordereau-transmission', name: 'app_core_bordereau_transmission_patch', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/core/bordereau-transmission',
        summary: 'Mettre à jour plusieurs bordereaux de transmission',
        tags: ['BordereauTransmission'],
        description: "Met à jour plusieurs bordereaux de transmission avec leur correspondant et courriers associés.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['bordereauData'],
                properties: [
                    new OA\Property(
                        property: 'bordereauData',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            required: ['id', 'correspondantId', 'courrierIds'],
                            properties: [
                                new OA\Property(
                                    property: 'id',
                                    type: 'integer',
                                    example: 1,
                                    description: 'ID du bordereau à mettre à jour'
                                ),
                                new OA\Property(
                                    property: 'correspondantId',
                                    type: 'integer',
                                    example: 1,
                                    description: 'ID du correspondant destinataire'
                                ),
                                new OA\Property(
                                    property: 'courrierIds',
                                    type: 'array',
                                    items: new OA\Items(type: 'integer'),
                                    example: [5, 8, 12],
                                    description: 'Liste des IDs des courriers pour ce bordereau'
                                ),
                                new OA\Property(
                                    property: 'numeroReference',
                                    type: 'string',
                                    example: 'BT-2025-00001',
                                    description: 'Numéro de référence (optionnel)'
                                ),
                                new OA\Property(
                                    property: 'nombrePieceJointe',
                                    type: 'integer',
                                    example: 5,
                                    description: 'Nombre de pièces jointes (optionnel)'
                                ),
                            ]
                        ),
                        example: [
                            [
                                'id' => 1,
                                'correspondantId' => 1,
                                'courrierIds' => [5, 8, 12]
                            ],
                            [
                                'id' => 2,
                                'correspondantId' => 2,
                                'courrierIds' => [3, 7]
                            ]
                        ]
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Bordereaux de transmission mis à jour avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Bordereaux de transmission mis à jour avec succès.'),
                        new OA\Property(
                            property: 'data',
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
                                    new OA\Property(property: 'nombrePieceJointe', type: 'integer', example: 3),
                                    new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2025-12-08T10:30:00+00:00'),
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Requête invalide - données incorrectes.'),
            new OA\Response(response: 401, description: 'Accès non autorisé - authentification requise.'),
            new OA\Response(response: 403, description: 'Accès interdit - permissions insuffisantes.'),
            new OA\Response(response: 404, description: 'Un ou plusieurs bordereaux non trouvés.')
        ]
    )]
    public function update(Request $request): Response
    {
        // VÃ©rification des permissions
        $this->accessChecker->checker(
            $this->getUser(), 
            $this->isGranted('ROLE_ADMIN'), 
            'PatchBordereauTransmission'
        );

        // RÃ©cupÃ©ration et validation des donnÃ©es
        $data = json_decode($request->getContent(), true);
        
        if (!$data || !isset($data['bordereauData'])) {
            return $this->json([
                'code' => 400,
                'message' => 'Le champ "bordereauData" est requis et doit contenir un tableau de bordereaux.'
            ], 400);
        }

        $bordereauData = $data['bordereauData'];

        if (!is_array($bordereauData) || empty($bordereauData)) {
            return $this->json([
                'code' => 400,
                'message' => 'Le champ "bordereauData" doit être un tableau non vide.'
            ], 400);
        }

        $updatedBordereaux = [];
        $errors = [];

        try {
            foreach ($bordereauData as $index => $bordereauItem) {
                // Validation des champs requis
                if (!isset($bordereauItem['id'])) {
                    $errors[] = "Bordereau #$index : le champ 'id' est requis.";
                    continue;
                }

                if (!isset($bordereauItem['correspondantId'])) {
                    $errors[] = "Bordereau #$index : le champ 'correspondantId' est requis.";
                    continue;
                }

                if (!isset($bordereauItem['courrierIds']) || !is_array($bordereauItem['courrierIds'])) {
                    $errors[] = "Bordereau #$index : le champ 'courrierIds' est requis et doit être un tableau.";
                    continue;
                }

                // Récupération du bordereau existant
                $bordereau = $this->entityManager->getRepository(BordereauTransmission::class)->find($bordereauItem['id']);

                if (!$bordereau) {
                    $errors[] = "Bordereau #{$bordereauItem['id']} non trouvé.";
                    continue;
                }

                // Mise à jour des champs
                $bordereau->setCorrespondantId($bordereauItem['correspondantId']);
                $bordereau->setCourrierIds($bordereauItem['courrierIds']);

                // Champs optionnels
                if (isset($bordereauItem['numeroReference'])) {
                    $bordereau->setNumeroReference($bordereauItem['numeroReference']);
                }

                if (isset($bordereauItem['nombrePieceJointe'])) {
                    $bordereau->setNombrePieceJointe($bordereauItem['nombrePieceJointe']);
                }

                $this->entityManager->persist($bordereau);

                $updatedBordereaux[] = [
                    'id' => $bordereau->getId(),
                    'correspondantId' => $bordereau->getCorrespondantId(),
                    'courrierIds' => $bordereau->getCourrierIds(),
                    'numeroReference' => $bordereau->getNumeroReference(),
                    'nombrePieceJointe' => $bordereau->getNombrePieceJointe(),
                    'updatedAt' => $bordereau->getUpdatedAt()?->format('c'),
                ];
            }

            // Si des erreurs ont Ã©tÃ© dÃ©tectÃ©es
            if (!empty($errors)) {
                return $this->json([
                    'code' => 400,
                    'message' => 'Certains bordereaux n\'ont pas pu être mis à jour.',
                    'errors' => $errors,
                    'updated' => $updatedBordereaux
                ], 400);
            }

            // Sauvegarde des modifications
            $this->entityManager->flush();

            return $this->json([
                'code' => 200,
                'message' => count($updatedBordereaux) . ' bordereau(x) de transmission mis à jour avec succès.',
                'data' => $updatedBordereaux
            ], 200);

        } catch (\Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => 'Une erreur est survenue lors de la mise à jour des bordereaux.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
