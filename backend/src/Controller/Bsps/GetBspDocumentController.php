<?php

namespace App\Controller\Bsps;

use App\Entity\Bsp;
use App\Service\ApiResponseFactory;
use App\Service\BspPdfGeneratorService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/bsps')]
#[OA\Tag(name: 'BSP')]
final class GetBspDocumentController extends AbstractController
{
    #[Route('/{id}/document', name: 'app_bsp_document', methods: ['GET'])]
    #[OA\Get(
        path: '/bsps/{id}/document',
        summary: 'Télécharger le document PDF d\'un BSP',
        description: 'Génère et retourne le Bon de Sortie Provisoire au format PDF, conforme au modèle officiel.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Response(response: 200, description: 'Fichier PDF', content: new OA\MediaType(mediaType: 'application/pdf'))]
    #[OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(example: ['success' => false, 'status' => 401, 'message' => 'Authentification requise.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'BSP introuvable', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'BSP introuvable.', 'data' => null]))]
    #[OA\Response(response: 500, description: 'Erreur serveur', content: new OA\JsonContent(example: ['success' => false, 'status' => 500, 'message' => 'Une erreur interne du serveur est survenue.', 'data' => null]))]
    public function __invoke(
        Bsp $bsp,
        BspPdfGeneratorService $pdfGenerator,
        ApiResponseFactory $apiResponse
    ): Response {
        if ($bsp->isDelete()) {
            return $apiResponse->error('Ce BSP est supprimé.', Response::HTTP_NOT_FOUND);
        }

        $pdfContent = $pdfGenerator->generate($bsp);

        $response = new Response($pdfContent);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set(
            'Content-Disposition',
            sprintf('inline; filename="%s.pdf"', $bsp->getNumero() ?? ('bsp-' . $bsp->getId()))
        );

        return $response;
    }
}
