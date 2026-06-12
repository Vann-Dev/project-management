<?php

namespace Tests\Unit\Models;

use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Ticket;
use App\Models\TicketHour;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketAccessorsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a ticket with required relationships for testing
     */
    private function createTicket(array $attributes = [], array $hourValues = []): Ticket
    {
        $owner = User::factory()->create();
        $responsible = User::factory()->create();

        $projectStatus = ProjectStatus::create([
            'name' => 'Active_' . uniqid(),
            'color' => '#000000',
            'is_default' => false
        ]);

        $project = Project::create([
            'name' => 'Test Project_' . uniqid(),
            'owner_id' => $owner->id,
            'status_id' => $projectStatus->id,
            'ticket_prefix' => 'TST' . strtoupper(substr(uniqid(), -3))
        ]);

        $ticketStatus = TicketStatus::create([
            'name' => 'Open_' . uniqid(),
            'color' => '#0000ff',
            'is_default' => false,
            'order' => 1,
            'project_id' => $project->id
        ]);

        $ticketType = TicketType::create([
            'name' => 'Task_' . uniqid(),
            'icon' => 'task'
        ]);

        $ticketPriority = TicketPriority::create([
            'name' => 'Medium_' . uniqid(),
            'color' => '#ffaa00'
        ]);

        $ticket = Ticket::create(array_merge([
            'name' => 'Test Ticket_' . uniqid(),
            'content' => 'Test content',
            'owner_id' => $owner->id,
            'responsible_id' => $responsible->id,
            'status_id' => $ticketStatus->id,
            'project_id' => $project->id,
            'type_id' => $ticketType->id,
            'priority_id' => $ticketPriority->id,
            'estimation' => null
        ], $attributes));

        // Create ticket hours if provided
        foreach ($hourValues as $value) {
            TicketHour::create([
                'ticket_id' => $ticket->id,
                'user_id' => $owner->id,
                'value' => $value,
                'comment' => 'Test work'
            ]);
        }

        return $ticket->fresh(['hours', 'project.users', 'owner', 'responsible']);
    }

    public function test_estimation_progress_calculates_correctly_with_logged_hours(): void
    {
        // Create ticket with 10 hours estimation and 5 hours logged
        $ticket = $this->createTicket(
            ['estimation' => 10],
            [2, 2, 1] // Total 5 hours logged
        );

        // (5 * 3600) / (10 * 3600) * 100 = 50%
        $this->assertEquals(50.0, $ticket->estimation_progress);
        $this->assertIsFloat($ticket->estimation_progress);
        $this->assertGreaterThanOrEqual(0, $ticket->estimation_progress);
    }

    public function test_estimation_progress_handles_zero_estimation(): void
    {
        // Create ticket with no estimation but with logged hours
        $ticket = $this->createTicket(
            ['estimation' => null],
            [5]
        );

        // Should handle division by zero gracefully
        // (5 * 3600) / 1 * 100 = 1,800,000% (the implementation divides by estimationInSeconds ?? 1)
        $this->assertIsFloat($ticket->estimation_progress);

        // With 0 estimation, the formula is (logged / 1) * 100
        $expected = (5 * 3600 / 1) * 100; // 1,800,000
        $this->assertEquals($expected, $ticket->estimation_progress);
    }

    public function test_estimation_progress_with_zero_logged_hours(): void
    {
        // Create ticket with estimation but no logged hours
        $ticket = $this->createTicket(
            ['estimation' => 10],
            [] // No hours logged
        );

        // 0 / (10 * 3600) * 100 = 0%
        $this->assertEquals(0.0, $ticket->estimation_progress);
        $this->assertIsFloat($ticket->estimation_progress);
    }

    public function test_estimation_in_seconds_converts_hours_correctly(): void
    {
        // Test with 1 hour
        $ticket1 = $this->createTicket(['estimation' => 1]);
        $this->assertEquals(3600, $ticket1->estimation_in_seconds);

        // Test with 5 hours
        $ticket2 = $this->createTicket(['estimation' => 5]);
        $this->assertEquals(18000, $ticket2->estimation_in_seconds);

        // Test with 10 hours
        $ticket3 = $this->createTicket(['estimation' => 10]);
        $this->assertEquals(36000, $ticket3->estimation_in_seconds);

        // Test with null estimation
        $ticket4 = $this->createTicket(['estimation' => null]);
        $this->assertNull($ticket4->estimation_in_seconds);
    }

    public function test_total_logged_seconds_sums_hours_correctly(): void
    {
        // Create ticket with multiple hour entries: 2h, 3h, 1.5h = 6.5h total
        $ticket = $this->createTicket(
            ['estimation' => 10],
            [2, 3, 1.5]
        );

        // (2 + 3 + 1.5) * 3600 = 23400 seconds
        $this->assertEquals(23400, $ticket->total_logged_seconds);

        // Test ticket with no logged hours
        $ticketWithoutHours = $this->createTicket(['estimation' => 10], []);
        $this->assertEquals(0, $ticketWithoutHours->total_logged_seconds);
    }

    public function test_total_logged_in_hours_sums_correctly(): void
    {
        // Create ticket with multiple hour entries
        $ticket = $this->createTicket(
            ['estimation' => 10],
            [2.5, 3, 1]
        );

        $this->assertEquals(6.5, $ticket->total_logged_in_hours);
        $this->assertIsFloat($ticket->total_logged_in_hours);

        // Test with single hour entry
        $ticket2 = $this->createTicket(['estimation' => 5], [4]);
        $this->assertEquals(4, $ticket2->total_logged_in_hours);
    }

    public function test_watchers_includes_all_unique_users(): void
    {
        $owner = User::factory()->create();
        $responsible = User::factory()->create();
        $projectUser1 = User::factory()->create();
        $projectUser2 = User::factory()->create();

        $projectStatus = ProjectStatus::create([
            'name' => 'Active_' . uniqid(),
            'color' => '#000000',
            'is_default' => false
        ]);

        $project = Project::create([
            'name' => 'Test Project_' . uniqid(),
            'owner_id' => $owner->id,
            'status_id' => $projectStatus->id,
            'ticket_prefix' => 'TST' . strtoupper(substr(uniqid(), -3))
        ]);

        // Add users to project
        $project->users()->attach($projectUser1->id, ['role' => 'developer']);
        $project->users()->attach($projectUser2->id, ['role' => 'developer']);

        $ticketStatus = TicketStatus::create([
            'name' => 'Open_' . uniqid(),
            'color' => '#0000ff',
            'is_default' => false,
            'order' => 1,
            'project_id' => $project->id
        ]);

        $ticketType = TicketType::create([
            'name' => 'Task_' . uniqid(),
            'icon' => 'task'
        ]);

        $ticketPriority = TicketPriority::create([
            'name' => 'Medium_' . uniqid(),
            'color' => '#ffaa00'
        ]);

        $ticket = Ticket::create([
            'name' => 'Test Ticket_' . uniqid(),
            'content' => 'Test content',
            'owner_id' => $owner->id,
            'responsible_id' => $responsible->id,
            'status_id' => $ticketStatus->id,
            'project_id' => $project->id,
            'type_id' => $ticketType->id,
            'priority_id' => $ticketPriority->id
        ]);

        $ticket = $ticket->fresh(['project.users', 'owner', 'responsible']);

        // Should include: 2 project users + owner + responsible = 4 users
        $this->assertCount(4, $ticket->watchers);
        $this->assertTrue($ticket->watchers->contains($owner));
        $this->assertTrue($ticket->watchers->contains($responsible));
        $this->assertTrue($ticket->watchers->contains($projectUser1));
        $this->assertTrue($ticket->watchers->contains($projectUser2));
    }

    public function test_watchers_handles_null_responsible(): void
    {
        $owner = User::factory()->create();
        $projectUser = User::factory()->create();

        $projectStatus = ProjectStatus::create([
            'name' => 'Active_' . uniqid(),
            'color' => '#000000',
            'is_default' => false
        ]);

        $project = Project::create([
            'name' => 'Test Project_' . uniqid(),
            'owner_id' => $owner->id,
            'status_id' => $projectStatus->id,
            'ticket_prefix' => 'TST' . strtoupper(substr(uniqid(), -3))
        ]);

        $project->users()->attach($projectUser->id, ['role' => 'developer']);

        $ticketStatus = TicketStatus::create([
            'name' => 'Open_' . uniqid(),
            'color' => '#0000ff',
            'is_default' => false,
            'order' => 1,
            'project_id' => $project->id
        ]);

        $ticketType = TicketType::create([
            'name' => 'Task_' . uniqid(),
            'icon' => 'task'
        ]);

        $ticketPriority = TicketPriority::create([
            'name' => 'Medium_' . uniqid(),
            'color' => '#ffaa00'
        ]);

        $ticket = Ticket::create([
            'name' => 'Test Ticket_' . uniqid(),
            'content' => 'Test content',
            'owner_id' => $owner->id,
            'responsible_id' => null, // No responsible user
            'status_id' => $ticketStatus->id,
            'project_id' => $project->id,
            'type_id' => $ticketType->id,
            'priority_id' => $ticketPriority->id
        ]);

        $ticket = $ticket->fresh(['project.users', 'owner', 'responsible']);

        // Should include: 1 project user + owner = 2 users (no responsible)
        $this->assertCount(2, $ticket->watchers);
        $this->assertTrue($ticket->watchers->contains($owner));
        $this->assertTrue($ticket->watchers->contains($projectUser));
    }

    public function test_completude_percentage_returns_estimation_progress(): void
    {
        // Create ticket with estimation and logged hours
        $ticket = $this->createTicket(
            ['estimation' => 8],
            [2, 2] // 4 hours logged = 50%
        );

        // completudePercentage should be an alias of estimationProgress
        $this->assertEquals($ticket->estimation_progress, $ticket->completude_percentage);
        $this->assertEquals(50.0, $ticket->completude_percentage);
    }
}
