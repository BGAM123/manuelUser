<?php
$spec = json_decode(file_get_contents('api_spec.json'), true);
$tags = isset($spec['tags']) ? array_column($spec['tags'], 'name') : array();

echo "Total tags: " . count($tags) . "\n";
echo "Last 5 tags: " . implode(', ', array_slice($tags, -5)) . "\n";
echo "Has 'Asset Exits': " . (in_array('Asset Exits', $tags) ? 'YES' : 'NO') . "\n";

// Check paths
$paths = isset($spec['paths']) ? array_keys($spec['paths']) : array();
$assetExitsPaths = array_filter($paths, fn($p) => str_contains($p, 'asset-exits'));
echo "Asset Exits paths found: " . count($assetExitsPaths) . "\n";
if ($assetExitsPaths) {
    echo "Paths: " . implode(', ', array_values($assetExitsPaths)) . "\n";
}
