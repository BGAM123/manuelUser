<?php

namespace App\Controller\Core;

use App\Service\UserActionLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Logs")]
class TestLogController extends AbstractController
{
    public function __construct(
        private UserActionLoggerService $actionLogger
    ) {}

    #[Route('/core/logs/test', name: 'app_core_logs_test', methods: ['POST'])]
    #[OA\Post(
        path: '/core/logs/test',
        summary: 'Créer des logs de test pour l\'utilisateur connecté',
        tags: ['Logs'],
        description: "Génère quelques logs de test pour vérifier que le système fonctionne.",
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Logs de test créés avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Logs de test créés'),
                        new OA\Property(property: 'count', type: 'integer', example: 5)
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié')
        ]
    )]
    public function createTestLogs(): JsonResponse
    {
        // Créer quelques logs de test
        $this->actionLogger->logCreate(
            'Courrier',
            123,
            'Création d\'un courrier test',
            ['objet' => 'Test de log', 'type' => 'Arrivée']
        );

        $this->actionLogger->logUpdate(
            'Courrier',
            123,
            'Modification du courrier test',
            ['champs_modifies' => ['statut', 'observation']]
        );

        $this->actionLogger->logView(
            'User',
            456,
            'Consultation de la liste des utilisateurs',
            ['total' => 50]
        );

        $this->actionLogger->logDownload(
            'Document',
            789,
            'TÃ©lÃ©chargement d\'un document',
            ['nom_fichier' => 'rapport.pdf', 'taille' => '2.5 MB']
        );

        $this->actionLogger->logLogin(
            'Connexion Ã  l\'application',
            ['device' => 'Web', 'browser' => 'Chrome']
        );

        return new JsonResponse([
            'message' => 'Logs de test créés avec succès',
            'count' => 5,
            'info' => 'Vous pouvez maintenant consulter vos logs via GET /core/logs'
        ]);
    }
}
