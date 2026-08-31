<?php
/**
 * Test script for 2FA (Two-Factor Authentication) implementation
 * Tests OTP generation, sending, and verification
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
    'bold' => "\033[1m",
];

function colorize($text, $color) {
    global $colors;
    return $colors[$color] . $text . $colors['reset'];
}

function log_test($title, $method, $endpoint) {
    echo colorize("\n" . str_repeat("=", 80), 'cyan') . "\n";
    echo colorize($title, 'bold') . "\n";
    echo colorize("$method $endpoint", 'yellow') . "\n";
}

function make_request($method, $endpoint, $data = null, $token = null) {
    global $baseUrl;

    $url = $baseUrl . $endpoint;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => $response,
        'decoded' => json_decode($response, true)
    ];
}

echo colorize("\n" . str_repeat("=", 80), 'cyan') . "\n";
echo colorize("TWO-FACTOR AUTHENTICATION (2FA) TEST SUITE", 'bold') . "\n";
echo colorize("Base URL: $baseUrl", 'cyan') . "\n";

// Test 1: Create user WITH 2FA enabled
log_test("TEST 1: Create user WITH 2FA enabled", "POST", "/users");
$createUserWith2FA = make_request('POST', '/users', [
    'firstName' => 'Test',
    'lastName' => '2FA',
    'email' => 'test2fa@example.com',
    'password' => 'TestPassword123!',
    'twoFactorEnabled' => true,
    'is_active' => true
]);

if ($createUserWith2FA['code'] === 201) {
    echo colorize("✓ HTTP " . $createUserWith2FA['code'] . " - User created with 2FA enabled", 'green') . "\n";
    $userData = $createUserWith2FA['decoded']['data'] ?? null;
    if ($userData && isset($userData['twoFactorEnabled'])) {
        echo "  twoFactorEnabled: " . ($userData['twoFactorEnabled'] ? 'true' : 'false') . "\n";
    }
} else {
    echo colorize("✗ HTTP " . $createUserWith2FA['code'], 'red') . "\n";
    if (isset($createUserWith2FA['decoded']['message'])) {
        echo "  Error: " . $createUserWith2FA['decoded']['message'] . "\n";
    }
}

// Test 2: Login with 2FA user (should return requires_otp=true)
log_test("TEST 2: Login with 2FA enabled user (should get requires_otp=true)", "POST", "/login_check");
$login2FA = make_request('POST', '/login_check', [
    'email' => 'test2fa@example.com',
    'password' => 'TestPassword123!'
]);

$otpCode = null;
if ($login2FA['code'] === 200) {
    echo colorize("✓ HTTP " . $login2FA['code'] . " - Login successful", 'green') . "\n";
    $response = $login2FA['decoded']['data'] ?? null;
    if ($response && isset($response['requires_otp'])) {
        if ($response['requires_otp']) {
            echo colorize("✓ requires_otp field present and true", 'green') . "\n";
            echo "  OTP has been sent to test2fa@example.com (check inbox/spam)\n";

            // For testing, we'll use a demo OTP
            echo colorize("\n⚠️  For testing: Enter the OTP you received, or use demo code", 'yellow') . "\n";
            echo "   (In production, check email for actual OTP)\n";
        } else {
            echo colorize("✗ requires_otp is false", 'red') . "\n";
        }
    }
} else {
    echo colorize("✗ HTTP " . $login2FA['code'], 'red') . "\n";
}

// Test 3: Create user WITHOUT 2FA (default behavior)
log_test("TEST 3: Create user WITHOUT 2FA (default behavior)", "POST", "/users");
$createUserNoTwoFA = make_request('POST', '/users', [
    'firstName' => 'Test',
    'lastName' => 'NoTwoFA',
    'email' => 'testno2fa@example.com',
    'password' => 'TestPassword123!',
    'is_active' => true
]);

if ($createUserNoTwoFA['code'] === 201) {
    echo colorize("✓ HTTP " . $createUserNoTwoFA['code'] . " - User created without 2FA", 'green') . "\n";
    $userData = $createUserNoTwoFA['decoded']['data'] ?? null;
    if ($userData && isset($userData['twoFactorEnabled'])) {
        echo "  twoFactorEnabled: " . ($userData['twoFactorEnabled'] ? 'true' : 'false') . "\n";
        if (!$userData['twoFactorEnabled']) {
            echo colorize("✓ Correctly defaulted to false", 'green') . "\n";
        }
    }
} else {
    echo colorize("✗ HTTP " . $createUserNoTwoFA['code'], 'red') . "\n";
}

// Test 4: Login without 2FA user (should return token directly)
log_test("TEST 4: Login without 2FA user (should get JWT directly)", "POST", "/login_check");
$loginNoTwoFA = make_request('POST', '/login_check', [
    'email' => 'testno2fa@example.com',
    'password' => 'TestPassword123!'
]);

$jwtToken = null;
if ($loginNoTwoFA['code'] === 200) {
    echo colorize("✓ HTTP " . $loginNoTwoFA['code'] . " - Login successful", 'green') . "\n";
    $response = $loginNoTwoFA['decoded']['data'] ?? null;
    if ($response && isset($response['token'])) {
        echo colorize("✓ JWT token received directly (no OTP required)", 'green') . "\n";
        $jwtToken = $response['token'];
    } elseif ($response && isset($response['requires_otp'])) {
        echo colorize("✗ Received OTP requirement for non-2FA user", 'red') . "\n";
    }
} else {
    echo colorize("✗ HTTP " . $loginNoTwoFA['code'], 'red') . "\n";
}

// Test 5: Update user 2FA status
log_test("TEST 5: Update 2FA status via PATCH /users/{id}/two-factor", "PATCH", "/users/1/two-factor");
$updateTwoFactor = make_request('PATCH', '/users/1/two-factor', [
    'twoFactorEnabled' => true
], $jwtToken);

if ($updateTwoFactor['code'] === 200) {
    echo colorize("✓ HTTP " . $updateTwoFactor['code'] . " - 2FA status updated", 'green') . "\n";
    $response = $updateTwoFactor['decoded']['data'] ?? null;
    if ($response && isset($response['twoFactorEnabled'])) {
        echo "  twoFactorEnabled: " . ($response['twoFactorEnabled'] ? 'true' : 'false') . "\n";
    }
} else {
    echo colorize("✗ HTTP " . $updateTwoFactor['code'], 'red') . "\n";
    if (isset($updateTwoFactor['decoded']['message'])) {
        echo "  Error: " . $updateTwoFactor['decoded']['message'] . "\n";
    }
}

// Test 6: Get user profile (should include twoFactorEnabled)
if ($jwtToken) {
    log_test("TEST 6: Get /profile (should include twoFactorEnabled)", "GET", "/profile");
    $profile = make_request('GET', '/profile', null, $jwtToken);

    if ($profile['code'] === 200) {
        echo colorize("✓ HTTP " . $profile['code'] . " - Profile retrieved", 'green') . "\n";
        $userData = $profile['decoded']['data'] ?? null;
        if ($userData && isset($userData['twoFactorEnabled'])) {
            echo colorize("✓ twoFactorEnabled field found in profile", 'green') . "\n";
            echo "  Value: " . ($userData['twoFactorEnabled'] ? 'true' : 'false') . "\n";
        } else {
            echo colorize("✗ twoFactorEnabled field missing", 'red') . "\n";
        }
    } else {
        echo colorize("✗ HTTP " . $profile['code'], 'red') . "\n";
    }
}

echo "\n" . colorize(str_repeat("=", 80), 'cyan') . "\n";
echo colorize("TEST SUMMARY", 'bold') . "\n";
echo "Note: Full OTP testing requires access to email inbox or SMTP configuration.\n";
echo "Configure MAILER_DSN in .env file with your email provider settings.\n";
echo colorize(str_repeat("=", 80), 'cyan') . "\n";
