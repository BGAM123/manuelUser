<?php

namespace App\Controller\Core\Sms;

use App\Service\SmsService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "SMS")]
class SmsBalanceController extends AbstractController
{
    public function __construct(
        private SmsService $smsService,
    ) {}

    #[Route('/core/sms/balance', name: 'app_core_sms_balance', methods: ['GET'])]
    #[OA\Get(
        path: '/core/sms/balance',
        security: [["bearerAuth" => []]],
        summary: 'Consulter le solde SMS disponible',
        tags: ['SMS'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Solde SMS récupéré avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'balance', type: 'integer', example: 968, description: 'Solde restant de SMS'),
                        new OA\Property(property: 'qty_acheter', type: 'integer', example: 1000, description: 'Quantité de SMS achetés'),
                        new OA\Property(property: 'qty_envoye', type: 'integer', example: 32, description: 'Quantité de SMS envoyés'),
                        new OA\Property(property: 'message', type: 'string', example: 'Solde récupéré avec succès'),
                        new OA\Property(
                            property: 'response',
                            type: 'object',
                            description: 'Réponse complète de l\'API SMS'
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Erreur lors de la récupération du solde',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'balance', type: 'integer', nullable: true, example: null),
                        new OA\Property(property: 'message', type: 'string', example: 'Configuration SMS manquante'),
                        new OA\Property(property: 'error_type', type: 'string', example: 'configuration')
                    ]
                )
            )
        ]
    )]
    public function getBalance(): Response
    {
        $result = $this->smsService->getSmsBalance();
        
        $statusCode = $result['success'] ? 200 : 500;
        
        return $this->json($result, $statusCode);
    }
}
