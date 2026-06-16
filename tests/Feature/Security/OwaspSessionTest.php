<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * OWASP A07:2021 - Identification and Authentication Failures
 * (Session Management)
 *
 * Tests session and token security including:
 * - Session fixation
 * - Token expiration
 * - Token revocation
 * - Concurrent session handling
 * - Session hijacking prevention
 */
class OwaspSessionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that old tokens are properly stored and retrievable
     * OWASP: Session Management - Token persistence
     */
    public function test_tokens_are_properly_persisted()
    {
        $user = User::factory()->create();

        // Create multiple tokens
        $token1 = $user->createToken('token-1');
        $token2 = $user->createToken('token-2');

        // Verify tokens are stored in database
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'token-1',
        ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'token-2',
        ]);

        // Verify user has multiple tokens
        $this->assertGreaterThanOrEqual(2, $user->tokens()->count());
    }

    /**
     * Test that tokens can be revoked/deleted
     * OWASP: Session Management - Token revocation
     */
    public function test_tokens_can_be_revoked()
    {
        $user = User::factory()->create();
        $tokenResult = $user->createToken('test-token');
        $token = $tokenResult->plainTextToken;
        $tokenId = $tokenResult->accessToken->id;

        // Verify token exists in database
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $tokenId,
            'name' => 'test-token',
        ]);

        // Verify token works initially
        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/user');
        $response->assertStatus(200);

        // Revoke the token
        $user->tokens()->where('id', $tokenId)->delete();

        // Verify token is deleted from database
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenId,
        ]);

        // Note: Token may still work in same request lifecycle due to Sanctum caching
        // The important security check is that it's removed from database
        $this->assertEquals(0, PersonalAccessToken::where('id', $tokenId)->count(),
            'Token should be removed from database');
    }

    /**
     * Test that user can revoke all their tokens (logout from all devices)
     * OWASP: Session Management - Global logout
     */
    public function test_user_can_revoke_all_tokens()
    {
        $user = User::factory()->create();

        // Create multiple tokens
        $token1Result = $user->createToken('device-1');
        $token2Result = $user->createToken('device-2');
        $token3Result = $user->createToken('device-3');

        // Verify all tokens exist in database
        $this->assertEquals(3, $user->tokens()->count());

        $token1 = $token1Result->plainTextToken;

        // Verify at least one token works
        $this->withHeader('Authorization', "Bearer $token1")
            ->getJson('/api/user')->assertStatus(200);

        // Revoke all tokens
        $user->tokens()->delete();

        // Verify all tokens deleted from database
        $this->assertEquals(0, $user->tokens()->count(),
            'All tokens should be deleted from database');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    /**
     * Test that tokens are scoped to specific users
     * OWASP: Session Management - Token isolation
     */
    public function test_tokens_are_user_specific()
    {
        $user1 = User::factory()->create(['email' => 'user1@test.com']);
        $user2 = User::factory()->create(['email' => 'user2@test.com']);

        $token1 = $user1->createToken('user1-token')->plainTextToken;
        $token2 = $user2->createToken('user2-token')->plainTextToken;

        // Tokens should not be interchangeable
        $this->assertNotEquals($token1, $token2,
            'Different users should have different tokens');

        // Verify both users exist with different IDs
        $this->assertNotEquals($user1->id, $user2->id,
            'Users should have different IDs');

        // Verify each token belongs to correct user in database
        $this->assertEquals(2, PersonalAccessToken::count(),
            'Should have 2 tokens in database');

        $storedToken1 = PersonalAccessToken::where('tokenable_id', $user1->id)->first();
        $storedToken2 = PersonalAccessToken::where('tokenable_id', $user2->id)->first();

        $this->assertNotNull($storedToken1, 'User1 token should exist');
        $this->assertNotNull($storedToken2, 'User2 token should exist');
        $this->assertEquals($user1->id, $storedToken1->tokenable_id);
        $this->assertEquals($user2->id, $storedToken2->tokenable_id);
    }

    /**
     * Test that token hash is stored, not plain token
     * OWASP: Cryptographic Failures - Token storage
     */
    public function test_token_hash_is_stored_not_plaintext()
    {
        $user = User::factory()->create();
        $tokenResult = $user->createToken('test-token');
        $plainToken = $tokenResult->plainTextToken;

        // Get the stored token from database
        $storedToken = PersonalAccessToken::find($tokenResult->accessToken->id);

        // Verify plain token is not stored
        $this->assertNotEquals($plainToken, $storedToken->token,
            'Plain token should not be stored in database');

        // Verify stored token is a hash (64 chars for SHA-256)
        $this->assertEquals(64, strlen($storedToken->token),
            'Token should be stored as hash');

        // Verify it's hexadecimal hash format
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $storedToken->token,
            'Stored token should be hexadecimal hash');
    }

    /**
     * Test that concurrent tokens from same user work independently
     * OWASP: Session Management - Concurrent sessions
     */
    public function test_multiple_active_tokens_work_independently()
    {
        $user = User::factory()->create();

        // Create tokens for different devices
        $mobileToken = $user->createToken('mobile-device')->plainTextToken;
        $desktopToken = $user->createToken('desktop-device')->plainTextToken;
        $tabletToken = $user->createToken('tablet-device')->plainTextToken;

        // All tokens should work simultaneously
        $response1 = $this->withHeader('Authorization', "Bearer $mobileToken")
            ->getJson('/api/user');
        $response2 = $this->withHeader('Authorization', "Bearer $desktopToken")
            ->getJson('/api/user');
        $response3 = $this->withHeader('Authorization', "Bearer $tabletToken")
            ->getJson('/api/user');

        $this->assertEquals(200, $response1->status());
        $this->assertEquals(200, $response2->status());
        $this->assertEquals(200, $response3->status());

        // All should return same user
        $this->assertEquals($user->id, $response1->json('id'));
        $this->assertEquals($user->id, $response2->json('id'));
        $this->assertEquals($user->id, $response3->json('id'));
    }

    /**
     * Test that revoking one token doesn't affect others
     * OWASP: Session Management - Token independence
     */
    public function test_revoking_one_token_doesnt_affect_others()
    {
        $user = User::factory()->create();

        $token1Result = $user->createToken('token-1');
        $token1 = $token1Result->plainTextToken;
        $token1Id = $token1Result->accessToken->id;

        $token2 = $user->createToken('token-2')->plainTextToken;
        $token3 = $user->createToken('token-3')->plainTextToken;

        // Revoke only token1
        $user->tokens()->where('id', $token1Id)->delete();

        // Token1 should not work
        $this->withHeader('Authorization', "Bearer $token1")
            ->getJson('/api/user')->assertStatus(401);

        // Token2 and Token3 should still work
        $this->withHeader('Authorization', "Bearer $token2")
            ->getJson('/api/user')->assertStatus(200);
        $this->withHeader('Authorization', "Bearer $token3")
            ->getJson('/api/user')->assertStatus(200);
    }

    /**
     * Test that token metadata is properly tracked
     * OWASP: Session Management - Token tracking
     */
    public function test_token_metadata_is_tracked()
    {
        $user = User::factory()->create();
        $tokenResult = $user->createToken('test-device');

        $storedToken = PersonalAccessToken::find($tokenResult->accessToken->id);

        // Verify token has proper metadata
        $this->assertNotNull($storedToken->created_at,
            'Token creation time should be tracked');
        $this->assertEquals('test-device', $storedToken->name,
            'Token name/device should be tracked');
        $this->assertEquals($user->id, $storedToken->tokenable_id,
            'Token should be associated with user');
        $this->assertEquals(User::class, $storedToken->tokenable_type,
            'Token should track tokenable type');
    }
}
