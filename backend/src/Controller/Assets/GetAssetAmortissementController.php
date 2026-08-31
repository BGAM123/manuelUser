<?php

namespace App\Controller\Assets;

use App\Entity\Asset;
use App\Repository\AssetRepository;
use App\Service\ApiResponseFactory;
use App\Service\AssetDepreciation\AssetDepreciationCalculator;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/assets')]
#[OA\Tag(name: 'Amortissements')]
final class GetAssetAmortissementController extends AbstractController
{
    #[Route('/{id}/amortissement', name: 'app_asset_amortissement_detail', methods: ['GET'])]
    #[OA\Get(
        path: '/assets/{id}/amortissement',
        summary: 'Obtenir le détail de l\'amortissement d\'un bien',
        description: "Retourne les informations détaillées de l'amortissement d'un bien patrimonial.\n\n"
            . "**Le calcul est toujours basé sur la valeur actuelle du bien (`valeur`)**\n\n"
            . "**Informations retournées :**\n"
            . "- Valeur d'acquisition (valeur actuelle du bien)\n"
            . "- Valeur actuelle après amortissement\n"
            . "- Amortissement annuel\n"
            . "- Amortissement cumulé\n"
            . "- Durée de vie (en années)\n"
            . "- Taux d'amortissement\n"
            . "- Années écoulées depuis l'acquisition\n"
            . "- Durée de vie restante\n"
            . "- Mois écoulés depuis l'acquisition\n"
            . "- Type de calcul (AMORTISSEMENT_SIMPLE, AVEC_DEPRECIATION, etc.)"
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
        example: 1,
        description: 'ID du bien'
    )]
    #[OA\Response(
        response: 200,
        description: 'Amortissement récupéré avec succès',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Amortissement récupéré avec succès.',
                'data' => [
                    'id' => 1,
                    'reference' => 'PAT-2026-00001',
                    'nom' => 'Ordinateur Portable HP ProBook 450 G10',
                    'valeur' => 850000,
                    'valeurInitiale' => 850000,
                    'dateAcquisition' => '2026-07-30',
                    'typeBien' => [
                        'id' => 5,
                        'nom' => 'Ordinateur Portable',
                        // 'dureeVie' => 5,
                        // 'taux' => 20
                    ],
                    // 'activeAmortissement' => true,
                    'amortissement' => [
                        'valeur' => 850000,
                        'valeurActuelle' => 680000,
                        'amortissementAnnuel' => 170000,
                        'amortissementCumule' => 170000,
                        // 'dureeVie' => 5,
                        // 'taux' => 20,
                        'anneesEcoulees' => 1,
                        'dateAcquisition' => '2026-07-30',
                        'dureeVieRestante' => 4,
                        'moisEcoules' => 12,
                        'typeCalcul' => 'AMORTISSEMENT_SIMPLE'
                    ],
                    'statistiques' => [
                        'pourcentageAmorti' => 20,
                        'pourcentageRestant' => 80,
                        // 'valeurPerdue' => 170000
                    ]
                ]
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Bien introuvable',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 404,
                'message' => 'Le bien demandé est introuvable.',
                'data' => null
            ]
        )
    )]
    #[OA\Response(
        response: 409,
        description: 'Bien supprimé',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 409,
                'message' => 'Ce bien est supprimé.',
                'data' => null
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Données manquantes pour le calcul',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 400,
                'message' => 'Données manquantes pour le calcul de l\'amortissement.',
                'data' => [
                    'valeur' => 'La valeur du bien est requise',
                    'dateAcquisition' => 'La date d\'acquisition est requise',
                    'typeBien' => 'Un type de bien avec durée de vie est requis'
                ]
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Non authentifié',
        content: new OA\JsonContent(
            example: [
                'success' => false,
                'status' => 401,
                'message' => 'Authentification requise.',
                'data' => null
            ]
        )
    )]
    public function __invoke(
        int $id,
        AssetRepository $assetRepository,
        AssetDepreciationCalculator $depreciationCalculator,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        // Récupérer le bien avec ses relations
        $asset = $assetRepository->getActiveById($id);
        if (!$asset instanceof Asset) {
            return $apiResponse->error('Le bien demandé est introuvable.', Response::HTTP_NOT_FOUND);
        }

        // Vérifier si le bien est supprimé
        if ($asset->isDelete()) {
            return $apiResponse->error('Ce bien est supprimé.', Response::HTTP_CONFLICT);
        }

        // Calculer l'amortissement
        $depreciationResult = $depreciationCalculator->calculate($asset);

        // Vérifier si le calcul a retourné des données
        if (null === $depreciationResult->valeurAcquisition) {
            $errors = [];
            if (null === $asset->getValeur()) {
                $errors['valeur'] = 'La valeur du bien est requise';
            }
            if (null === $asset->getDateAcquisition()) {
                $errors['dateAcquisition'] = 'La date d\'acquisition est requise';
            }
            if (empty($asset->getAssetTypes()->toArray())) {
                $errors['typeBien'] = 'Un type de bien avec durée de vie est requis';
            } else {
                $typeBien = $asset->getAssetTypes()->first();
                if (null === $typeBien->getDureeVie() || $typeBien->getDureeVie() <= 0) {
                    $errors['dureeVie'] = 'La durée de vie du type de bien doit être supérieure à 0';
                }
            }

            return $apiResponse->error(
                'Données manquantes pour le calcul de l\'amortissement.',
                Response::HTTP_BAD_REQUEST,
                $errors
            );
        }

        // Calculer des statistiques supplémentaires
        $statistiques = $this->calculateStatistics($asset, $depreciationResult);

        // Construire la réponse
        $data = [
            'id' => $asset->getId(),
            'reference' => $asset->getReference(),
            'nom' => $asset->getNom(),
            'valeur' => $asset->getValeur(),
            'valeurInitiale' => $asset->getValeurInitiale(),
            'dateAcquisition' => $asset->getDateAcquisition()?->format('Y-m-d'),
            'typeBien' => $this->getTypeBienInfo($asset),
            'activeAmortissement' => $asset->isActiveAmortissement(),
            'amortissement' => $depreciationResult->toArray(),
            'statistiques' => $statistiques,
        ];

        return $apiResponse->success(
            $data,
            Response::HTTP_OK,
            'Amortissement récupéré avec succès.'
        );
    }

    /**
     * Récupère les informations du type de bien
     */
    private function getTypeBienInfo(Asset $asset): ?array
    {
        $typeBien = $asset->getAssetTypes()->first();
        if (!$typeBien) {
            return null;
        }

        return [
            'id' => $typeBien->getId(),
            'nom' => $typeBien->getNom(),
            // 'dureeVie' => $typeBien->getDureeVie(),
            // 'taux' => $typeBien->getTaux(),
        ];
    }

    /**
     * Calcule des statistiques supplémentaires
     */
    private function calculateStatistics(Asset $asset, $depreciationResult): array
    {
        $valeurAcquisition = $depreciationResult->valeurAcquisition ?? 0;
        $valeurActuelle = $depreciationResult->valeurActuelle ?? 0;
        $amortissementCumule = $depreciationResult->amortissementCumule ?? 0;

        // Pourcentage amorti
        $pourcentageAmorti = 0;
        if ($valeurAcquisition > 0) {
            $pourcentageAmorti = round(($amortissementCumule / $valeurAcquisition) * 100, 2);
        }

        // Pourcentage restant
        $pourcentageRestant = max(0, 100 - $pourcentageAmorti);

        // Valeur perdue (identique à l'amortissement cumulé)
        $valeurPerdue = $amortissementCumule;

        return [
            'pourcentageAmorti' => $pourcentageAmorti,
            'pourcentageRestant' => $pourcentageRestant,
            // 'valeurPerdue' => $valeurPerdue,
            // 'valeurAcquisition' => $valeurAcquisition,
            // 'valeurActuelle' => $valeurActuelle,
        ];
    }
}