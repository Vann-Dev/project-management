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

class UserMethodsTest extends TestCase
{
    use RefreshDatabase;

    public function test_total_logged_in_hours_sums_all_ticket_hours_for_user(): void
    {
        // Arrange: Create user with multiple ticket hours
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $project = $this->createProject($user);
        $ticket1 = $this->createTicket($project, $user, $user);
        $ticket2 = $this->createTicket($project, $user, $user);

        // Create multiple hours for the user
        TicketHour::create([
            'ticket_id' => $ticket1->id,
            'user_id' => $user->id,
            'value' => 2.5,
        ]);
        TicketHour::create([
            'ticket_id' => $ticket1->id,
            'user_id' => $user->id,
            'value' => 3.0,
        ]);
        TicketHour::create([
            'ticket_id' => $ticket2->id,
            'user_id' => $user->id,
            'value' => 1.5,
        ]);

        // Act: Get total logged hours
        $user->refresh(); // Refresh to reload relationships
        $totalHours = $user->total_logged_in_hours;

        // Assert: Total should be sum of all hours (2.5 + 3.0 + 1.5 = 7.0)
        $this->assertEquals(7.0, $totalHours);
    }

    public function test_total_logged_in_hours_returns_zero_when_no_hours_logged(): void
    {
        // Arrange: Create user with no ticket hours
        $user = User::factory()->create();

        // Act: Get total logged hours
        $totalHours = $user->total_logged_in_hours;

        // Assert: Should return 0 when no hours logged
        $this->assertEquals(0, $totalHours);
    }

    public function test_total_logged_in_hours_excludes_other_users_hours(): void
    {
        // Arrange: Create two users with hours
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $project = $this->createProject($user);
        $ticket = $this->createTicket($project, $user, $user);

        // Create hours for the target user
        TicketHour::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'value' => 5.0,
        ]);

        // Create hours for other users (should be excluded)
        TicketHour::create([
            'ticket_id' => $ticket->id,
            'user_id' => $otherUser->id,
            'value' => 10.0,
        ]);
        TicketHour::create([
            'ticket_id' => $ticket->id,
            'user_id' => $otherUser->id,
            'value' => 15.0,
        ]);

        // Act: Get total logged hours for each user
        $user->refresh();
        $otherUser->refresh();
        $userTotal = $user->total_logged_in_hours;
        $otherUserTotal = $otherUser->total_logged_in_hours;

        // Assert: Each user should only have their own hours counted
        $this->assertEquals(5.0, $userTotal);
        $this->assertEquals(25.0, $otherUserTotal);
    }

    private function createProject(User $owner): Project
    {
        return Project::create([
            'name' => 'Test Project',
            'description' => 'Test project for unit tests',
            'owner_id' => $owner->id,
            'status_id' => ProjectStatus::create([
                'name' => 'Active_' . uniqid(),
                'color' => '#16a34a',
                'is_default' => true,
            ])->id,
            'ticket_prefix' => 'TST',
            'status_type' => 'default',
            'type' => 'kanban',
        ]);
    }

    private function createTicket(Project $project, User $owner, User $responsible): Ticket
    {
        return Ticket::create([
            'name' => 'Test Ticket',
            'content' => 'Test ticket for unit tests',
            'owner_id' => $owner->id,
            'responsible_id' => $responsible->id,
            'project_id' => $project->id,
            'status_id' => TicketStatus::create([
                'name' => 'Todo_' . uniqid(),
                'color' => '#64748b',
                'is_default' => true,
                'order' => 1,
            ])->id,
            'type_id' => TicketType::create([
                'name' => 'Task_' . uniqid(),
                'icon' => 'heroicon-o-clipboard-document-list',
                'color' => '#3b82f6',
                'is_default' => true,
                'order' => 1,
            ])->id,
            'priority_id' => TicketPriority::create([
                'name' => 'Medium_' . uniqid(),
                'color' => '#eab308',
                'is_default' => true,
                'order' => 1,
            ])->id,
        ]);
    }
}
