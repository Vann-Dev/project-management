<?php

namespace Tests\Feature;

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

class SecurityTestingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_current_user_api(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertUnauthorized();
        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_guest_cannot_access_project_api(): void
    {
        $response = $this->getJson('/api/projects');

        $response->assertUnauthorized();
        $this->assertFalse($response->isSuccessful());
    }

    public function test_guest_cannot_create_project_api(): void
    {
        $response = $this->postJson('/api/projects', ['name' => 'Blocked Project']);

        $response->assertUnauthorized();
        $this->assertDatabaseMissing('projects', ['name' => 'Blocked Project']);
    }

    public function test_api_user_response_does_not_expose_password(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');

        $response->assertOk();
        $response->assertJsonMissingPath('password');
    }

    public function test_user_cannot_access_project_owned_by_another_user(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $project = $this->createProject($owner, 'FOR');
        Sanctum::actingAs($attacker);

        $response = $this->getJson('/api/projects/' . $project->id);

        $response->assertForbidden();
        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_user_cannot_access_ticket_from_another_users_project(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $ticket = $this->createTicket($owner, $this->createProject($owner, 'TFB'));
        Sanctum::actingAs($attacker);

        $response = $this->getJson('/api/tickets/' . $ticket->id);

        $response->assertForbidden();
        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_project_create_validation_rejects_missing_name(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/projects', [
            'ticket_prefix' => 'VAL',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_ticket_create_validation_rejects_missing_content(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $project = $this->createProject($user, 'VTC');

        $response = $this->postJson('/api/tickets', [
            'project_id' => $project->id,
            'name' => 'Invalid Ticket',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['content']);
    }

    public function test_invalid_project_id_returns_not_found(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/projects/999999');

        $response->assertNotFound();
        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_invalid_ticket_id_returns_not_found(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/tickets/999999');

        $response->assertNotFound();
        $this->assertSame(404, $response->getStatusCode());
    }

    private function createProject(User $owner, string $prefix): Project
    {
        return Project::create([
            'name' => 'Security Project ' . $prefix,
            'description' => 'Project fixture for security testing',
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
            'name' => 'Security Ticket',
            'content' => 'Ticket fixture for security testing',
            'owner_id' => $owner->id,
            'responsible_id' => $owner->id,
            'status_id' => TicketStatus::create(['name' => 'Todo ' . uniqid(), 'color' => '#64748b', 'is_default' => true, 'order' => 1])->id,
            'project_id' => $project->id,
            'type_id' => TicketType::create(['name' => 'Bug ' . uniqid(), 'icon' => 'heroicon-o-bug-ant', 'color' => '#ef4444', 'is_default' => true])->id,
            'priority_id' => TicketPriority::create(['name' => 'High ' . uniqid(), 'color' => '#f97316', 'is_default' => true])->id,
            'estimation' => 3,
        ]);
    }
}
