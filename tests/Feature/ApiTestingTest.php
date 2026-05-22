<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiTestingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_projects_api_route(): void
    {
        $response = $this->getJson('/api/projects');

        $response->assertUnauthorized();
        $response->assertJsonMissing(['data' => []]);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertFalse($response->isSuccessful());
    }

    public function test_authenticated_user_can_access_current_user_api_route(): void
    {
        $user = User::factory()->create(['email' => 'api-user@example.com']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');

        $response->assertOk();
        $response->assertJsonPath('id', $user->id);
        $response->assertJsonPath('email', 'api-user@example.com');
        $response->assertJsonMissingPath('password');
    }

    public function test_authenticated_user_can_access_users_api_route(): void
    {
        Sanctum::actingAs(User::factory()->create());
        User::factory()->create(['name' => 'Listed API User']);

        $response = $this->getJson('/api/users');

        $response->assertOk();
        $response->assertJsonStructure(['data', 'links', 'meta']);
        $response->assertJsonFragment(['name' => 'Listed API User']);
        $response->assertJsonMissingPath('data.0.password');
    }

    public function test_authenticated_user_can_access_project_status_reference_route(): void
    {
        Sanctum::actingAs(User::factory()->create());
        ProjectStatus::create(['name' => 'Reference Active', 'color' => '#16a34a', 'is_default' => true]);

        $response = $this->getJson('/api/references/project-statuses');

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Reference Active']);
        $response->assertJsonFragment(['color' => '#16a34a']);
        $this->assertIsArray($response->json());
    }

    public function test_authenticated_user_can_create_project_via_api(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $status = ProjectStatus::create(['name' => 'Active', 'color' => '#16a34a', 'is_default' => true]);

        $response = $this->postJson('/api/projects', [
            'name' => 'API Project',
            'description' => 'Created from API testing',
            'status_id' => $status->id,
            'ticket_prefix' => 'API',
            'status_type' => 'default',
            'type' => 'kanban',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('name', 'API Project');
        $response->assertJsonPath('owner_id', $user->id);
        $this->assertDatabaseHas('projects', ['name' => 'API Project', 'ticket_prefix' => 'API']);
    }

    public function test_authenticated_user_can_show_project_via_api(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $project = $this->createProject($user, 'SHW');

        $response = $this->getJson('/api/projects/' . $project->id);

        $response->assertOk();
        $response->assertJsonPath('id', $project->id);
        $response->assertJsonPath('ticket_prefix', 'SHW');
        $response->assertJsonStructure(['owner', 'status', 'users', 'tickets']);
    }

    public function test_authenticated_user_can_update_project_via_api(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $project = $this->createProject($user, 'UPD');

        $response = $this->putJson('/api/projects/' . $project->id, [
            'name' => 'Updated API Project',
            'type' => 'scrum',
        ]);

        $response->assertOk();
        $response->assertJsonPath('name', 'Updated API Project');
        $response->assertJsonPath('type', 'scrum');
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Updated API Project']);
    }

    public function test_authenticated_user_can_create_ticket_via_api(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $project = $this->createProject($user, 'TCA');
        $status = $this->createTicketStatus();
        $type = $this->createTicketType();
        $priority = $this->createTicketPriority();

        $response = $this->postJson('/api/tickets', [
            'project_id' => $project->id,
            'name' => 'API Ticket',
            'content' => 'Ticket from API testing',
            'status_id' => $status->id,
            'type_id' => $type->id,
            'priority_id' => $priority->id,
            'estimation' => 4,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('name', 'API Ticket');
        $response->assertJsonPath('project_id', $project->id);
        $this->assertDatabaseHas('tickets', ['name' => 'API Ticket', 'project_id' => $project->id]);
    }

    public function test_authenticated_user_can_create_ticket_comment_via_api(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $ticket = $this->createTicket($user, $this->createProject($user, 'COM'));

        $response = $this->postJson('/api/tickets/' . $ticket->id . '/comments', [
            'content' => 'API testing comment',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('content', 'API testing comment');
        $response->assertJsonPath('ticket_id', $ticket->id);
        $this->assertDatabaseHas('ticket_comments', ['ticket_id' => $ticket->id, 'content' => 'API testing comment']);
    }

    public function test_authenticated_user_can_create_ticket_hour_via_api(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $ticket = $this->createTicket($user, $this->createProject($user, 'HRA'));
        $activity = Activity::create(['name' => 'Development', 'description' => 'Development work']);

        $response = $this->postJson('/api/tickets/' . $ticket->id . '/hours', [
            'value' => 2.5,
            'comment' => 'API testing logged hour',
            'activity_id' => $activity->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('value', 2.5);
        $response->assertJsonPath('ticket_id', $ticket->id);
        $this->assertDatabaseHas('ticket_hours', ['ticket_id' => $ticket->id, 'activity_id' => $activity->id]);
    }

    private function createProject(User $owner, string $prefix): Project
    {
        return Project::create([
            'name' => 'API Project ' . $prefix,
            'description' => 'Project fixture for API testing',
            'owner_id' => $owner->id,
            'status_id' => ProjectStatus::create(['name' => 'Active ' . uniqid(), 'color' => '#16a34a', 'is_default' => true])->id,
            'ticket_prefix' => $prefix,
            'status_type' => 'default',
            'type' => 'kanban',
        ]);
    }

    private function createTicket(User $owner, Project $project): Ticket
    {
        return Ticket::create([
            'name' => 'API Fixture Ticket',
            'content' => 'Ticket fixture for API testing',
            'owner_id' => $owner->id,
            'responsible_id' => $owner->id,
            'status_id' => $this->createTicketStatus()->id,
            'project_id' => $project->id,
            'type_id' => $this->createTicketType()->id,
            'priority_id' => $this->createTicketPriority()->id,
            'estimation' => 3,
        ]);
    }

    private function createTicketStatus(): TicketStatus
    {
        return TicketStatus::create(['name' => 'Todo ' . uniqid(), 'color' => '#64748b', 'is_default' => true, 'order' => random_int(1, 1000)]);
    }

    private function createTicketType(): TicketType
    {
        return TicketType::create(['name' => 'Bug ' . uniqid(), 'icon' => 'heroicon-o-bug-ant', 'color' => '#ef4444', 'is_default' => true]);
    }

    private function createTicketPriority(): TicketPriority
    {
        return TicketPriority::create(['name' => 'High ' . uniqid(), 'color' => '#f97316', 'is_default' => true]);
    }
}
