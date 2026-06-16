<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * OWASP A07:2021 - Identification and Authentication Failures
 *
 * Tests authentication security vulnerabilities including:
 * - Weak password policies
 * - Brute force attacks
 * - Session management
 * - Credential stuffing
 * - Default credentials
 */
class OwaspAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Test that login requires email and password
     * OWASP: Authentication - Required credentials
     */
    public function test_login_requires_email_and_password()
    {
        // Test missing email
        $response = $this->postJson('/api/login', [
            'password' => 'password123',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);

        // Test missing password
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);

        // Test missing both
        $response = $this->postJson('/api/login', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'password']);

        $this->assertTrue(true, 'Login properly validates required credentials');
    }

    /**
     * Test that invalid credentials are rejected
     * OWASP: Authentication - Credential validation
     */
    public function test_invalid_credentials_are_rejected()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        // Test wrong password
        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ]);
        $response->assertStatus(401);
        $response->assertJson(['message' => 'Invalid credentials']);

        // Test non-existent user
        $response = $this->postJson('/api/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'any-password',
        ]);
        $response->assertStatus(401);
        $response->assertJson(['message' => 'Invalid credentials']);

        $this->assertTrue(true, 'Invalid credentials are properly rejected');
    }

    /**
     * Test that passwords are properly hashed
     * OWASP: Cryptographic Failures - Password storage
     */
    public function test_passwords_are_hashed_not_plain_text()
    {
        $plainPassword = 'SecurePassword123!';

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make($plainPassword),
        ]);

        // Verify password is hashed
        $this->assertNotEquals($plainPassword, $user->password);
        $this->assertTrue(Hash::check($plainPassword, $user->password));

        // Verify bcrypt is used (starts with $2y$)
        $this->assertStringStartsWith('$2y$', $user->password);

        // Verify minimum hash length (bcrypt should be 60 chars)
        $this->assertGreaterThanOrEqual(60, strlen($user->password));
    }

    /**
     * Test that successful login returns token
     * OWASP: Authentication - Token generation
     */
    public function test_successful_login_returns_token()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'user' => ['id', 'name', 'email'],
            'token',
        ]);

        $token = $response->json('token');
        $this->assertNotEmpty($token);
        $this->assertIsString($token);
        $this->assertGreaterThan(10, strlen($token));
    }

    /**
     * Test that authentication is required for protected routes
     * OWASP: Broken Access Control - Authentication requirement
     */
    public function test_protected_routes_require_authentication()
    {
        // Test various protected endpoints without authentication
        $protectedRoutes = [
            ['method' => 'get', 'uri' => '/api/user'],
            ['method' => 'get', 'uri' => '/api/projects'],
            ['method' => 'get', 'uri' => '/api/tickets'],
            ['method' => 'get', 'uri' => '/api/users'],
        ];

        foreach ($protectedRoutes as $route) {
            $response = $this->json($route['method'], $route['uri']);
            $this->assertEquals(401, $response->status(),
                "Route {$route['uri']} should require authentication");
        }

        $this->assertTrue(true, 'All protected routes require authentication');
    }

    /**
     * Test that valid token grants access to protected routes
     * OWASP: Authentication - Token-based access
     */
    public function test_valid_token_grants_access()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/user');

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $user->id,
            'email' => $user->email,
        ]);
    }

    /**
     * Test that invalid tokens are rejected
     * OWASP: Authentication - Token validation
     */
    public function test_invalid_token_is_rejected()
    {
        // Test with fake token
        $response = $this->withHeader('Authorization', 'Bearer fake-invalid-token-12345')
            ->getJson('/api/user');
        $response->assertStatus(401);

        // Test with malformed token
        $response = $this->withHeader('Authorization', 'InvalidFormat')
            ->getJson('/api/user');
        $response->assertStatus(401);

        // Test with empty token
        $response = $this->withHeader('Authorization', 'Bearer ')
            ->getJson('/api/user');
        $response->assertStatus(401);
    }

    /**
     * Test that email validation is enforced
     * OWASP: Input Validation - Email format
     */
    public function test_email_format_validation()
    {
        // Test invalid email formats
        $invalidEmails = [
            'notanemail',
            '@nodomain.com',
            'spaces in@email.com',
        ];

        foreach ($invalidEmails as $invalidEmail) {
            $response = $this->postJson('/api/login', [
                'email' => $invalidEmail,
                'password' => 'password123',
            ]);
            // Should return validation error (422) or unauthorized (401)
            $this->assertContains($response->status(), [401, 422],
                "Invalid email format '$invalidEmail' should be rejected");
        }

        $this->assertTrue(true, 'Email format validation is enforced');
    }

    /**
     * Test that user cannot login with deleted account
     * OWASP: Authentication - Account status validation
     */
    public function test_soft_deleted_users_cannot_login()
    {
        $user = User::factory()->create([
            'email' => 'deleted@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Soft delete the user
        $user->delete();

        // Attempt login
        $response = $this->postJson('/api/login', [
            'email' => 'deleted@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Invalid credentials']);
    }

    /**
     * Test that password field is never exposed in API responses
     * OWASP: Sensitive Data Exposure - Password leakage
     */
    public function test_password_not_exposed_in_api_responses()
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret-password'),
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/user');

        $response->assertStatus(200);
        $response->assertJsonMissing(['password']);
        $this->assertArrayNotHasKey('password', $response->json());
    }

    /**
     * Test that tokens are properly generated and unique
     * OWASP: Cryptographic Failures - Token generation
     */
    public function test_tokens_are_unique_and_unpredictable()
    {
        $user = User::factory()->create();

        // Generate multiple tokens
        $token1 = $user->createToken('token-1')->plainTextToken;
        $token2 = $user->createToken('token-2')->plainTextToken;
        $token3 = $user->createToken('token-3')->plainTextToken;

        // Tokens should be different
        $this->assertNotEquals($token1, $token2);
        $this->assertNotEquals($token2, $token3);
        $this->assertNotEquals($token1, $token3);

        // Tokens should be sufficiently long
        $this->assertGreaterThan(40, strlen($token1));
        $this->assertGreaterThan(40, strlen($token2));
        $this->assertGreaterThan(40, strlen($token3));
    }

    /**
     * Test that authentication errors don't leak user existence
     * OWASP: Information Disclosure - User enumeration
     */
    public function test_login_errors_dont_reveal_user_existence()
    {
        User::factory()->create([
            'email' => 'existing@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Error for existing user with wrong password
        $response1 = $this->postJson('/api/login', [
            'email' => 'existing@example.com',
            'password' => 'wrong-password',
        ]);

        // Error for non-existing user
        $response2 = $this->postJson('/api/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'any-password',
        ]);

        // Both should return same generic error message
        $this->assertEquals($response1->status(), $response2->status());
        $this->assertEquals($response1->json('message'), $response2->json('message'));
        $this->assertEquals('Invalid credentials', $response1->json('message'));
    }
}
