<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ProjectStatus;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiIntegrationTestingTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_flow_create_project_then_create_ticket_successfully(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $status = ProjectStatus::create(['name' => 'Active', 'color' => '#16a34a', 'is_default' => true]);
        $ticketStatus = $this->createTicketStatus();
        $ticketType = $this->createTicketType();
        $ticketPriority = $this->createTicketPriority();

        $projectResponse = $this->postJson('/api/projects', [
            'name' => 'API Integration Project',
            'status_id' => $status->id,
            'ticket_prefix' => 'AIP',
        ]);
        $projectId = $projectResponse->json('id');

        $ticketResponse = $this->postJson('/api/tickets', [
            'project_id' => $projectId,
            'name' => 'API Integration Ticket',
            'content' => 'Ticket created after project API call',
            'status_id' => $ticketStatus->id,
            'type_id' => $ticketType->id,
            'priority_id' => $ticketPriority->id,
        ]);

        $projectResponse->assertCreated();
        $ticketResponse->assertCreated();
        $ticketResponse->assertJsonPath('project_id', $projectId);
        $this->assertDatabaseHas('tickets', ['name' => 'API Integration Ticket', 'project_id' => $projectId]);
    }

    public function test_api_flow_create_project_then_create_epic_successfully(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $projectId = $this->createProjectThroughApi('EPI');

        $response = $this->postJson('/api/projects/' . $projectId . '/epics', [
            'name' => 'API Integration Epic',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('project_id', $projectId);
        $response->assertJsonPath('name', 'API Integration Epic');
        $this->assertDatabaseHas('epics', ['name' => 'API Integration Epic', 'project_id' => $projectId]);
    }

    public function test_api_flow_create_project_then_create_sprint_successfully(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $projectId = $this->createProjectThroughApi('SPR');

        $response = $this->postJson('/api/projects/' . $projectId . '/sprints', [
            'name' => 'API Integration Sprint',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addDays(14)->toDateString(),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('project_id', $projectId);
        $response->assertJsonPath('name', 'API Integration Sprint');
        $this->assertDatabaseHas('sprints', ['name' => 'API Integration Sprint', 'project_id' => $projectId]);
    }

    public function test_api_flow_create_ticket_then_add_comment_successfully(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $ticketId = $this->createTicketThroughApi('CFL');

        $response = $this->postJson('/api/tickets/' . $ticketId . '/comments', [
            'content' => 'Comment from API integration testing',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('ticket_id', $ticketId);
        $response->assertJsonPath('content', 'Comment from API integration testing');
        $this->assertDatabaseHas('ticket_comments', ['ticket_id' => $ticketId, 'content' => 'Comment from API integration testing']);
    }

    public function test_api_flow_create_ticket_then_add_logged_hour_successfully(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $ticketId = $this->createTicketThroughApi('LHR');
        $activity = Activity::create(['name' => 'Testing', 'description' => 'Testing activity']);

        $response = $this->postJson('/api/tickets/' . $ticketId . '/hours', [
            'value' => 1.5,
            'comment' => 'Logged hour from API integration testing',
            'activity_id' => $activity->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('ticket_id', $ticketId);
        $response->assertJsonPath('activity_id', $activity->id);
        $this->assertDatabaseHas('ticket_hours', ['ticket_id' => $ticketId, 'activity_id' => $activity->id]);
    }

    public function test_guest_api_integration_flow_is_rejected(): void
    {
        $projectResponse = $this->getJson('/api/projects');
        $ticketResponse = $this->postJson('/api/tickets', []);

        $projectResponse->assertUnauthorized();
        $ticketResponse->assertUnauthorized();
        $this->assertSame(401, $projectResponse->getStatusCode());
        $this->assertSame(401, $ticketResponse->getStatusCode());
    }

    private function createProjectThroughApi(string $prefix): int
    {
        $status = ProjectStatus::create(['name' => 'Active ' . uniqid(), 'color' => '#16a34a', 'is_default' => true]);

        return $this->postJson('/api/projects', [
            'name' => 'API Integration Project ' . $prefix,
            'status_id' => $status->id,
            'ticket_prefix' => $prefix,
        ])->json('id');
    }

    private function createTicketThroughApi(string $prefix): int
    {
        $projectId = $this->createProjectThroughApi($prefix);

        return $this->postJson('/api/tickets', [
            'project_id' => $projectId,
            'name' => 'API Integration Ticket ' . $prefix,
            'content' => 'Ticket fixture for API integration testing',
            'status_id' => $this->createTicketStatus()->id,
            'type_id' => $this->createTicketType()->id,
            'priority_id' => $this->createTicketPriority()->id,
        ])->json('id');
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
