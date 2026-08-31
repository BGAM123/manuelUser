<?php

function makeRequest($url, $method = 'GET', $data = null, $token = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $httpCode, 'body' => $response];
}

echo "=== STEP 1: Login to get JWT Token ===\n";
$loginResp = makeRequest(
    'https://localhost:8000/login_check',
    'POST',
    ['email' => 'admin@example.com', 'password' => 'password']
);

if ($loginResp['code'] != 200) {
    echo "Login failed (HTTP {$loginResp['code']})\n";
    echo "Response: {$loginResp['body']}\n";
    exit(1);
}

$loginData = json_decode($loginResp['body'], true);
if (!isset($loginData['data']['token'])) {
    echo "No token in response\n";
    echo "Response: " . json_encode($loginData, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}

$token = $loginData['data']['token'];
echo "Token obtained: " . substr($token, 0, 50) . "...\n\n";

// Test POST /type-organigrammes
echo "=== TEST 1: POST /type-organigrammes ===\n";
$postResp = makeRequest(
    'https://localhost:8000/type-organigrammes',
    'POST',
    ['nom' => 'Test Organigramme ' . time(), 'description' => 'Description test'],
    $token
);
echo "HTTP Code: {$postResp['code']}\n";
echo "Response: " . $postResp['body'] . "\n\n";

// Test GET /type-organigrammes
echo "=== TEST 2: GET /type-organigrammes ===\n";
$getResp = makeRequest('https://localhost:8000/type-organigrammes?page=1&limit=10', 'GET', null, $token);
echo "HTTP Code: {$getResp['code']}\n";
$data = json_decode($getResp['body'], true);
echo "Response structure: " . json_encode(['success' => $data['success'] ?? null, 'status' => $data['status'] ?? null, 'message' => $data['message'] ?? null, 'data' => isset($data['data']) ? array_keys($data['data']) : null], JSON_PRETTY_PRINT) . "\n";
