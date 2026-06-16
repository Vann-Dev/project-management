<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Epic;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Sprint;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ComprehensiveApiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    // Positive Scenarios

    public function test_complete_project_workflow_with_member_collaboration(): void
    {
        $owner = User::factory()->create(['name' => 'Project Owner']);
        $member = User::factory()->create(['name' => 'Project Member']);
        Sanctum::actingAs($owner);

        $projectStatus = $this->createProjectStatus();
        $projectResponse = $this->postJson('/api/projects', [
            'name' => 'Collaboration Project',
            'description' => 'Testing member collaboration workflow',
            'status_id' => $projectStatus->id,
            'ticket_prefix' => 'COL',
        ]);

        $projectResponse->assertCreated();
        $projectId = $projectResponse->json('id');
        $project = Project::find($projectId);
        $project->users()->attach($member->id, ['role' => 'member']);

        Sanctum::actingAs($member);
        $ticketStatus = $this->createTicketStatus();
        $ticketType = $this->createTicketType();
        $ticketPriority = $this->createTicketPriority();

        $ticketResponse = $this->postJson('/api/tickets', [
            'project_id' => $projectId,
            'name' => 'Member Created Ticket',
            'content' => 'This ticket was created by a project member',
            'status_id' => $ticketStatus->id,
            'type_id' => $ticketType->id,
            'priority_id' => $ticketPriority->id,
        ]);

        $ticketResponse->assertCreated();
        $ticketId = $ticketResponse->json('id');

        $commentResponse = $this->postJson("/api/tickets/{$ticketId}/comments", [
            'content' => 'Member adding a comment to the ticket',
        ]);

        $commentResponse->assertCreated();
        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticketId,
            'user_id' => $member->id,
            'content' => 'Member adding a comment to the ticket',
        ]);

        Sanctum::actingAs($owner);
        $activity = $this->createActivity();
        $hoursResponse = $this->postJson("/api/tickets/{$ticketId}/hours", [
            'value' => 2.5,
            'comment' => 'Owner logging hours on member ticket',
            'activity_id' => $activity->id,
        ]);

        $hoursResponse->assertCreated();
        $this->assertDatabaseHas('ticket_hours', [
            'ticket_id' => $ticketId,
            'user_id' => $owner->id,
            'value' => 2.5,
        ]);
    }

    public function test_epic_and_sprint_workflow_with_nested_resources(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $projectStatus = $this->createProjectStatus();
        $projectResponse = $this->postJson('/api/projects', [
            'name' => 'Epic Sprint Project',
            'status_id' => $projectStatus->id,
            'ticket_prefix' => 'ESP',
            'type' => 'scrum',
        ]);

        $projectResponse->assertCreated();
        $projectId = $projectResponse->json('id');

        $epicResponse = $this->postJson("/api/projects/{$projectId}/epics", [
            'name' => 'Authentication Epic',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonths(2)->toDateString(),
        ]);

        $epicResponse->assertCreated();
        $epicId = $epicResponse->json('id');
        $this->assertDatabaseHas('epics', [
            'id' => $epicId,
            'project_id' => $projectId,
            'name' => 'Authentication Epic',
        ]);

        $sprintResponse = $this->postJson("/api/projects/{$projectId}/sprints", [
            'name' => 'Sprint 1',
            'description' => 'First sprint for authentication',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addWeeks(2)->toDateString(),
        ]);

        $sprintResponse->assertCreated();
        $sprintId = $sprintResponse->json('id');

        $ticketStatus = $this->createTicketStatus();
        $ticketType = $this->createTicketType();
        $ticketPriority = $this->createTicketPriority();

        $ticketResponse = $this->postJson('/api/tickets', [
            'project_id' => $projectId,
            'name' => 'Implement login',
            'content' => 'Create login functionality',
            'status_id' => $ticketStatus->id,
            'type_id' => $ticketType->id,
            'priority_id' => $ticketPriority->id,
            'epic_id' => $epicId,
            'sprint_id' => $sprintId,
        ]);

        $ticketResponse->assertCreated();
        $ticketResponse->assertJsonPath('epic_id', $epicId);
        $ticketResponse->assertJsonPath('sprint_id', $sprintId);
    }

    public function test_project_listing_shows_only_accessible_projects(): void
    {
        $userA = User::factory()->create(['name' => 'User A']);
        $userB = User::factory()->create(['name' => 'User B']);

        Sanctum::actingAs($userA);
        $statusA = $this->createProjectStatus();
        $projectAResponse = $this->postJson('/api/projects', [
            'name' => 'User A Project',
            'status_id' => $statusA->id,
            'ticket_prefix' => 'UAP',
        ]);
        $projectAResponse->assertCreated();

        Sanctum::actingAs($userB);
        $statusB = $this->createProjectStatus();
        $projectBResponse = $this->postJson('/api/projects', [
            'name' => 'User B Project',
            'status_id' => $statusB->id,
            'ticket_prefix' => 'UBP',
        ]);
        $projectBResponse->assertCreated();

        $listResponse = $this->getJson('/api/projects');
        $listResponse->assertOk();
        $projects = $listResponse->json('data');

        $this->assertCount(1, $projects);
        $this->assertEquals('User B Project', $projects[0]['name']);
        $this->assertArrayHasKey('owner', $projects[0]);
        $this->assertEquals($userB->id, $projects[0]['owner']['id']);
    }

    public function test_ticket_retrieval_includes_all_relationships(): void
    {
        $owner = User::factory()->create(['name' => 'Ticket Owner']);
        $responsible = User::factory()->create(['name' => 'Responsible Person']);
        Sanctum::actingAs($owner);

        $projectStatus = $this->createProjectStatus();
        $projectResponse = $this->postJson('/api/projects', [
            'name' => 'Relationship Test Project',
            'status_id' => $projectStatus->id,
            'ticket_prefix' => 'RTP',
        ]);
        $projectId = $projectResponse->json('id');

        $project = Project::find($projectId);
        $project->users()->attach($responsible->id, ['role' => 'member']);

        $ticketStatus = $this->createTicketStatus();
        $ticketType = $this->createTicketType();
        $ticketPriority = $this->createTicketPriority();

        $ticketResponse = $this->postJson('/api/tickets', [
            'project_id' => $projectId,
            'name' => 'Complete Feature',
            'content' => 'Implement complete feature with all relationships',
            'responsible_id' => $responsible->id,
            'status_id' => $ticketStatus->id,
            'type_id' => $ticketType->id,
            'priority_id' => $ticketPriority->id,
            'estimation' => 8,
        ]);

        $ticketId = $ticketResponse->json('id');
        $getResponse = $this->getJson("/api/tickets/{$ticketId}");

        $getResponse->assertOk();
        $getResponse->assertJsonPath('id', $ticketId);
        $getResponse->assertJsonPath('project.id', $projectId);
        $getResponse->assertJsonPath('project.name', 'Relationship Test Project');
        $getResponse->assertJsonPath('owner.id', $owner->id);
        $getResponse->assertJsonPath('owner.name', 'Ticket Owner');
        $getResponse->assertJsonPath('responsible.id', $responsible->id);
        $getResponse->assertJsonPath('responsible.name', 'Responsible Person');
        $getResponse->assertJsonPath('status.id', $ticketStatus->id);
        $getResponse->assertJsonPath('type.id', $ticketType->id);
        $getResponse->assertJsonPath('priority.id', $ticketPriority->id);
        $this->assertNotNull($getResponse->json('project'));
        $this->assertNotNull($getResponse->json('owner'));
        $this->assertNotNull($getResponse->json('responsible'));
        $this->assertNotNull($getResponse->json('status'));
        $this->assertNotNull($getResponse->json('type'));
        $this->assertNotNull($getResponse->json('priority'));
    }

    public function test_update_project_details_persists_changes(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $projectStatus = $this->createProjectStatus();
        $projectResponse = $this->postJson('/api/projects', [
            'name' => 'Original Project Name',
            'description' => 'Original description',
            'status_id' => $projectStatus->id,
            'ticket_prefix' => 'OPN',
        ]);

        $projectResponse->assertCreated();
        $projectId = $projectResponse->json('id');

        $updateResponse = $this->putJson("/api/projects/{$projectId}", [
            'name' => 'Updated Project Name',
            'description' => 'Updated description with more details',
        ]);

        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('name', 'Updated Project Name');
        $updateResponse->assertJsonPath('description', 'Updated description with more details');

        $this->assertDatabaseHas('projects', [
            'id' => $projectId,
            'name' => 'Updated Project Name',
            'description' => 'Updated description with more details',
        ]);
    }

    public function test_update_ticket_status_reflects_in_database(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $projectStatus = $this->createProjectStatus();
        $projectResponse = $this->postJson('/api/projects', [
            'name' => 'Status Update Project',
            'status_id' => $projectStatus->id,
            'ticket_prefix' => 'SUP',
        ]);
        $projectId = $projectResponse->json('id');

        $statusTodo = $this->createTicketStatus(['name' => 'Todo']);
        $statusInProgress = $this->createTicketStatus(['name' => 'In Progress']);
        $ticketType = $this->createTicketType();
        $ticketPriority = $this->createTicketPriority();

        $ticketResponse = $this->postJson('/api/tickets', [
            'project_id' => $projectId,
            'name' => 'Task to update',
            'content' => 'This task will have its status updated',
            'status_id' => $statusTodo->id,
            'type_id' => $ticketType->id,
            'priority_id' => $ticketPriority->id,
        ]);

        $ticketId = $ticketResponse->json('id');

        $updateResponse = $this->putJson("/api/tickets/{$ticketId}", [
            'status_id' => $statusInProgress->id,
        ]);

        $updateResponse->assertOk();
        $this->assertDatabaseHas('tickets', [
            'id' => $ticketId,
            'status_id' => $statusInProgress->id,
        ]);
    }

    public function test_reference_data_endpoints_return_correct_data(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $projectStatus = $this->createProjectStatus();
        $ticketStatus = $this->createTicketStatus();
        $ticketType = $this->createTicketType();
        $ticketPriority = $this->createTicketPriority();
        $activity = $this->createActivity();

        $projectStatusesResponse = $this->getJson('/api/references/project-statuses');
        $projectStatusesResponse->assertOk();
        $projectStatusesResponse->assertJsonFragment(['id' => $projectStatus->id]);

        $ticketStatusesResponse = $this->getJson('/api/references/ticket-statuses');
        $ticketStatusesResponse->assertOk();
        $ticketStatusesResponse->assertJsonFragment(['id' => $ticketStatus->id]);

        $ticketTypesResponse = $this->getJson('/api/references/ticket-types');
        $ticketTypesResponse->assertOk();
        $ticketTypesResponse->assertJsonFragment(['id' => $ticketType->id]);

        $ticketPrioritiesResponse = $this->getJson('/api/references/ticket-priorities');
        $ticketPrioritiesResponse->assertOk();
        $ticketPrioritiesResponse->assertJsonFragment(['id' => $ticketPriority->id]);

        $activitiesResponse = $this->getJson('/api/references/activities');
        $activitiesResponse->assertOk();
        $activitiesResponse->assertJsonFragment(['id' => $activity->id]);
    }

    public function test_project_member_can_access_and_modify_tickets(): void
    {
        $owner = User::factory()->create(['name' => 'Owner']);
        $member = User::factory()->create(['name' => 'Member']);
        Sanctum::actingAs($owner);

        $projectStatus = $this->createProjectStatus();
        $projectResponse = $this->postJson('/api/projects', [
            'name' => 'Member Access Project',
            'status_id' => $projectStatus->id,
            'ticket_prefix' => 'MAP',
        ]);

        $projectId = $projectResponse->json('id');
        $project = Project::find($projectId);
        $project->users()->attach($member->id, ['role' => 'member']);

        Sanctum::actingAs($member);
        $ticketStatus = $this->createTicketStatus();
        $ticketType = $this->createTicketType();
        $ticketPriority = $this->createTicketPriority();

        $createResponse = $this->postJson('/api/tickets', [
            'project_id' => $projectId,
            'name' => 'Member Ticket',
            'content' => 'Created by project member',
            'status_id' => $ticketStatus->id,
            'type_id' => $ticketType->id,
            'priority_id' => $ticketPriority->id,
        ]);

        $createResponse->assertCreated();
        $ticketId = $createResponse->json('id');

        $newStatus = $this->createTicketStatus(['name' => 'Updated Status']);
        $updateResponse = $this->putJson("/api/tickets/{$ticketId}", [
            'name' => 'Updated by Member',
            'status_id' => $newStatus->id,
        ]);

        $updateResponse->assertOk();
        $this->assertDatabaseHas('tickets', [
            'id' => $ticketId,
            'name' => 'Updated by Member',
            'status_id' => $newStatus->id,
        ]);

        $commentResponse = $this->postJson("/api/tickets/{$ticketId}/comments", [
            'content' => 'Member comment on their ticket',
        ]);

        $commentResponse->assertCreated();
        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticketId,
            'user_id' => $member->id,
        ]);
    }

    // Negative Scenarios

    public function test_guest_user_cannot_access_any_api_endpoints(): void
    {
        $projectsListResponse = $this->getJson('/api/projects');
        $projectsListResponse->assertUnauthorized();

        $projectCreateResponse = $this->postJson('/api/projects', [
            'name' => 'Test Project',
            'ticket_prefix' => 'TST',
        ]);
        $projectCreateResponse->assertUnauthorized();

        $ticketsListResponse = $this->getJson('/api/tickets');
        $ticketsListResponse->assertUnauthorized();

        $ticketCreateResponse = $this->postJson('/api/tickets', [
            'project_id' => 1,
            'name' => 'Test Ticket',
            'content' => 'Test content',
        ]);
        $ticketCreateResponse->assertUnauthorized();

        $this->assertEquals(401, $projectsListResponse->status());
        $this->assertEquals(401, $projectCreateResponse->status());
        $this->assertEquals(401, $ticketsListResponse->status());
        $this->assertEquals(401, $ticketCreateResponse->status());
    }

    public function test_user_cannot_access_other_users_project(): void
    {
        $userA = User::factory()->create(['name' => 'User A']);
        $userB = User::factory()->create(['name' => 'User B']);

        Sanctum::actingAs($userA);
        $projectStatus = $this->createProjectStatus();
        $projectResponse = $this->postJson('/api/projects', [
            'name' => 'User A Private Project',
            'status_id' => $projectStatus->id,
            'ticket_prefix' => 'UAP',
        ]);

        $projectId = $projectResponse->json('id');

        Sanctum::actingAs($userB);

        $showResponse = $this->getJson("/api/projects/{$projectId}");
        $showResponse->assertForbidden();

        $updateResponse = $this->putJson("/api/projects/{$projectId}", [
            'name' => 'Trying to update',
        ]);
        $updateResponse->assertForbidden();

        $deleteResponse = $this->deleteJson("/api/projects/{$projectId}");
        $deleteResponse->assertForbidden();
    }

    public function test_user_cannot_access_ticket_in_unauthorized_project(): void
    {
        $userA = User::factory()->create(['name' => 'User A']);
        $userB = User::factory()->create(['name' => 'User B']);

        Sanctum::actingAs($userA);
        $projectStatus = $this->createProjectStatus();
        $projectResponse = $this->postJson('/api/projects', [
            'name' => 'Private Project',
            'status_id' => $projectStatus->id,
            'ticket_prefix' => 'PRP',
        ]);

        $projectId = $projectResponse->json('id');
        $ticketStatus = $this->createTicketStatus();
        $ticketType = $this->createTicketType();
        $ticketPriority = $this->createTicketPriority();

        $ticketResponse = $this->postJson('/api/tickets', [
            'project_id' => $projectId,
            'name' => 'Private Ticket',
            'content' => 'User B should not access this',
            'status_id' => $ticketStatus->id,
            'type_id' => $ticketType->id,
            'priority_id' => $ticketPriority->id,
        ]);

        $ticketId = $ticketResponse->json('id');

        Sanctum::actingAs($userB);

        $showResponse = $this->getJson("/api/tickets/{$ticketId}");
        $showResponse->assertForbidden();

        $updateResponse = $this->putJson("/api/tickets/{$ticketId}", [
            'name' => 'Unauthorized update',
        ]);
        $updateResponse->assertForbidden();

        $deleteResponse = $this->deleteJson("/api/tickets/{$ticketId}");
        $deleteResponse->assertForbidden();
    }

    public function test_user_cannot_modify_others_comments(): void
    {
        $owner = User::factory()->create(['name' => 'Owner']);
        $memberA = User::factory()->create(['name' => 'Member A']);
        $memberB = User::factory()->create(['name' => 'Member B']);

        Sanctum::actingAs($owner);
        $projectStatus = $this->createProjectStatus();
        $projectResponse = $this->postJson('/api/projects', [
            'name' => 'Comment Test Project',
            'status_id' => $projectStatus->id,
            'ticket_prefix' => 'CTP',
        ]);

        $projectId = $projectResponse->json('id');
        $project = Project::find($projectId);
        $project->users()->attach([
            $memberA->id => ['role' => 'member'],
            $memberB->id => ['role' => 'member'],
        ]);

        $ticketStatus = $this->createTicketStatus();
        $ticketType = $this->createTicketType();
        $ticketPriority = $this->createTicketPriority();

        $ticketResponse = $this->postJson('/api/tickets', [
            'project_id' => $projectId,
            'name' => 'Ticket with Comments',
            'content' => 'Testing comment ownership',
            'status_id' => $ticketStatus->id,
            'type_id' => $ticketType->id,
            'priority_id' => $ticketPriority->id,
        ]);

        $ticketId = $ticketResponse->json('id');

        Sanctum::actingAs($memberA);
        $commentResponse = $this->postJson("/api/tickets/{$ticketId}/comments", [
            'content' => 'Comment by Member A',
        ]);

        $commentId = $commentResponse->json('id');

        Sanctum::actingAs($memberB);

        $updateResponse = $this->putJson("/api/tickets/{$ticketId}/comments/{$commentId}", [
            'content' => 'Member B trying to modify Member A comment',
        ]);
        $updateResponse->assertForbidden();

        $deleteResponse = $this->deleteJson("/api/tickets/{$ticketId}/comments/{$commentId}");
        $deleteResponse->assertForbidden();
    }

    public function test_validation_fails_for_missing_required_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $projectStatus = $this->createProjectStatus();

        $projectNoNameResponse = $this->postJson('/api/projects', [
            'ticket_prefix' => 'PNM',
            'status_id' => $projectStatus->id,
        ]);
        $projectNoNameResponse->assertUnprocessable();
        $projectNoNameResponse->assertJsonValidationErrors(['name']);

        $projectNoPrefixResponse = $this->postJson('/api/projects', [
            'name' => 'Project without prefix',
            'status_id' => $projectStatus->id,
        ]);
        $projectNoPrefixResponse->assertUnprocessable();
        $projectNoPrefixResponse->assertJsonValidationErrors(['ticket_prefix']);

        $projectResponse = $this->postJson('/api/projects', [
            'name' => 'Valid Project',
            'status_id' => $projectStatus->id,
            'ticket_prefix' => 'VLD',
        ]);
        $projectId = $projectResponse->json('id');

        $ticketStatus = $this->createTicketStatus();
        $ticketType = $this->createTicketType();
        $ticketPriority = $this->createTicketPriority();

        $ticketNoContentResponse = $this->postJson('/api/tickets', [
            'project_id' => $projectId,
            'name' => 'Ticket without content',
            'status_id' => $ticketStatus->id,
            'type_id' => $ticketType->id,
            'priority_id' => $ticketPriority->id,
        ]);
        $ticketNoContentResponse->assertUnprocessable();
        $ticketNoContentResponse->assertJsonValidationErrors(['content']);
    }

    public function test_validation_fails_for_duplicate_ticket_prefix(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $projectStatus = $this->createProjectStatus();

        $firstProjectResponse = $this->postJson('/api/projects', [
            'name' => 'First Project',
            'status_id' => $projectStatus->id,
            'ticket_prefix' => 'DUP',
        ]);

        $firstProjectResponse->assertCreated();

        $duplicateProjectResponse = $this->postJson('/api/projects', [
            'name' => 'Second Project',
            'status_id' => $projectStatus->id,
            'ticket_prefix' => 'DUP',
        ]);

        $duplicateProjectResponse->assertUnprocessable();
        $duplicateProjectResponse->assertJsonValidationErrors(['ticket_prefix']);
    }

    public function test_accessing_nonexistent_resources_returns_404(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $projectResponse = $this->getJson('/api/projects/99999');
        $projectResponse->assertNotFound();

        $ticketResponse = $this->getJson('/api/tickets/99999');
        $ticketResponse->assertNotFound();

        $epicResponse = $this->getJson('/api/projects/99999/epics/99999');
        $epicResponse->assertNotFound();

        $sprintResponse = $this->getJson('/api/projects/99999/sprints/99999');
        $sprintResponse->assertNotFound();
    }

    // Helper Methods

    private function createProjectStatus(array $overrides = []): ProjectStatus
    {
        return ProjectStatus::create(array_merge([
            'name' => 'Status ' . uniqid(),
            'color' => '#16a34a',
            'is_default' => true,
        ], $overrides));
    }

    private function createTicketStatus(array $overrides = []): TicketStatus
    {
        return TicketStatus::create(array_merge([
            'name' => 'Status ' . uniqid(),
            'color' => '#64748b',
            'is_default' => true,
            'order' => random_int(1, 1000),
        ], $overrides));
    }

    private function createTicketType(array $overrides = []): TicketType
    {
        return TicketType::create(array_merge([
            'name' => 'Type ' . uniqid(),
            'icon' => 'heroicon-o-bug-ant',
            'color' => '#ef4444',
            'is_default' => true,
        ], $overrides));
    }

    private function createTicketPriority(array $overrides = []): TicketPriority
    {
        return TicketPriority::create(array_merge([
            'name' => 'Priority ' . uniqid(),
            'color' => '#f97316',
            'is_default' => true,
        ], $overrides));
    }

    private function createActivity(array $overrides = []): Activity
    {
        return Activity::create(array_merge([
            'name' => 'Activity ' . uniqid(),
            'description' => 'Test activity',
        ], $overrides));
    }
}
