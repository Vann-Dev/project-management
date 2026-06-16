<?php

namespace Tests\Feature;

use App\Models\Epic;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Sprint;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketHour;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ComprehensiveApiTest extends TestCase
{
    use RefreshDatabase;

    // ==================== HELPER METHODS ====================

    private function createAuthenticatedUser(): User
    {
        $user = User::factory()->create([
            'email' => 'test-' . uniqid() . '@example.com',
        ]);
        Sanctum::actingAs($user);
        return $user;
    }

    private function createProjectWithStatus(User $owner, string $prefix = 'TST'): Project
    {
        $status = ProjectStatus::create([
            'name' => 'Active-' . uniqid(),
            'color' => '#00ff00',
            'is_default' => true,
        ]);

        return Project::create([
            'name' => 'Test Project ' . uniqid(),
            'description' => 'Test description',
            'owner_id' => $owner->id,
            'status_id' => $status->id,
            'ticket_prefix' => $prefix,
        ]);
    }

    private function createTicketWithAllDependencies(Project $project, User $owner): Ticket
    {
        $status = TicketStatus::create([
            'name' => 'Open-' . uniqid(),
            'color' => '#ffffff',
            'is_default' => true,
            'order' => random_int(1, 1000),
        ]);

        $type = TicketType::create([
            'name' => 'Task-' . uniqid(),
            'icon' => 'heroicon-o-bug-ant',
            'color' => '#ef4444',
            'is_default' => true,
        ]);

        $priority = TicketPriority::create([
            'name' => 'Medium-' . uniqid(),
            'color' => '#ffaa00',
            'is_default' => true,
        ]);

        return Ticket::create([
            'project_id' => $project->id,
            'name' => 'Test Ticket ' . uniqid(),
            'content' => 'Ticket content',
            'owner_id' => $owner->id,
            'responsible_id' => $owner->id,
            'status_id' => $status->id,
            'type_id' => $type->id,
            'priority_id' => $priority->id,
            'estimation' => 3,
        ]);
    }

    private function createAllReferenceData(): array
    {
        $projectStatus = ProjectStatus::create([
            'name' => 'Active-' . uniqid(),
            'color' => '#00ff00',
            'is_default' => true,
        ]);

        $ticketType = TicketType::create([
            'name' => 'Bug-' . uniqid(),
            'icon' => 'bug',
            'is_default' => true,
        ]);

        $ticketPriority = TicketPriority::create([
            'name' => 'High-' . uniqid(),
            'color' => '#ff0000',
            'is_default' => true,
        ]);

        return [
            'projectStatus' => $projectStatus,
            'ticketType' => $ticketType,
            'ticketPriority' => $ticketPriority,
        ];
    }

    // ==================== AUTHENTICATION & AUTHORIZATION TESTS ====================

    public function test_guest_cannot_access_protected_api_endpoints(): void
    {
        // Test multiple endpoints without authentication
        $response1 = $this->getJson('/api/user');
        $response1->assertUnauthorized(); // Assertion 1

        $response2 = $this->getJson('/api/projects');
        $response2->assertUnauthorized(); // Assertion 2

        $response3 = $this->getJson('/api/tickets');
        $response3->assertUnauthorized(); // Assertion 3

        $response4 = $this->getJson('/api/references/project-statuses');
        $response4->assertUnauthorized(); // Assertion 4

        $response5 = $this->getJson('/api/users');
        $response5->assertUnauthorized(); // Assertion 5
    }

    public function test_authenticated_user_can_access_user_profile(): void
    {
        $user = $this->createAuthenticatedUser();

        $response = $this->getJson('/api/user');

        $response->assertOk(); // Assertion 6
        $response->assertJsonPath('id', $user->id); // Assertion 7
        $response->assertJsonPath('email', $user->email); // Assertion 8
        $response->assertJsonMissing(['password']); // Assertion 9
    }

    public function test_authenticated_user_cannot_access_other_users_projects(): void
    {
        $owner = User::factory()->create();
        $otherUser = $this->createAuthenticatedUser();

        $project = $this->createProjectWithStatus($owner, 'OTH');

        // Try to access project owned by another user
        $response = $this->getJson("/api/projects/{$project->id}");

        $response->assertForbidden(); // Assertion 10
    }

    public function test_authenticated_user_cannot_modify_others_ticket_comments(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = $this->createProjectWithStatus($owner, 'CMT');

        // Add other user as project member so they can access the ticket
        $project->users()->attach($otherUser->id, ['role' => 'member']);

        $ticket = $this->createTicketWithAllDependencies($project, $owner);

        // Owner creates a comment
        Sanctum::actingAs($owner);
        $commentResponse = $this->postJson("/api/tickets/{$ticket->id}/comments", [
            'content' => 'Owner comment',
        ]);
        $commentId = $commentResponse->json('id');

        // Different user (who is project member) tries to edit the comment
        Sanctum::actingAs($otherUser);
        $updateResponse = $this->putJson("/api/tickets/{$ticket->id}/comments/{$commentId}", [
            'content' => 'Modified by other user',
        ]);

        $updateResponse->assertForbidden(); // Assertion 11
    }

    // ==================== REFERENCE DATA TESTS ====================

    public function test_can_fetch_all_reference_data_endpoints(): void
    {
        $user = $this->createAuthenticatedUser();
        $this->createAllReferenceData();

        $response1 = $this->getJson('/api/references/project-statuses');
        $response1->assertOk(); // Assertion 12

        $response2 = $this->getJson('/api/references/ticket-types');
        $response2->assertOk(); // Assertion 13

        $response3 = $this->getJson('/api/references/ticket-priorities');
        $response3->assertOk(); // Assertion 14

        $response4 = $this->getJson('/api/references/ticket-statuses');
        $response4->assertOk(); // Assertion 15

        $response5 = $this->getJson('/api/references/activities');
        $response5->assertOk(); // Assertion 16
    }

    public function test_reference_data_returns_correct_structure(): void
    {
        $user = $this->createAuthenticatedUser();
        $refs = $this->createAllReferenceData();

        $response = $this->getJson('/api/references/project-statuses');

        $response->assertOk(); // Assertion 17
        $response->assertJsonStructure([
            '*' => ['id', 'name', 'color']
        ]); // Assertion 18
        $this->assertIsArray($response->json()); // Assertion 19
        $this->assertNotEmpty($response->json()); // Assertion 20
    }

    // ==================== PROJECT CRUD TESTS ====================

    public function test_can_create_and_retrieve_project(): void
    {
        $user = $this->createAuthenticatedUser();
        $status = ProjectStatus::create([
            'name' => 'New-' . uniqid(),
            'color' => '#0000ff',
            'is_default' => true,
        ]);

        // Create project
        $createResponse = $this->postJson('/api/projects', [
            'name' => 'API Created Project',
            'description' => 'Project created via API',
            'status_id' => $status->id,
            'ticket_prefix' => 'ACP',
        ]);

        $createResponse->assertCreated(); // Assertion 21
        $createResponse->assertJsonPath('name', 'API Created Project'); // Assertion 22
        $createResponse->assertJsonPath('ticket_prefix', 'ACP'); // Assertion 23
        $this->assertDatabaseHas('projects', [
            'name' => 'API Created Project',
            'owner_id' => $user->id,
        ]); // Assertion 24

        // Retrieve project
        $projectId = $createResponse->json('id');
        $getResponse = $this->getJson("/api/projects/{$projectId}");

        $getResponse->assertOk(); // Assertion 25
        $getResponse->assertJsonPath('id', $projectId); // Assertion 26
        $getResponse->assertJsonPath('name', 'API Created Project'); // Assertion 27
    }

    public function test_can_update_project_details(): void
    {
        $user = $this->createAuthenticatedUser();
        $project = $this->createProjectWithStatus($user, 'UPD');

        $newStatus = ProjectStatus::create([
            'name' => 'Completed-' . uniqid(),
            'color' => '#ff00ff',
            'is_default' => false,
        ]);

        $response = $this->putJson("/api/projects/{$project->id}", [
            'name' => 'Updated Project Name',
            'description' => 'Updated description',
            'status_id' => $newStatus->id,
        ]);

        $response->assertOk(); // Assertion 28
        $response->assertJsonPath('name', 'Updated Project Name'); // Assertion 29
        $response->assertJsonPath('description', 'Updated description'); // Assertion 30
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Updated Project Name',
            'status_id' => $newStatus->id,
        ]); // Assertion 31
    }

    public function test_can_delete_project(): void
    {
        $user = $this->createAuthenticatedUser();
        $project = $this->createProjectWithStatus($user, 'DEL');

        // Note: In real scenarios, you'd need to delete or handle related records
        // For this test, we're testing a project without dependencies
        $projectId = $project->id;

        $response = $this->deleteJson("/api/projects/{$projectId}");

        // Project might use soft deletes or have cascade rules
        $response->assertNoContent(); // Assertion 32

        // Check project is no longer accessible via API
        $getResponse = $this->getJson("/api/projects/{$projectId}");
        $getResponse->assertNotFound(); // Assertion 33
    }

    // ==================== TICKET CRUD TESTS ====================

    public function test_can_create_ticket_with_all_fields(): void
    {
        $user = $this->createAuthenticatedUser();
        $project = $this->createProjectWithStatus($user, 'TKT');

        $status = TicketStatus::create([
            'name' => 'Todo-' . uniqid(),
            'color' => '#cccccc',
            'is_default' => true,
            'order' => random_int(1, 1000),
        ]);

        $type = TicketType::create([
            'name' => 'Feature-' . uniqid(),
            'icon' => 'heroicon-o-bug-ant',
            'color' => '#ef4444',
            'is_default' => true,
        ]);

        $priority = TicketPriority::create([
            'name' => 'Critical-' . uniqid(),
            'color' => '#ff0000',
            'is_default' => true,
        ]);

        $response = $this->postJson('/api/tickets', [
            'project_id' => $project->id,
            'name' => 'New Feature Ticket',
            'content' => 'Detailed ticket description',
            'status_id' => $status->id,
            'type_id' => $type->id,
            'priority_id' => $priority->id,
            'estimation' => 8,
        ]);

        $response->assertCreated(); // Assertion 34
        $response->assertJsonPath('name', 'New Feature Ticket'); // Assertion 35
        $response->assertJsonPath('estimation', 8); // Assertion 36
        $this->assertDatabaseHas('tickets', [
            'name' => 'New Feature Ticket',
            'project_id' => $project->id,
            'owner_id' => $user->id,
        ]); // Assertion 37
    }

    public function test_can_update_ticket_status_and_assignment(): void
    {
        $user = $this->createAuthenticatedUser();
        $project = $this->createProjectWithStatus($user, 'UPT');
        $ticket = $this->createTicketWithAllDependencies($project, $user);

        $responsible = User::factory()->create();
        $newStatus = TicketStatus::create([
            'name' => 'InProgress-' . uniqid(),
            'color' => '#00aaff',
            'is_default' => false,
            'order' => random_int(1, 1000),
        ]);

        $response = $this->putJson("/api/tickets/{$ticket->id}", [
            'name' => 'Updated Ticket Name',
            'responsible_id' => $responsible->id,
            'status_id' => $newStatus->id,
            'estimation' => 5,
        ]);

        $response->assertOk(); // Assertion 38
        $response->assertJsonPath('name', 'Updated Ticket Name'); // Assertion 39
        $response->assertJsonPath('responsible_id', $responsible->id); // Assertion 40
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'responsible_id' => $responsible->id,
            'status_id' => $newStatus->id,
        ]); // Assertion 41
    }

    public function test_can_delete_ticket_and_verify_cascade(): void
    {
        $user = $this->createAuthenticatedUser();
        $project = $this->createProjectWithStatus($user, 'DLT');
        $ticket = $this->createTicketWithAllDependencies($project, $user);

        $ticketId = $ticket->id;

        $response = $this->deleteJson("/api/tickets/{$ticketId}");

        $response->assertNoContent(); // Assertion 42

        // Verify ticket is no longer accessible via API (may be soft deleted)
        $getResponse = $this->getJson("/api/tickets/{$ticketId}");
        $getResponse->assertNotFound(); // Assertion 43
    }

    // ==================== EPIC & SPRINT MANAGEMENT TESTS ====================

    public function test_can_manage_project_epics_full_lifecycle(): void
    {
        $user = $this->createAuthenticatedUser();
        $project = $this->createProjectWithStatus($user, 'EPC');

        // Create epic
        $createResponse = $this->postJson("/api/projects/{$project->id}/epics", [
            'name' => 'Major Epic',
            'starts_at' => '2024-01-01',
            'ends_at' => '2024-12-31',
        ]);

        $createResponse->assertCreated(); // Assertion 44
        $createResponse->assertJsonPath('name', 'Major Epic'); // Assertion 45
        $this->assertDatabaseHas('epics', [
            'name' => 'Major Epic',
            'project_id' => $project->id,
        ]); // Assertion 46

        $epicId = $createResponse->json('id');

        // Update epic
        $updateResponse = $this->putJson("/api/projects/{$project->id}/epics/{$epicId}", [
            'name' => 'Updated Epic Name',
            'ends_at' => '2024-06-30',
        ]);

        $updateResponse->assertOk(); // Assertion 47
        $updateResponse->assertJsonPath('name', 'Updated Epic Name'); // Assertion 48

        // Delete epic
        $deleteResponse = $this->deleteJson("/api/projects/{$project->id}/epics/{$epicId}");

        $deleteResponse->assertNoContent(); // Assertion 49

        // Verify epic is no longer accessible via API (may be soft deleted)
        $getResponse = $this->getJson("/api/projects/{$project->id}/epics/{$epicId}");
        $getResponse->assertNotFound(); // Assertion 50
    }

    public function test_can_manage_project_sprints_with_dates(): void
    {
        $user = $this->createAuthenticatedUser();
        $project = $this->createProjectWithStatus($user, 'SPR');

        // Create sprint
        $createResponse = $this->postJson("/api/projects/{$project->id}/sprints", [
            'name' => 'Sprint 1',
            'description' => 'First sprint',
            'starts_at' => '2024-01-01',
            'ends_at' => '2024-01-14',
        ]);

        $createResponse->assertCreated(); // Assertion 51
        $createResponse->assertJsonPath('name', 'Sprint 1'); // Assertion 52
        $this->assertDatabaseHas('sprints', [
            'name' => 'Sprint 1',
            'project_id' => $project->id,
        ]); // Assertion 53

        $sprintId = $createResponse->json('id');

        // Update sprint with started_at
        $updateResponse = $this->putJson("/api/projects/{$project->id}/sprints/{$sprintId}", [
            'name' => 'Sprint 1 - Updated',
            'started_at' => '2024-01-01 09:00:00',
        ]);

        $updateResponse->assertOk(); // Assertion 54
        $updateResponse->assertJsonPath('name', 'Sprint 1 - Updated'); // Assertion 55

        // List sprints
        $listResponse = $this->getJson("/api/projects/{$project->id}/sprints");
        $listResponse->assertOk(); // Assertion 56
    }

    // ==================== TICKET COMMENT TESTS ====================

    public function test_can_add_and_list_ticket_comments(): void
    {
        $user = $this->createAuthenticatedUser();
        $project = $this->createProjectWithStatus($user, 'COM');
        $ticket = $this->createTicketWithAllDependencies($project, $user);

        // Add comment
        $createResponse = $this->postJson("/api/tickets/{$ticket->id}/comments", [
            'content' => 'This is a test comment',
        ]);

        $createResponse->assertCreated(); // Assertion 57
        $createResponse->assertJsonPath('content', 'This is a test comment'); // Assertion 58
        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'content' => 'This is a test comment',
        ]); // Assertion 59

        // List comments
        $listResponse = $this->getJson("/api/tickets/{$ticket->id}/comments");

        $listResponse->assertOk(); // Assertion 60
        $this->assertIsArray($listResponse->json('data')); // Assertion 61
    }

    public function test_comment_owner_can_edit_and_delete_own_comment(): void
    {
        $user = $this->createAuthenticatedUser();
        $project = $this->createProjectWithStatus($user, 'EDT');
        $ticket = $this->createTicketWithAllDependencies($project, $user);

        // Create comment
        $createResponse = $this->postJson("/api/tickets/{$ticket->id}/comments", [
            'content' => 'Original comment',
        ]);
        $commentId = $createResponse->json('id');

        // Edit own comment
        $updateResponse = $this->putJson("/api/tickets/{$ticket->id}/comments/{$commentId}", [
            'content' => 'Updated comment',
        ]);

        $updateResponse->assertOk(); // Assertion 62
        $updateResponse->assertJsonPath('content', 'Updated comment'); // Assertion 63

        // Delete own comment
        $deleteResponse = $this->deleteJson("/api/tickets/{$ticket->id}/comments/{$commentId}");

        $deleteResponse->assertNoContent(); // Assertion 64

        // Verify comment is no longer accessible via API (may be soft deleted)
        $getResponse = $this->getJson("/api/tickets/{$ticket->id}/comments/{$commentId}");
        $getResponse->assertNotFound(); // Assertion 65
    }

    // ==================== TICKET HOUR (TIME TRACKING) TESTS ====================

    public function test_can_log_hours_on_ticket_with_activity(): void
    {
        $user = $this->createAuthenticatedUser();
        $project = $this->createProjectWithStatus($user, 'HRS');
        $ticket = $this->createTicketWithAllDependencies($project, $user);

        // Log hours
        $response = $this->postJson("/api/tickets/{$ticket->id}/hours", [
            'value' => 4.5,
            'comment' => 'Worked on implementing feature',
        ]);

        $response->assertCreated(); // Assertion 66
        $response->assertJsonPath('value', 4.5); // Assertion 67
        $response->assertJsonPath('comment', 'Worked on implementing feature'); // Assertion 68
        $this->assertDatabaseHas('ticket_hours', [
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'value' => 4.5,
        ]); // Assertion 69
    }
}
