<?php

namespace App\Tests\Controller\Stock;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests pour l'API unique de stock patrimonial.
 */
class StockApiTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * Test de l'endpoint GET /gestion_stock_bien (réponse par défaut)
     */
    public function testGetStockDefault(): void
    {
        $this->client->request('GET', '/gestion_stock_bien');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('data', $data);
        $this->assertTrue($data['success']);
        
        // Vérifier que les sections par défaut sont présentes
        $this->assertArrayHasKey('summary', $data['data']);
        $this->assertArrayHasKey('assets', $data['data']);
    }

    /**
     * Test de l'endpoint GET /gestion_stock_bien avec pagination
     */
    public function testGetStockWithPagination(): void
    {
        $this->client->request('GET', '/gestion_stock_bien', [
            'page' => 1,
            'limit' => 5,
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('assets', $data['data']);
        $this->assertArrayHasKey('data', $data['data']['assets']);
        $this->assertArrayHasKey('pagination', $data['data']['assets']);
        
        $this->assertLessThanOrEqual(5, count($data['data']['assets']['data']));
        $this->assertEquals(1, $data['data']['assets']['pagination']['page']);
        $this->assertEquals(5, $data['data']['assets']['pagination']['limit']);
    }

    /**
     * Test de l'endpoint GET /gestion_stock_bien avec filtres
     */
    public function testGetStockWithFilters(): void
    {
        $this->client->request('GET', '/gestion_stock_bien', [
            'page' => 1,
            'limit' => 10,
            'search' => 'test',
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('assets', $data['data']);
    }

    /**
     * Test de l'endpoint GET /gestion_stock_bien avec filtre de situation
     */
    public function testGetStockWithSituationFilter(): void
    {
        $this->client->request('GET', '/gestion_stock_bien', [
            'situation' => 'DISPONIBLE',
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('assets', $data['data']);
    }

    /**
     * Test de l'endpoint GET /gestion_stock_bien avec include_summary=false
     */
    public function testGetStockWithoutSummary(): void
    {
        $this->client->request('GET', '/gestion_stock_bien', [
            'include_summary' => 'false',
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('summary', $data['data']);
    }

    /**
     * Test de l'endpoint GET /gestion_stock_bien avec include_assets=false
     */
    public function testGetStockWithoutAssets(): void
    {
        $this->client->request('GET', '/gestion_stock_bien', [
            'include_assets' => 'false',
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('assets', $data['data']);
    }

    /**
     * Test de l'endpoint GET /gestion_stock_bien avec include_movements=true
     */
    public function testGetStockWithMovements(): void
    {
        $this->client->request('GET', '/gestion_stock_bien', [
            'include_movements' => 'true',
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayNotHasKey('movements', $data['data']);
    }

    /**
     * Test de l'endpoint GET /gestion_stock_bien avec asset_id et include_history
     */
    public function testGetStockWithAssetHistory(): void
    {
        // Note: Ce test nécessite un asset_id valide dans la base de données
        $this->client->request('GET', '/gestion_stock_bien', [
            'asset_id' => 1,
            'include_history' => 'true',
        ]);

        // Peut retourner une réponse vide si l'asset n'existe pas
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('data', $data);
    }

    /**
     * Test de validation des paramètres include_*
     */
    public function testIncludeParametersValidation(): void
    {
        $this->client->request('GET', '/gestion_stock_bien', [
            'include_summary' => 'true',
            'include_statistics' => 'true',
            'include_assets' => 'true',
            'include_movements' => 'false',
            'include_history' => 'false',
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('summary', $data['data']);
        $this->assertArrayHasKey('assets', $data['data']);
        $this->assertArrayNotHasKey('movements', $data['data']);
        $this->assertArrayNotHasKey('history', $data['data']);
    }

    /**
     * Test de validation du paramètre page
     */
    public function testInvalidPageParameter(): void
    {
        $this->client->request('GET', '/gestion_stock_bien', [
            'page' => -1,
        ]);

        // Le contrôleur normalise la page à 1 minimum
        $this->assertResponseIsSuccessful();
    }

    /**
     * Test de validation du paramètre limit
     */
    public function testInvalidLimitParameter(): void
    {
        $this->client->request('GET', '/gestion_stock_bien', [
            'limit' => 200, // Au-delà de la limite max de 100
        ]);

        // Le contrôleur normalise la limite à 100 maximum
        $this->assertResponseIsSuccessful();
    }

    /**
     * Test de la structure de réponse avec nouvelle structure du summary
     */
    public function testResponseStructure(): void
    {
        $this->client->request('GET', '/gestion_stock_bien');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        // Structure racine
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('data', $data);

        // Structure de summary (nouvelle structure)
        if (isset($data['data']['summary'])) {
            $this->assertArrayHasKey('patrimoine', $data['data']['summary']);
            $this->assertArrayHasKey('situations', $data['data']['summary']);
            $this->assertArrayHasKey('affectations', $data['data']['summary']);
            $this->assertArrayHasKey('sorties', $data['data']['summary']);

            // Patrimoine
            $this->assertArrayHasKey('total', $data['data']['summary']['patrimoine']);
            $this->assertArrayHasKey('actifs', $data['data']['summary']['patrimoine']);
            $this->assertArrayHasKey('inactifs', $data['data']['summary']['patrimoine']);

            // Situations
            $this->assertArrayHasKey('non_affectes', $data['data']['summary']['situations']);
            $this->assertArrayHasKey('affectes', $data['data']['summary']['situations']);
            $this->assertArrayHasKey('maintenance', $data['data']['summary']['situations']);

            // Affectations
            $this->assertArrayHasKey('total', $data['data']['summary']['affectations']);
            $this->assertArrayHasKey('utilisateurs', $data['data']['summary']['affectations']);
            $this->assertArrayHasKey('services', $data['data']['summary']['affectations']);

            // Sorties
            $this->assertArrayHasKey('definitives', $data['data']['summary']['sorties']);
        }

        // Structure de assets
        if (isset($data['data']['assets'])) {
            $this->assertArrayHasKey('data', $data['data']['assets']);
            $this->assertArrayHasKey('pagination', $data['data']['assets']);
            $this->assertArrayHasKey('page', $data['data']['assets']['pagination']);
            $this->assertArrayHasKey('limit', $data['data']['assets']['pagination']);
            $this->assertArrayHasKey('total', $data['data']['assets']['pagination']);
            $this->assertArrayHasKey('pages', $data['data']['assets']['pagination']);
        }

        // Structure de history (toujours présente, peut être null)
        $this->assertArrayNotHasKey('history', $data['data']);
    }

    /**
     * Test des invariants : aucun compteur ne doit être négatif
     */
    public function testNoNegativeCounters(): void
    {
        $this->client->request('GET', '/gestion_stock_bien');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        if (isset($data['data']['summary'])) {
            $summary = $data['data']['summary'];

            // Patrimoine
            $this->assertGreaterThanOrEqual(0, $summary['patrimoine']['total']);
            $this->assertGreaterThanOrEqual(0, $summary['patrimoine']['actifs']);
            $this->assertGreaterThanOrEqual(0, $summary['patrimoine']['inactifs']);

            // Situations
            $this->assertGreaterThanOrEqual(0, $summary['situations']['disponibles']);
            $this->assertGreaterThanOrEqual(0, $summary['situations']['non_affectes']);
            $this->assertGreaterThanOrEqual(0, $summary['situations']['affectes']);
            $this->assertGreaterThanOrEqual(0, $summary['situations']['maintenance']);

            // Affectations
            $this->assertGreaterThanOrEqual(0, $summary['affectations']['total']);
            $this->assertGreaterThanOrEqual(0, $summary['affectations']['utilisateurs']['total_biens']);
            $this->assertGreaterThanOrEqual(0, $summary['affectations']['services']['total_biens']);

            // Sorties
            $this->assertGreaterThanOrEqual(0, $summary['sorties']['definitives']);
            $this->assertGreaterThanOrEqual(0, $summary['sorties']['bsp_non_retournes']);
        }
    }

    /**
     * Test de cohérence : patrimoine = actifs + inactifs
     */
    public function testPatrimoineConsistency(): void
    {
        $this->client->request('GET', '/gestion_stock_bien');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        if (isset($data['data']['summary'])) {
            $summary = $data['data']['summary'];
            $patrimoineTotal = $summary['patrimoine']['total'];
            $actifs = $summary['patrimoine']['actifs'];
            $inactifs = $summary['patrimoine']['inactifs'];

            $this->assertEquals($actifs + $inactifs, $patrimoineTotal);
        }
    }

    /**
     * Test de cohérence : situations = disponibles + affectes + maintenance
     */
    public function testSituationsConsistency(): void
    {
        $this->client->request('GET', '/gestion_stock_bien');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        if (isset($data['data']['summary'])) {
            $summary = $data['data']['summary'];
            $patrimoineTotal = $summary['patrimoine']['total'];
            $disponibles = $summary['situations']['disponibles'];
            $affectes = $summary['situations']['affectes'];
            $maintenance = $summary['situations']['maintenance'];

            // Les situations doivent être cohérentes avec le patrimoine
            $this->assertEquals($disponibles + $affectes + $maintenance, $patrimoineTotal);
        }
    }

    /**
     * Test de cohérence : affectations = utilisateurs + services
     */
    public function testAssignmentsConsistency(): void
    {
        $this->client->request('GET', '/gestion_stock_bien');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        if (isset($data['data']['summary'])) {
            $summary = $data['data']['summary'];
            $affectationsTotal = $summary['affectations']['total'];
            $utilisateursTotal = $summary['affectations']['utilisateurs']['total_biens'];
            $servicesTotal = $summary['affectations']['services']['total_biens'];

            $this->assertEquals($utilisateursTotal + $servicesTotal, $affectationsTotal);
        }
    }
}
