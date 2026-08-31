<?php

/**
 * Validation tests pour la double authentification 2FA
 * Vérifie que twoFactorEnabled accepte uniquement booléens true/false
 */

namespace App\Tests\Validation;

use App\Entity\User;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TwoFactorValidationTest
{
    /**
     * Test: twoFactorEnabled doit être un booléen
     *
     * Cas valides:
     * - true ✓
     * - false ✓
     *
     * Cas invalides:
     * - "true" (string) ✗
     * - 1 (integer) ✗
     * - "1" (string) ✗
     * - null ✓ (optionnel, défaut false)
     */
    public function validateTwoFactorField(ValidatorInterface $validator, $value): array
    {
        $user = new User();

        // Validation basée sur le type fourni
        if (is_bool($value)) {
            $user->setTwoFactorEnabled($value);
            $errors = $validator->validate($user);
            return [
                'valid' => count($errors) === 0,
                'errors' => $this->formatErrors($errors),
                'value' => $value,
                'type' => gettype($value)
            ];
        } else {
            return [
                'valid' => false,
                'errors' => ['Type error: twoFactorEnabled must be boolean, got ' . gettype($value)],
                'value' => $value,
                'type' => gettype($value)
            ];
        }
    }

    private function formatErrors($errors): array
    {
        $messages = [];
        foreach ($errors as $violation) {
            $messages[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
        }
        return $messages;
    }
}

// Tests unitaires pour validation payload API

class ApiPayloadValidationTest
{
    /**
     * Scénarios de création utilisateur
     */
    public function testCreateUserPayloads(): void
    {
        echo "=== Test: Création d'utilisateur avec twoFactorEnabled ===\n\n";

        $testCases = [
            [
                'description' => 'Valide: twoFactorEnabled = true',
                'payload' => [
                    'firstName' => 'John',
                    'lastName' => 'Doe',
                    'email' => 'john@example.com',
                    'password' => 'SecurePass123!',
                    'twoFactorEnabled' => true
                ],
                'expected' => 'valid'
            ],
            [
                'description' => 'Valide: twoFactorEnabled = false',
                'payload' => [
                    'firstName' => 'Jane',
                    'lastName' => 'Smith',
                    'email' => 'jane@example.com',
                    'password' => 'SecurePass123!',
                    'twoFactorEnabled' => false
                ],
                'expected' => 'valid'
            ],
            [
                'description' => 'Valide: twoFactorEnabled omis (défaut false)',
                'payload' => [
                    'firstName' => 'Bob',
                    'lastName' => 'Johnson',
                    'email' => 'bob@example.com',
                    'password' => 'SecurePass123!'
                ],
                'expected' => 'valid',
                'note' => 'Doit défaut à false'
            ],
            [
                'description' => 'Invalide: twoFactorEnabled = "true" (string)',
                'payload' => [
                    'firstName' => 'Alice',
                    'lastName' => 'Williams',
                    'email' => 'alice@example.com',
                    'password' => 'SecurePass123!',
                    'twoFactorEnabled' => 'true'
                ],
                'expected' => 'invalid',
                'note' => 'Type string non accepté'
            ],
            [
                'description' => 'Invalide: twoFactorEnabled = 1 (integer)',
                'payload' => [
                    'firstName' => 'Charlie',
                    'lastName' => 'Brown',
                    'email' => 'charlie@example.com',
                    'password' => 'SecurePass123!',
                    'twoFactorEnabled' => 1
                ],
                'expected' => 'invalid',
                'note' => 'Type integer non accepté'
            ],
        ];

        foreach ($testCases as $index => $case) {
            echo "Test " . ($index + 1) . ": " . $case['description'] . "\n";
            if (isset($case['note'])) {
                echo "  Note: " . $case['note'] . "\n";
            }
            echo "  Expected: " . $case['expected'] . "\n";
            if (isset($case['payload']['twoFactorEnabled'])) {
                echo "  Value: " . (is_bool($case['payload']['twoFactorEnabled']) ?
                    ($case['payload']['twoFactorEnabled'] ? 'true' : 'false') :
                    "'" . $case['payload']['twoFactorEnabled'] . "'") . "\n";
            } else {
                echo "  Value: (omis, défaut expected)\n";
            }
            echo "\n";
        }
    }

    /**
     * Scénarios de modification utilisateur
     */
    public function testUpdateUserPayloads(): void
    {
        echo "\n=== Test: Modification d'utilisateur (twoFactorEnabled) ===\n\n";

        $testCases = [
            [
                'description' => 'Activer 2FA (false → true)',
                'payload' => ['twoFactorEnabled' => true],
                'currentValue' => false,
                'expected' => 'valid'
            ],
            [
                'description' => 'Désactiver 2FA (true → false)',
                'payload' => ['twoFactorEnabled' => false],
                'currentValue' => true,
                'expected' => 'valid',
                'note' => 'Codes OTP doivent être nettoyés'
            ],
            [
                'description' => 'Maintenir 2FA activée',
                'payload' => ['twoFactorEnabled' => true],
                'currentValue' => true,
                'expected' => 'valid'
            ],
            [
                'description' => 'Combiné avec autres champs',
                'payload' => [
                    'firstName' => 'Updated',
                    'lastName' => 'Name',
                    'twoFactorEnabled' => true
                ],
                'currentValue' => false,
                'expected' => 'valid'
            ],
        ];

        foreach ($testCases as $index => $case) {
            echo "Test " . ($index + 1) . ": " . $case['description'] . "\n";
            echo "  Changement: " . ($case['currentValue'] ? 'true' : 'false') .
                 " → " . ($case['payload']['twoFactorEnabled'] ? 'true' : 'false') . "\n";
            if (isset($case['note'])) {
                echo "  Note: " . $case['note'] . "\n";
            }
            echo "  Expected: " . $case['expected'] . "\n";
            echo "\n";
        }
    }

    /**
     * Scénarios d'activation/désactivation 2FA
     */
    public function testTwoFactorEndpointPayloads(): void
    {
        echo "\n=== Test: Endpoint PATCH /users/{id}/two-factor ===\n\n";

        $testCases = [
            [
                'description' => 'Valide: Activer 2FA',
                'payload' => ['twoFactorEnabled' => true],
                'expected' => 'valid'
            ],
            [
                'description' => 'Valide: Désactiver 2FA',
                'payload' => ['twoFactorEnabled' => false],
                'expected' => 'valid'
            ],
            [
                'description' => 'Invalide: Payload vide',
                'payload' => [],
                'expected' => 'invalid',
                'note' => 'twoFactorEnabled requis'
            ],
            [
                'description' => 'Invalide: twoFactorEnabled manquant',
                'payload' => ['otherField' => true],
                'expected' => 'invalid',
                'note' => 'twoFactorEnabled requis'
            ],
            [
                'description' => 'Invalide: Type string',
                'payload' => ['twoFactorEnabled' => 'true'],
                'expected' => 'invalid',
                'note' => 'Doit être booléen'
            ],
            [
                'description' => 'Invalide: Valeur null',
                'payload' => ['twoFactorEnabled' => null],
                'expected' => 'invalid',
                'note' => 'Ne peut pas être null'
            ],
        ];

        foreach ($testCases as $index => $case) {
            echo "Test " . ($index + 1) . ": " . $case['description'] . "\n";
            echo "  Payload: " . json_encode($case['payload']) . "\n";
            if (isset($case['note'])) {
                echo "  Note: " . $case['note'] . "\n";
            }
            echo "  Expected: " . $case['expected'] . "\n";
            echo "\n";
        }
    }
}

// Exemple d'utilisation
if (php_sapi_name() === 'cli') {
    $test = new ApiPayloadValidationTest();
    $test->testCreateUserPayloads();
    $test->testUpdateUserPayloads();
    $test->testTwoFactorEndpointPayloads();
}
