<?php

namespace App\Controller\Assets;

use App\Repository\AssetRepository;
use App\Service\ApiResponseFactory;
use App\Service\AssetDepreciation\AssetDepreciationCalculator;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/assets')]
#[OA\Tag(name: 'Amortissements')]
final class ListAssetAmortissementsController extends AbstractController
{
    #[Route('/amortissements/tableau', name: 'app_asset_amortissement_schedule', methods: ['GET'])]
    #[OA\Get(
        path: '/assets/amortissements/tableau',
        summary: 'Tableau d\'amortissement de tous les biens',
        description: 'Retourne la liste paginée des biens actifs avec leurs données d\'amortissement calculées. '
            . 'Réutilise AssetDepreciationCalculator, le même service que GET /assets/{id}/amortissement. '
            . 'Les biens sans données suffisantes pour le calcul (valeur, date d\'acquisition ou type de bien manquant) sont exclus du tableau.'
    )]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20))]
    #[OA\Parameter(name: 'category', in: 'query', schema: new OA\Schema(type: 'integer'), description: 'Filtrer par ID de catégorie')]
    #[OA\Parameter(
        name: 'exercice',
        in: 'query',
        schema: new OA\Schema(type: 'integer'),
        description: 'Filtre les biens acquis durant cette année (ex: 2026). Basé sur la date d\'acquisition.'
    )]
    #[OA\Parameter(name: 'date_debut', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), description: 'Date d\'acquisition minimale (YYYY-MM-DD)')]
    #[OA\Parameter(name: 'date_fin', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), description: 'Date d\'acquisition maximale (YYYY-MM-DD)')]
    #[OA\Response(
        response: 200,
        description: 'Success - tableau d\'amortissement retourné',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Tableau d\'amortissement retourné avec succès.',
                'data' => [
                    'meta' => ['current_page' => 1, 'limit' => 20, 'total_items' => 1, 'total_pages' => 1],
                    'data' => [
                        [
                            'bien' => ['id' => 1, 'reference' => 'PAT-2026-00001', 'designation' => 'Ordinateur Portable HP ProBook 450 G10'],
                            'categorie' => ['id' => 2, 'nom' => 'Matériel informatique'],
                            'valeur_acquisition' => 850000,
                            'date_acquisition' => '2026-07-30',
                            'duree_vie' => 5,
                            'taux_amortissement' => 20,
                            'amortissement_annuel' => 170000,
                            'amortissement_cumule' => 170000,
                            'vnc' => 680000,
                            'annees_ecoulees' => 1,
                            'duree_vie_restante' => 4,
                            'type_calcul' => 'AMORTISSEMENT_SIMPLE',
                        ],
                    ],
                ],
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'Le paramètre exercice doit être une année valide (4 chiffres).', 'data' => null])
    )]
    public function __invoke(
        Request $request,
        AssetRepository $assetRepository,
        AssetDepreciationCalculator $depreciationCalculator,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 20);
        $limit = $limit < 1 ? 20 : ($limit > 200 ? 200 : $limit);

        $categoryId = $request->query->has('category') ? $request->query->getInt('category') : null;

        $dateDebut = null;
        $dateDebutParam = $request->query->get('date_debut');
        if (null !== $dateDebutParam && '' !== $dateDebutParam) {
            try {
                $dateDebut = new \DateTimeImmutable($dateDebutParam);
            } catch (\Exception) {
                return $apiResponse->error('Le paramètre date_debut est invalide.', Response::HTTP_BAD_REQUEST);
            }
        }

        $dateFin = null;
        $dateFinParam = $request->query->get('date_fin');
        if (null !== $dateFinParam && '' !== $dateFinParam) {
            try {
                $dateFin = new \DateTimeImmutable($dateFinParam);
            } catch (\Exception) {
                return $apiResponse->error('Le paramètre date_fin est invalide.', Response::HTTP_BAD_REQUEST);
            }
        }

        // Pas de fonction DQL YEAR() disponible dans ce projet : exercice se traduit en
        // bornes de date d'acquisition (1er janvier - 31 décembre), combinées avec des
        // date_debut/date_fin explicites si les deux sont fournis (bornes les plus strictes).
        $exerciceParam = $request->query->get('exercice');
        if (null !== $exerciceParam && '' !== $exerciceParam) {
            if (!preg_match('/^\d{4}$/', (string) $exerciceParam)) {
                return $apiResponse->error('Le paramètre exercice doit être une année valide (4 chiffres).', Response::HTTP_BAD_REQUEST);
            }
            $exerciceDebut = new \DateTimeImmutable($exerciceParam . '-01-01');
            $exerciceFin = new \DateTimeImmutable($exerciceParam . '-12-31');

            $dateDebut = $dateDebut && $dateDebut > $exerciceDebut ? $dateDebut : $exerciceDebut;
            $dateFin = $dateFin && $dateFin < $exerciceFin ? $dateFin : $exerciceFin;
        }

        // Filtrage par utilisateur connecté
        $user = $this->getUser();
        $userId = null;
        if ($user && method_exists($user, 'getId')) {
            // Vérifier si l'utilisateur a un rôle administrateur
            $isAdmin = false;
            $userRoles = $user->getAssignedRoles();
            $adminRoleNames = ['Administrateur', 'Administrateur patrimonial', 'Administrateur système'];

            foreach ($userRoles as $role) {
                if (in_array($role->getNom(), $adminRoleNames, true)) {
                    $isAdmin = true;
                    break;
                }
            }

            // Si pas administrateur, filtrer par l'utilisateur connecté (détenteur actuel)
            if (!$isAdmin) {
                $userId = $user->getId();
            }
        }

        $assets = $assetRepository->findActiveForDepreciationSchedule($page, $limit, $categoryId, $dateDebut, $dateFin, $userId);
        $total = $assetRepository->countActiveForDepreciationSchedule($categoryId, $dateDebut, $dateFin, $userId);

        $rows = [];
        foreach ($assets as $asset) {
            $result = $depreciationCalculator->calculate($asset);
            if (null === $result->valeurAcquisition) {
                continue;
            }

            $category = $asset->getCategories()->first();

            $rows[] = [
                'bien' => [
                    'id' => $asset->getId(),
                    'reference' => $asset->getReference(),
                    'designation' => $asset->getNom(),
                ],
                'categorie' => $category ? ['id' => $category->getId(), 'nom' => $category->getNom()] : null,
                'valeur_acquisition' => $result->valeurAcquisition,
                'date_acquisition' => $result->dateAcquisition,
                'duree_vie' => $result->dureeVie,
                'taux_amortissement' => $result->taux,
                'amortissement_annuel' => $result->amortissementAnnuel,
                'amortissement_cumule' => $result->amortissementCumule,
                'vnc' => $result->valeurActuelle,
                'annees_ecoulees' => $result->anneesEcoulees,
                'duree_vie_restante' => $result->dureeVieRestante,
                'type_calcul' => $result->typeCalcul,
            ];
        }

        $meta = [
            'current_page' => $page,
            'limit' => $limit,
            'total_items' => $total,
            'total_pages' => (int) ceil($total / max(1, $limit)),
        ];

        return $apiResponse->success([
            'meta' => $meta,
            'data' => $rows,
        ], Response::HTTP_OK, 'Tableau d\'amortissement retourné avec succès.');
    }
}
