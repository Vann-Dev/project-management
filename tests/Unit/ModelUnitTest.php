<?php

namespace Tests\Unit;

use App\Models\Activity;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Sprint;
use App\Models\Ticket;
use App\Models\TicketHour;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelUnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_cover_returns_default_avatar_when_no_media_exists(): void
    {
        $project = $this->createProject(User::factory()->create(), 'COV');

        $this->assertIsString($project->cover);
        $this->assertStringStartsWith('https://ui-avatars.com/api/', $project->cover);
        $this->assertStringContainsString('name=' . $project->name, $project->cover);
        $this->assertStringContainsString('background=3f84f3', $project->cover);
    }

    public function test_project_contributors_merge_owner_and_members_uniquely(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = $this->createProject($owner, 'CON');

        $project->users()->attach($member->id, ['role' => 'manager']);
        $contributors = $project->fresh()->contributors;

        $this->assertCount(2, $contributors);
        $this->assertTrue($contributors->contains('id', $owner->id));
        $this->assertTrue($contributors->contains('id', $member->id));
        $this->assertSame([1, 1], $contributors->pluck('id')->countBy()->values()->all());
    }

    public function test_project_current_sprint_returns_started_sprint_without_end(): void
    {
        $project = $this->createProject(User::factory()->create(), 'CUR');
        $current = Sprint::create([
            'name' => 'Current Sprint',
            'project_id' => $project->id,
            'starts_at' => now()->subDays(2)->toDateString(),
            'ends_at' => now()->addDays(5)->toDateString(),
            'started_at' => now()->subDay(),
        ]);

        $this->assertNotNull($project->fresh()->current_sprint);
        $this->assertSame($current->id, $project->fresh()->current_sprint->id);
        $this->assertSame('Current Sprint', $project->fresh()->current_sprint->name);
        $this->assertNull($project->fresh()->current_sprint->ended_at);
    }

    public function test_project_next_sprint_returns_future_sprint_after_current(): void
    {
        $project = $this->createProject(User::factory()->create(), 'NXT');
        Sprint::create([
            'name' => 'Current Sprint',
            'project_id' => $project->id,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addDays(7)->toDateString(),
            'started_at' => now(),
        ]);
        $next = Sprint::create([
            'name' => 'Next Sprint',
            'project_id' => $project->id,
            'starts_at' => now()->addDays(8)->toDateString(),
            'ends_at' => now()->addDays(14)->toDateString(),
        ]);

        $this->assertNotNull($project->fresh()->next_sprint);
        $this->assertSame($next->id, $project->fresh()->next_sprint->id);
        $this->assertSame('Next Sprint', $project->fresh()->next_sprint->name);
        $this->assertNull($project->fresh()->next_sprint->started_at);
    }

    public function test_ticket_estimation_is_converted_to_seconds_and_human_text(): void
    {
        $ticket = $this->createTicket(User::factory()->create(), estimation: 2);

        $this->assertSame(7200, $ticket->estimation_in_seconds);
        $this->assertIsString($ticket->estimation_for_humans);
        $this->assertStringContainsString('2 hours', $ticket->estimation_for_humans);
        $this->assertGreaterThan(0, $ticket->estimation_in_seconds);
    }

    public function test_ticket_total_logged_hours_and_seconds_are_calculated(): void
    {
        $user = User::factory()->create();
        $ticket = $this->createTicket($user);
        TicketHour::create(['ticket_id' => $ticket->id, 'user_id' => $user->id, 'value' => 1.5]);
        TicketHour::create(['ticket_id' => $ticket->id, 'user_id' => $user->id, 'value' => 2]);

        $ticket = $ticket->fresh('hours');

        $this->assertSame(3.5, $ticket->total_logged_in_hours);
        $this->assertSame(12600.0, $ticket->total_logged_seconds);
        $this->assertCount(2, $ticket->hours);
        $this->assertGreaterThan($ticket->total_logged_in_hours, $ticket->total_logged_seconds);
    }

    public function test_ticket_estimation_progress_uses_logged_seconds_against_estimation(): void
    {
        $user = User::factory()->create();
        $ticket = $this->createTicket($user, estimation: 4);
        TicketHour::create(['ticket_id' => $ticket->id, 'user_id' => $user->id, 'value' => 1]);

        $ticket = $ticket->fresh('hours');

        $this->assertSame(14400, $ticket->estimation_in_seconds);
        $this->assertSame(3600.0, $ticket->total_logged_seconds);
        $this->assertSame(25.0, $ticket->estimation_progress);
        $this->assertSame($ticket->estimation_progress, $ticket->completude_percentage);
    }

    public function test_ticket_hour_for_humans_returns_readable_duration(): void
    {
        $user = User::factory()->create();
        $ticket = $this->createTicket($user);
        $activity = Activity::create(['name' => 'Testing', 'description' => 'Testing activity']);
        $hour = TicketHour::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'value' => 1.5,
            'activity_id' => $activity->id,
        ]);

        $this->assertIsString($hour->for_humans);
        $this->assertStringContainsString('1 hour', $hour->for_humans);
        $this->assertStringContainsString('30 minutes', $hour->for_humans);
        $this->assertSame($activity->id, $hour->activity_id);
    }

    public function test_ticket_creation_generates_code_and_order_from_project_prefix(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user, 'AUT');
        $ticket = $this->createTicket($user, $project);

        $this->assertSame('AUT-1', $ticket->code);
        $this->assertSame(1, $ticket->order);
        $this->assertSame($project->id, $ticket->project_id);
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'code' => 'AUT-1']);
    }

    public function test_sprint_remaining_returns_days_when_sprint_is_active(): void
    {
        $project = $this->createProject(User::factory()->create(), 'REM');
        $sprint = Sprint::create([
            'name' => 'Remaining Sprint',
            'project_id' => $project->id,
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addDays(2)->toDateString(),
            'started_at' => now()->subDay(),
        ]);

        $this->assertIsInt($sprint->remaining);
        $this->assertGreaterThanOrEqual(1, $sprint->remaining);
        $this->assertLessThanOrEqual(3, $sprint->remaining);
        $this->assertNull($sprint->ended_at);
    }

    private function createProject(User $owner, string $prefix): Project
    {
        return Project::create([
            'name' => 'Unit Project ' . $prefix,
            'description' => 'Project fixture for unit tests',
            'owner_id' => $owner->id,
            'status_id' => ProjectStatus::create([
                'name' => 'Active ' . uniqid(),
                'color' => '#16a34a',
                'is_default' => true,
            ])->id,
            'ticket_prefix' => $prefix,
            'status_type' => 'default',
            'type' => 'kanban',
        ]);
    }

    private function createTicket(User $owner, ?Project $project = null, int|float $estimation = 3): Ticket
    {
        $project ??= $this->createProject($owner, strtoupper(substr(uniqid(), -3)));

        return Ticket::create([
            'name' => 'Unit Ticket',
            'content' => 'Ticket fixture for unit tests',
            'owner_id' => $owner->id,
            'responsible_id' => $owner->id,
            'status_id' => TicketStatus::create([
                'name' => 'Todo ' . uniqid(),
                'color' => '#64748b',
                'is_default' => true,
                'order' => 1,
            ])->id,
            'project_id' => $project->id,
            'type_id' => TicketType::create([
                'name' => 'Bug ' . uniqid(),
                'icon' => 'heroicon-o-bug-ant',
                'color' => '#ef4444',
                'is_default' => true,
            ])->id,
            'priority_id' => TicketPriority::create([
                'name' => 'High ' . uniqid(),
                'color' => '#f97316',
                'is_default' => true,
            ])->id,
            'estimation' => $estimation,
        ]);
    }
}
