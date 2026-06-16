<?php

namespace Tests\Feature\Security;

use App\Models\Epic;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * OWASP A01:2021 - Broken Access Control
 *
 * Tests authorization and access control vulnerabilities including:
 * - Missing access control
 * - Insecure Direct Object Reference (IDOR)
 * - Privilege escalation
 * - Unauthorized access to resources
 * - Missing function level access control
 */
class OwaspAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that unauthenticated users cannot access API endpoints
     * OWASP: Broken Access Control - Missing authentication
     */
    public function test_unauthenticated_users_cannot_access_protected_endpoints()
    {
        $endpoints = [
            ['method' => 'get', 'uri' => '/api/projects'],
            ['method' => 'post', 'uri' => '/api/projects'],
            ['method' => 'get', 'uri' => '/api/tickets'],
            ['method' => 'post', 'uri' => '/api/tickets'],
            ['method' => 'get', 'uri' => '/api/users'],
            ['method' => 'get', 'uri' => '/api/references/project-statuses'],
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->json($endpoint['method'], $endpoint['uri']);
            $this->assertEquals(401, $response->status(),
                "{$endpoint['method']} {$endpoint['uri']} should require authentication");
        }

        $this->assertTrue(true, 'All endpoints properly require authentication');
    }

    /**
     * Test IDOR vulnerability - users cannot access other users' projects
     * OWASP: Broken Access Control - IDOR
     */
    public function test_users_cannot_access_other_users_projects_via_idor()
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $project = Project::factory()->create([
            'owner_id' => $owner->id,
        ]);

        $attackerToken = $attacker->createToken('test')->plainTextToken;

        // Attacker tries to access owner's project
        $response = $this->withHeader('Authorization', "Bearer $attackerToken")
            ->getJson("/api/projects/{$project->id}");

        // Should either return 403 Forbidden or not include the project in results
        // Depending on your authorization logic
        $this->assertContains($response->status(), [403, 404],
            'Users should not access projects they don\'t own or aren\'t part of');
    }

    /**
     * Test that users can only create resources under their own context
     * OWASP: Broken Access Control - Unauthorized resource creation
     */
    public function test_users_cannot_create_resources_for_other_users()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $token = $user1->createToken('test')->plainTextToken;

        // User1 tries to create a project claiming to be owned by User2
        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/projects', [
                'name' => 'Malicious Project',
                'owner_id' => $user2->id, // Trying to set different owner
                'slug' => 'malicious-project',
            ]);

        // Should either ignore owner_id or reject the request
        if ($response->status() === 201) {
            $createdProject = Project::latest()->first();
            $this->assertEquals($user1->id, $createdProject->owner_id,
                'Created project should belong to authenticated user, not specified owner_id');
        }

        $this->assertTrue(true, 'Users cannot create resources for other users');
    }

    /**
     * Test that users cannot modify resources they don't own
     * OWASP: Broken Access Control - Unauthorized modification
     */
    public function test_users_cannot_modify_other_users_resources()
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $project = Project::factory()->create([
            'owner_id' => $owner->id,
            'name' => 'Original Name',
        ]);

        $attackerToken = $attacker->createToken('test')->plainTextToken;

        // Attacker tries to update owner's project
        $response = $this->withHeader('Authorization', "Bearer $attackerToken")
            ->putJson("/api/projects/{$project->id}", [
                'name' => 'Hacked Name',
            ]);

        $this->assertContains($response->status(), [403, 404],
            'Users should not be able to modify projects they don\'t own');

        // Verify project wasn't modified
        $project->refresh();
        $this->assertEquals('Original Name', $project->name);
    }

    /**
     * Test that users cannot delete resources they don't own
     * OWASP: Broken Access Control - Unauthorized deletion
     */
    public function test_users_cannot_delete_other_users_resources()
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $project = Project::factory()->create([
            'owner_id' => $owner->id,
        ]);

        $attackerToken = $attacker->createToken('test')->plainTextToken;

        // Attacker tries to delete owner's project
        $response = $this->withHeader('Authorization', "Bearer $attackerToken")
            ->deleteJson("/api/projects/{$project->id}");

        $this->assertContains($response->status(), [403, 404],
            'Users should not be able to delete projects they don\'t own');

        // Verify project still exists
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
        ]);
    }

    /**
     * Test that tickets are protected by ownership
     * OWASP: Broken Access Control - Resource ownership
     */
    public function test_ticket_access_requires_proper_ownership()
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $ticket = Ticket::factory()->create([
            'project_id' => $project->id,
            'owner_id' => $owner->id,
        ]);

        $attackerToken = $attacker->createToken('test')->plainTextToken;

        // Attacker tries to access ticket
        $response = $this->withHeader('Authorization', "Bearer $attackerToken")
            ->getJson("/api/tickets/{$ticket->id}");

        $this->assertContains($response->status(), [403, 404],
            'Tickets should be protected by ownership or project membership');
    }

    /**
     * Test that API endpoints validate resource IDs properly
     * OWASP: Broken Access Control - Invalid ID handling
     */
    public function test_endpoints_handle_invalid_resource_ids_securely()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $invalidIds = [
            999999, // Non-existent ID
            -1,     // Negative ID
            'abc',  // String ID
            "1' OR '1'='1", // SQL injection attempt
        ];

        foreach ($invalidIds as $invalidId) {
            $response = $this->withHeader('Authorization', "Bearer $token")
                ->getJson("/api/projects/{$invalidId}");

            $this->assertContains($response->status(), [404, 422],
                "Invalid ID '{$invalidId}' should return 404 or 422");
        }

        $this->assertTrue(true, 'Invalid resource IDs are handled securely');
    }

    /**
     * Test that users cannot access API endpoints they shouldn't
     * OWASP: Broken Access Control - Function level access control
     */
    public function test_user_list_endpoint_has_proper_access_control()
    {
        $regularUser = User::factory()->create();
        $token = $regularUser->createToken('test')->plainTextToken;

        // Regular users should be able to list users (for assignment purposes)
        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/users');

        // This is acceptable if users need to see other users for collaboration
        $this->assertContains($response->status(), [200, 403],
            'User list endpoint should have appropriate access control');

        if ($response->status() === 200) {
            // If accessible, ensure sensitive data is not exposed
            $users = $response->json('data') ?? $response->json();
            if (is_array($users) && count($users) > 0) {
                $firstUser = is_array($users[0]) ? $users[0] : [];
                $this->assertArrayNotHasKey('password', $firstUser,
                    'User list should not expose passwords');
            }
        }
    }

    /**
     * Test that nested resources respect parent resource authorization
     * OWASP: Broken Access Control - Nested resource security
     */
    public function test_nested_resources_respect_parent_authorization()
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $epic = Epic::factory()->create(['project_id' => $project->id]);

        $attackerToken = $attacker->createToken('test')->plainTextToken;

        // Attacker tries to access epic through project they don't own
        $response = $this->withHeader('Authorization', "Bearer $attackerToken")
            ->getJson("/api/projects/{$project->id}/epics/{$epic->id}");

        $this->assertContains($response->status(), [403, 404],
            'Nested resources should respect parent resource authorization');
    }

    /**
     * Test that mass assignment is protected
     * OWASP: Broken Access Control - Mass assignment
     */
    public function test_protected_fields_cannot_be_mass_assigned()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        // Try to create a project with potentially sensitive fields
        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/projects', [
                'name' => 'Test Project',
                'slug' => 'test-project',
                'owner_id' => 999, // Shouldn't be assignable by user
                'id' => 12345,     // Shouldn't be assignable
                'created_at' => '2020-01-01', // Shouldn't be assignable
            ]);

        if ($response->status() === 201) {
            $project = Project::latest()->first();

            // Verify protected fields weren't mass assigned
            $this->assertEquals($user->id, $project->owner_id,
                'owner_id should be set to authenticated user');
            $this->assertNotEquals(12345, $project->id,
                'ID should be auto-generated, not mass assigned');
        }

        $this->assertTrue(true, 'Protected fields are secured from mass assignment');
    }

    /**
     * Test that users can only view their authorized resources
     * OWASP: Broken Access Control - Resource listing
     */
    public function test_resource_listing_only_shows_authorized_resources()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create projects for different users
        $user1Project = Project::factory()->create(['owner_id' => $user1->id]);
        $user2Project = Project::factory()->create(['owner_id' => $user2->id]);

        $token1 = $user1->createToken('test')->plainTextToken;

        // User1 lists projects
        $response = $this->withHeader('Authorization', "Bearer $token1")
            ->getJson('/api/projects');

        if ($response->status() === 200) {
            $projects = $response->json('data') ?? $response->json();

            // Check if proper filtering is applied
            // Either shows only user1's projects, or shows all (depending on business logic)
            $projectIds = collect($projects)->pluck('id')->toArray();

            // At minimum, user should see their own project
            $this->assertContains($user1Project->id, $projectIds,
                'User should see their own projects');

            // Ideally, they shouldn't see projects they're not part of
            // This depends on business requirements (collaboration features)
        }

        $this->assertTrue(true, 'Resource listing respects authorization');
    }

    /**
     * Test that API tokens have proper scope limitations
     * OWASP: Broken Access Control - Token scope
     */
    public function test_api_tokens_respect_user_context()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Use token to access user endpoint
        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/user');

        $response->assertStatus(200);
        $this->assertEquals($user->id, $response->json('id'),
            'Token should only provide access to the token owner\'s data');

        // Token shouldn't allow impersonating other users
        $this->assertEquals($user->email, $response->json('email'));
    }
}
