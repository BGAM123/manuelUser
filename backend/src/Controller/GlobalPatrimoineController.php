<?php

namespace App\Controller;

use App\Service\ApiResponseFactory;
use App\Service\GlobalPatrimoineService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Contrôleur pour l'API globale du patrimoine et des consommables.
 * 
 * Ce contrôleur fournit une vue consolidée et structurée de l'ensemble du patrimoine
 * et des consommables, avec des regroupements par différents critères.
 * 
 * L'API est en lecture seule (GET uniquement) et ne modifie aucune donnée.
 */
#[Route('/patRimoine-global')]
#[OA\Tag(name: 'Stock')]
class GlobalPatrimoineController extends AbstractController
{
    public function __construct(
        private readonly GlobalPatrimoineService $globalPatrimoineService,
        private readonly ApiResponseFactory $apiResponseFactory
    ) {
    }

    /**
     * Récupère la vue globale complète du patrimoine et des consommables.
     * 
     * Cette endpoint retourne une structure JSON complète avec :
     * - Indicateurs globaux (total biens, affectations, restitutions, statuts)
     * - Regroupements par État (avec liste de biens)
     * - Regroupements par Région (avec liste de biens)
     * - Regroupements par Département (avec liste de biens)
     * - Regroupements par Arrondissement (avec liste de biens)
     * - Regroupements par Service (avec liste de biens)
     * - Regroupements par Projet (avec liste de biens)
     * - Regroupements par Catégorie (avec liste de biens)
     * - Regroupements par Type (avec liste de biens)
     * - Regroupements combinés (Region-Departement-Categorie-Etat)
     * - Regroupements combinés (Categorie-Region)
     * - Regroupements pour les consommables (par Service, par Catégorie)
     * 
     * @return JsonResponse Vue globale du patrimoine
     */
    #[Route('', name: 'global_patrimoine', methods: ['GET'])]
    public function getGlobalPatrimoine(Request $request): JsonResponse
    {
        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = max(1, min(1000, (int) $request->query->get('limit', 100)));
            
            $data = $this->globalPatrimoineService->getGlobalPatrimoine($page, $limit);
            
            return $this->apiResponseFactory->success($data);
        } catch (\Exception $e) {
            return $this->apiResponseFactory->error(
                'Erreur lors de la récupération des données globales : ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Récupère uniquement les données agrégées pour les biens.
     * 
     * Cette endpoint retourne uniquement la section BIENS de la vue globale.
     * 
     * @return JsonResponse Données agrégées des biens
     */
    #[Route('/biens', name: 'global_patrimoine_biens', methods: ['GET'])]
    public function getBiensData(Request $request): JsonResponse
    {
        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = max(1, min(1000, (int) $request->query->get('limit', 100)));
            
            $data = $this->globalPatrimoineService->getGlobalPatrimoine($page, $limit);
            
            return $this->apiResponseFactory->success($data['PATRIMOINE_GLOBAL']['BIENS']);
        } catch (\Exception $e) {
            return $this->apiResponseFactory->error(
                'Erreur lors de la récupération des données des biens : ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Récupère uniquement les données agrégées pour les consommables.
     * 
     * Cette endpoint retourne uniquement la section CONSOMMABLES de la vue globale.
     * 
     * @return JsonResponse Données agrégées des consommables
     */
    #[Route('/consommables', name: 'global_patrimoine_consommables', methods: ['GET'])]
    public function getConsommablesData(Request $request): JsonResponse
    {
        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = max(1, min(1000, (int) $request->query->get('limit', 100)));
            
            $data = $this->globalPatrimoineService->getGlobalPatrimoine($page, $limit);
            
            return $this->apiResponseFactory->success($data['PATRIMOINE_GLOBAL']['CONSOMMABLES']);
        } catch (\Exception $e) {
            return $this->apiResponseFactory->error(
                'Erreur lors de la récupération des données des consommables : ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Récupère les indicateurs globaux (total biens, affectations, restitutions, statuts).
     * 
     * Cette endpoint retourne uniquement les indicateurs numériques sans les listes de biens.
     * 
     * @return JsonResponse Indicateurs globaux
     */
    #[Route('/indicateurs', name: 'global_patrimoine_indicateurs', methods: ['GET'])]
    public function getIndicateurs(Request $request): JsonResponse
    {
        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = max(1, min(1000, (int) $request->query->get('limit', 100)));
            
            $data = $this->globalPatrimoineService->getGlobalPatrimoine($page, $limit);
            $biensData = $data['PATRIMOINE_GLOBAL']['BIENS'];
            
            return $this->apiResponseFactory->success([
                'totalBiens' => $biensData['totalBiens'],
                'affectations' => [
                    'total' => $biensData['affectations']['total'],
                    'parService' => $biensData['affectations']['parService'],
                ],
                'restitutions' => [
                    'total' => $biensData['restitutions']['total'],
                    'parService' => $biensData['restitutions']['parService'],
                ],
                'statuts' => $biensData['statuts'],
            ]);
        } catch (\Exception $e) {
            return $this->apiResponseFactory->error(
                'Erreur lors de la récupération des indicateurs : ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Récupère les biens regroupés par un critère spécifique.
     * 
     * @param Request $request Requête HTTP
     * @return JsonResponse Biens regroupés par critère
     */
    #[Route('/biens/par/{critere}', name: 'global_patrimoine_biens_par_critere', methods: ['GET'])]
    public function getBiensParCritere(Request $request, string $critere): JsonResponse
    {
        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = max(1, min(1000, (int) $request->query->get('limit', 100)));
            
            $data = $this->globalPatrimoineService->getGlobalPatrimoine($page, $limit);
            $biensData = $data['PATRIMOINE_GLOBAL']['BIENS'];
            
            $critereMap = [
                'etat' => 'parEtat',
                'region' => 'parRegion',
                'departement' => 'parDepartement',
                'arrondissement' => 'parArrondissement',
                'service' => 'parService',
                'projet' => 'parProjet',
                'categorie' => 'parCategorie',
                'type' => 'parType',
                'region-departement-categorie-etat' => 'parRegionDepartementCategorieEtat',
                'categorie-region' => 'parCategorieRegion',
            ];
            
            if (!isset($critereMap[$critere])) {
                return $this->apiResponseFactory->error(
                    'Critère invalide. Critères disponibles : ' . implode(', ', array_keys($critereMap)),
                    400
                );
            }
            
            $cle = $critereMap[$critere];
            
            if (!isset($biensData[$cle])) {
                return $this->apiResponseFactory->error(
                    'Données non disponibles pour ce critère',
                    404
                );
            }
            
            return $this->apiResponseFactory->success($biensData[$cle]);
        } catch (\Exception $e) {
            return $this->apiResponseFactory->error(
                'Erreur lors de la récupération des données : ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Récupère les consommables regroupés par un critère spécifique.
     * 
     * @param Request $request Requête HTTP
     * @return JsonResponse Consommables regroupés par critère
     */
    #[Route('/consommables/par/{critere}', name: 'global_patrimoine_consommables_par_critere', methods: ['GET'])]
    public function getConsommablesParCritere(Request $request, string $critere): JsonResponse
    {
        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = max(1, min(1000, (int) $request->query->get('limit', 100)));
            
            $data = $this->globalPatrimoineService->getGlobalPatrimoine($page, $limit);
            $consommablesData = $data['PATRIMOINE_GLOBAL']['CONSOMMABLES'];
            
            $critereMap = [
                'service' => 'parService',
                'categorie' => 'parCategorie',
            ];
            
            if (!isset($critereMap[$critere])) {
                return $this->apiResponseFactory->error(
                    'Critère invalide. Critères disponibles : ' . implode(', ', array_keys($critereMap)),
                    400
                );
            }
            
            $cle = $critereMap[$critere];
            
            if (!isset($consommablesData[$cle])) {
                return $this->apiResponseFactory->error(
                    'Données non disponibles pour ce critère',
                    404
                );
            }
            
            return $this->apiResponseFactory->success($consommablesData[$cle]);
        } catch (\Exception $e) {
            return $this->apiResponseFactory->error(
                'Erreur lors de la récupération des données : ' . $e->getMessage(),
                500
            );
        }
    }
}
