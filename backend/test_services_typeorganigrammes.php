<?php
/**
 * Test script for Services API with typeOrganigrammes
 * Tests all Service endpoints to verify typeOrganigrammes are correctly returned
 */

$baseUrl = 'https://localhost:8000/api';

// Colors for output
$colors = [
    'reset' => "\033[0m",
    'green' => "\033[32m",
    'red' => "\033[31m",
    'yellow' => "\033[33m",
    'blue' => "\033[34m",
    'cyan' => "\033[36m",
];

function colorize($text, $color) {
    global $colors;
    return $colors[$color] . $text . $colors['reset'];
}

function testEndpoint($method, $endpoint, $description, $data = null) {
    global $baseUrl, $colors;

    echo colorize("\n" . str_repeat("=", 80), 'cyan') . "\n";
    echo colorize("TEST: $description", 'blue') . "\n";
    echo colorize("$method $endpoint", 'yellow') . "\n";

    $url = $baseUrl . $endpoint;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        echo colorize("✓ HTTP $httpCode - Success", 'green') . "\n";
    } else {
        echo colorize("✗ HTTP $httpCode - Error", 'red') . "\n";
    }

    if ($decoded) {
        // Check for typeOrganigrammes field
        if (isset($decoded['data'])) {
            $data = $decoded['data'];

            // Check if it's paginated
            if (isset($data['data']) && is_array($data['data'])) {
                echo "Paginated response detected\n";
                echo "Meta: " . json_encode($data['meta']) . "\n";

                if (!empty($data['data']) && isset($data['data'][0])) {
                    $firstItem = $data['data'][0];
                    if (isset($firstItem['typeOrganigrammes'])) {
                        echo colorize("✓ typeOrganigrammes field found", 'green') . "\n";
                        echo "  Value: " . json_encode($firstItem['typeOrganigrammes']) . "\n";

                        // Check if it's an array of objects or IDs
                        if (is_array($firstItem['typeOrganigrammes']) && count($firstItem['typeOrganigrammes']) > 0) {
                            $first = $firstItem['typeOrganigrammes'][0];
                            if (is_array($first) && isset($first['id'])) {
                                echo colorize("  Structure: Object array [{id, nom}, ...]", 'green') . "\n";
                            } elseif (is_int($first)) {
                                echo colorize("  Structure: ID array [1, 2, ...]", 'green') . "\n";
                            }
                        } else {
                            echo "  Empty array\n";
                        }
                    } else {
                        echo colorize("✗ typeOrganigrammes field missing", 'red') . "\n";
                    }
                }
            } else {
                // Single object response
                echo "Single object response detected\n";
                if (isset($data['typeOrganigrammes'])) {
                    echo colorize("✓ typeOrganigrammes field found", 'green') . "\n";
                    echo "  Value: " . json_encode($data['typeOrganigrammes']) . "\n";

                    if (is_array($data['typeOrganigrammes']) && count($data['typeOrganigrammes']) > 0) {
                        $first = $data['typeOrganigrammes'][0];
                        if (is_array($first) && isset($first['id'])) {
                            echo colorize("  Structure: Object array [{id, nom}, ...]", 'green') . "\n";
                        } elseif (is_int($first)) {
                            echo colorize("  Structure: ID array [1, 2, ...]", 'green') . "\n";
                        }
                    } else {
                        echo "  Empty array\n";
                    }
                } else {
                    echo colorize("✗ typeOrganigrammes field missing", 'red') . "\n";
                }
            }
        }
    } else {
        echo "Response: " . $response . "\n";
    }
}

echo colorize("\n" . str_repeat("=", 80), 'cyan') . "\n";
echo colorize("TESTING SERVICES API - typeOrganigrammes Integration", 'yellow') . "\n";
echo colorize("Base URL: $baseUrl", 'cyan') . "\n";

// Test GET /services (list)
testEndpoint('GET', '/services?page=1&limit=5', 'GET /services - List with pagination');

// Test GET /services/{id} (detail)
testEndpoint('GET', '/services/2', 'GET /services/{id} - Service detail');

// Test GET /organigramme (hierarchy)
testEndpoint('GET', '/organigramme?page=1&limit=5', 'GET /organigramme - Hierarchical structure');

// Test with type_organigramme_id filter
testEndpoint('GET', '/services?type_organigramme_id=1&page=1&limit=5', 'GET /services - Filtered by type_organigramme_id');

echo colorize("\n" . str_repeat("=", 80), 'cyan') . "\n";
echo colorize("TEST COMPLETE", 'yellow') . "\n";
