<?php

// Test POST /type-organigrammes
echo "=== TEST 1: POST /type-organigrammes ===\n";
$ch = curl_init('https://localhost:8000/type-organigrammes');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['nom'=>'Test Organigramme 1', 'description'=>'Description test']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "HTTP Code: $httpCode\n";
echo "Response: " . $response . "\n\n";
curl_close($ch);

// Test GET /type-organigrammes
echo "=== TEST 2: GET /type-organigrammes ===\n";
$ch = curl_init('https://localhost:8000/type-organigrammes?page=1&limit=10');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "HTTP Code: $httpCode\n";
echo "Response: " . $response . "\n";
curl_close($ch);
