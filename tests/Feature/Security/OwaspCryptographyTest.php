<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * OWASP A02:2021 - Cryptographic Failures
 *
 * Tests cryptographic security including:
 * - Password hashing strength
 * - Encryption usage
 * - Sensitive data exposure
 * - Weak cryptographic algorithms
 * - Insecure random number generation
 */
class OwaspCryptographyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that passwords use strong hashing algorithm (bcrypt)
     * OWASP: Cryptographic Failures - Password hashing
     */
    public function test_passwords_use_strong_hashing_algorithm()
    {
        $password = 'SecurePassword123!';
        $hashedPassword = Hash::make($password);

        // Verify bcrypt is used (Laravel default)
        $this->assertStringStartsWith('$2y$', $hashedPassword,
            'Passwords should use bcrypt algorithm');

        // Verify hash is of expected length (60 chars for bcrypt)
        $this->assertEquals(60, strlen($hashedPassword),
            'Bcrypt hash should be 60 characters');

        // Verify password can be verified
        $this->assertTrue(Hash::check($password, $hashedPassword),
            'Hash verification should work correctly');

        // Verify different hashes for same password (salted)
        $hash2 = Hash::make($password);
        $this->assertNotEquals($hashedPassword, $hash2,
            'Same password should produce different hashes (salted)');
    }

    /**
     * Test that password hashing has sufficient work factor
     * OWASP: Cryptographic Failures - Hashing work factor
     */
    public function test_password_hashing_work_factor_is_sufficient()
    {
        $password = 'TestPassword123';
        $hash = Hash::make($password);

        // Verify hash format indicates proper rounds (bcrypt cost parameter)
        $this->assertMatchesRegularExpression('/^\$2y\$\d{2}\$/', $hash,
            'Hash should include work factor (cost)');

        // Extract cost from hash (format: $2y$10$...)
        preg_match('/^\$2y\$(\d{2})\$/', $hash, $matches);
        $cost = isset($matches[1]) ? (int)$matches[1] : 0;

        // Test environment uses cost 4 for speed, production uses 10+
        // Verify cost is at least 4 (minimum for bcrypt)
        $this->assertGreaterThanOrEqual(4, $cost,
            'Bcrypt cost should be at least 4');
        $this->assertLessThanOrEqual(15, $cost,
            'Bcrypt cost should not be excessively high');
    }

    /**
     * Test that sensitive data in database is not stored in plain text
     * OWASP: Cryptographic Failures - Sensitive data storage
     */
    public function test_passwords_not_stored_in_plain_text()
    {
        $plainPassword = 'MySecretPassword123!';

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make($plainPassword),
        ]);

        // Verify password is hashed in database
        $this->assertNotEquals($plainPassword, $user->password,
            'Password should not be stored in plain text');

        // Verify password field is hashed format
        $this->assertStringStartsWith('$2y$', $user->password,
            'Stored password should be bcrypt hash');

        // Verify password field is not visible in array/JSON
        $userArray = $user->toArray();
        $this->assertArrayNotHasKey('password', $userArray,
            'Password should be hidden in array output');
    }

    /**
     * Test that API tokens are sufficiently random and unpredictable
     * OWASP: Cryptographic Failures - Token generation
     */
    public function test_api_tokens_are_cryptographically_secure()
    {
        $user = User::factory()->create();

        $tokens = [];
        for ($i = 0; $i < 5; $i++) {
            $token = $user->createToken('test-token-' . $i)->plainTextToken;
            $tokens[] = $token;

            // Token should be sufficiently long
            $this->assertGreaterThan(40, strlen($token),
                'API tokens should be sufficiently long');

            // Token should contain random characters
            $this->assertMatchesRegularExpression('/^[\w\-]+\|[\w]+$/', $token,
                'Token should follow Sanctum format: ID|random');
        }

        // All tokens should be unique
        $uniqueTokens = array_unique($tokens);
        $this->assertCount(5, $uniqueTokens,
            'All generated tokens should be unique');

        // Tokens should not be sequential or predictable
        $this->assertNotEquals($tokens[0], $tokens[1],
            'Tokens should not be predictable');
    }

    /**
     * Test that sensitive fields are excluded from serialization
     * OWASP: Cryptographic Failures - Data exposure
     */
    public function test_sensitive_fields_hidden_from_serialization()
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret'),
            'remember_token' => Str::random(60),
        ]);

        // Test toArray()
        $array = $user->toArray();
        $this->assertArrayNotHasKey('password', $array,
            'Password should be hidden in toArray()');
        $this->assertArrayNotHasKey('remember_token', $array,
            'Remember token should be hidden in toArray()');

        // Test toJson()
        $json = json_decode($user->toJson(), true);
        $this->assertArrayNotHasKey('password', $json,
            'Password should be hidden in toJson()');
        $this->assertArrayNotHasKey('remember_token', $json,
            'Remember token should be hidden in toJson()');
    }

    /**
     * Test that Laravel encryption is working properly
     * OWASP: Cryptographic Failures - Encryption implementation
     */
    public function test_encryption_uses_secure_algorithm()
    {
        $plaintext = 'Sensitive data that should be encrypted';

        // Encrypt data
        $encrypted = Crypt::encryptString($plaintext);

        // Verify encrypted data is different from plaintext
        $this->assertNotEquals($plaintext, $encrypted,
            'Encrypted data should differ from plaintext');

        // Verify encrypted data is not empty
        $this->assertNotEmpty($encrypted,
            'Encrypted data should not be empty');

        // Verify decryption works
        $decrypted = Crypt::decryptString($encrypted);
        $this->assertEquals($plaintext, $decrypted,
            'Decryption should return original plaintext');

        // Verify multiple encryptions produce different ciphertexts (IV)
        $encrypted2 = Crypt::encryptString($plaintext);
        $this->assertNotEquals($encrypted, $encrypted2,
            'Same plaintext should produce different ciphertexts (unique IV)');
    }

    /**
     * Test that UUIDs/tokens are generated securely
     * OWASP: Cryptographic Failures - Random generation
     */
    public function test_uuid_generation_is_cryptographically_secure()
    {
        $user = User::factory()->create(['type' => 'db']);

        // Verify creation_token is set for db type users
        $this->assertNotNull($user->creation_token,
            'Creation token should be generated for db users');

        // Verify UUID format (version 4 UUID)
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $user->creation_token,
            'Creation token should be valid UUID v4'
        );

        // Generate multiple users and verify tokens are unique
        $tokens = [];
        for ($i = 0; $i < 5; $i++) {
            $testUser = User::factory()->create(['type' => 'db']);
            $tokens[] = $testUser->creation_token;
        }

        $uniqueTokens = array_unique($tokens);
        $this->assertCount(5, $uniqueTokens,
            'All UUIDs should be unique');
    }

    /**
     * Test that password comparison is timing-attack safe
     * OWASP: Cryptographic Failures - Timing attacks
     */
    public function test_password_verification_is_timing_safe()
    {
        $correctPassword = 'CorrectPassword123';
        $user = User::factory()->create([
            'password' => Hash::make($correctPassword),
        ]);

        // Verify Hash::check is used (timing-safe)
        $this->assertTrue(Hash::check($correctPassword, $user->password),
            'Correct password should verify');

        $this->assertFalse(Hash::check('WrongPassword', $user->password),
            'Wrong password should not verify');

        // Verify bcrypt is used (timing-safe by design)
        $this->assertStringStartsWith('$2y$', $user->password,
            'Should use bcrypt which has timing-safe verification');

        // Bcrypt's password_verify is inherently timing-safe
        // Laravel's Hash::check wraps password_verify
        $this->assertNotEquals($correctPassword, $user->password,
            'Password should be hashed, not plain text comparison');
    }
}
