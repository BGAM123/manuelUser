<?php

namespace App\Controller\Core;

use App\Service\SmsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;

#[Route('/api/sms', name: 'api_sms_')]
class SmsController extends AbstractController
{
    private SmsService $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * RÃ©cupÃ¨re le solde de SMS restants
     */
    #[Route('/balance', name: 'balance', methods: ['GET'])]
    #[OA\Get(
        path: '/api/sms/balance',
        operationId: 'getSmsBalance',
        description: 'Récupère le nombre de SMS restants disponibles sur le compte',
        summary: 'Obtenir le solde de SMS',
        tags: ['SMS']
    )]
    #[OA\Response(
        response: 200,
        description: 'Solde récupéré avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'balance', type: 'integer', example: 1250, description: 'Nombre de SMS restants'),
                new OA\Property(property: 'message', type: 'string', example: 'Solde récupéré avec succès'),
                new OA\Property(
                    property: 'response',
                    type: 'object',
                    description: 'Réponse brute de l\'API SMS'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 500,
        description: 'Erreur lors de la récupération du solde',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'balance', type: 'integer', nullable: true, example: null),
                new OA\Property(property: 'message', type: 'string', example: 'Erreur technique lors de la récupération du solde'),
                new OA\Property(
                    property: 'response',
                    type: 'object',
                    nullable: true
                )
            ]
        )
    )]
    public function getBalance(): JsonResponse
    {
        try {
            $result = $this->smsService->getSmsBalance();

            $statusCode = $result['success'] ? 200 : 500;

            return $this->json([
                'success' => $result['success'],
                'balance' => $result['balance'],
                'message' => $result['message'],
                'response' => $result['response']
            ], $statusCode);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'balance' => null,
                'message' => 'Erreur lors de la récupération du solde: ' . $e->getMessage(),
                'response' => null
            ], 500);
        }
    }

    /**
     * VÃ©rifie la configuration SMS
     */
    #[Route('/config/check', name: 'config_check', methods: ['GET'])]
    #[OA\Get(
        path: '/api/sms/config/check',
        operationId: 'checkSmsConfig',
        description: 'Vérifie si la configuration SMS est complète et valide',
        summary: 'Vérifier la configuration SMS',
        tags: ['SMS']
    )]
    #[OA\Response(
        response: 200,
        description: 'État de la configuration',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'configured', type: 'boolean', example: true),
                new OA\Property(property: 'api_url', type: 'string', example: 'https://devcodesms.com/developpeur/Send_sms_dev'),
                new OA\Property(property: 'sender', type: 'string', example: 'MINEPIA'),
                new OA\Property(property: 'api_key_preview', type: 'string', example: 'GuESKhMezK...', description: 'Aperçu de la clé API (tronquée pour sécurité)')
            ]
        )
    )]
    public function checkConfiguration(): JsonResponse
    {
        return $this->json([
            'configured' => $this->smsService->isConfigured(),
            'api_url' => $this->smsService->getApiUrl(),
            'sender' => $this->smsService->getSender(),
            'api_key_preview' => $this->smsService->getApiKey()
        ]);
    }

    /**
     * Teste la connexion Ã  l'API SMS
     */
    #[Route('/test-connection', name: 'test_connection', methods: ['GET'])]
    #[OA\Get(
        path: '/api/sms/test-connection',
        operationId: 'testSmsConnection',
        description: 'Effectue un test de connexion avec l\'API SMS',
        summary: 'Tester la connexion SMS',
        tags: ['SMS']
    )]
    #[OA\Response(
        response: 200,
        description: 'RÃ©sultat du test de connexion',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Test de connectivité réussi'),
                new OA\Property(
                    property: 'response',
                    type: 'object',
                    description: 'Réponse de l\'API'
                )
            ]
        )
    )]
    public function testConnection(): JsonResponse
    {
        $result = $this->smsService->testConnection();
        $statusCode = $result['success'] ? 200 : 500;

        return $this->json($result, $statusCode);
    }
}
