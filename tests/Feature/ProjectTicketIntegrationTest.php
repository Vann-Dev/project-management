<?php

namespace Tests\Feature;

use App\Models\Activity;
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
use Tests\TestCase;

class ProjectTicketIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_creation_then_ticket_creation_generates_project_scoped_ticket(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user, 'ITG');
        $ticket = $this->createTicket($user, $project);

        $this->assertSame($project->id, $ticket->project->id);
        $this->assertSame($user->id, $ticket->owner->id);
        $this->assertSame('ITG-1', $ticket->code);
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'project_id' => $project->id]);
    }

    public function test_project_member_is_available_through_project_user_pivot_relation(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = $this->createProject($owner, 'MBR');

        $project->users()->attach($member->id, ['role' => 'manager']);
        $project = $project->fresh('users');

        $this->assertCount(1, $project->users);
        $this->assertSame($member->id, $project->users->first()->id);
        $this->assertSame('manager', $project->users->first()->pivot->role);
        $this->assertDatabaseHas('project_users', ['project_id' => $project->id, 'user_id' => $member->id]);
    }

    public function test_sprint_creation_creates_epic_and_ticket_can_be_assigned_to_it(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user, 'SPT');
        $sprint = Sprint::create([
            'name' => 'Integration Sprint',
            'project_id' => $project->id,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addDays(7)->toDateString(),
        ])->fresh();

        $ticket = $this->createTicket($user, $project, ['sprint_id' => $sprint->id])->fresh();

        $this->assertNotNull($sprint->epic_id);
        $this->assertSame($sprint->id, $ticket->sprint_id);
        $this->assertSame($sprint->epic_id, $ticket->epic_id);
        $this->assertDatabaseHas('epics', ['id' => $sprint->epic_id, 'project_id' => $project->id]);
    }

    public function test_ticket_comment_flow_links_user_ticket_and_comment(): void
    {
        $user = User::factory()->create();
        $ticket = $this->createTicket($user, $this->createProject($user, 'CMT'));
        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'content' => 'Integration comment',
        ]);

        $ticket = $ticket->fresh('comments.user');

        $this->assertCount(1, $ticket->comments);
        $this->assertSame($comment->id, $ticket->comments->first()->id);
        $this->assertSame($user->id, $ticket->comments->first()->user->id);
        $this->assertDatabaseHas('ticket_comments', ['id' => $comment->id, 'content' => 'Integration comment']);
    }

    public function test_ticket_hour_flow_updates_ticket_logged_totals(): void
    {
        $user = User::factory()->create();
        $ticket = $this->createTicket($user, $this->createProject($user, 'HRS'), ['estimation' => 4]);
        $activity = Activity::create(['name' => 'Development', 'description' => 'Development work']);

        TicketHour::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'value' => 2,
            'comment' => 'Worked on integration flow',
            'activity_id' => $activity->id,
        ]);

        $ticket = $ticket->fresh('hours.activity');

        $this->assertSame(2.0, $ticket->total_logged_in_hours);
        $this->assertSame(7200.0, $ticket->total_logged_seconds);
        $this->assertSame(50.0, $ticket->estimation_progress);
        $this->assertSame($activity->id, $ticket->hours->first()->activity->id);
    }

    public function test_ticket_status_update_creates_ticket_activity_record(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user, 'STA');
        $todo = $this->createTicketStatus('Todo');
        $done = $this->createTicketStatus('Done');
        $ticket = $this->createTicket($user, $project, ['status_id' => $todo->id]);

        $ticket->update(['status_id' => $done->id]);

        $this->assertSame($done->id, $ticket->fresh()->status_id);
        $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $ticket->id, 'status_id' => $done->id]);
        $this->assertCount(1, $ticket->fresh('activities')->activities);
        $this->assertSame($done->id, $ticket->fresh('activities')->activities->first()->status_id);
    }

    private function createProject(User $owner, string $prefix): Project
    {
        return Project::create([
            'name' => 'Integration Project ' . $prefix,
            'description' => 'Project fixture for integration tests',
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

    private function createTicket(User $owner, Project $project, array $overrides = []): Ticket
    {
        return Ticket::create(array_merge([
            'name' => 'Integration Ticket',
            'content' => 'Ticket fixture for integration tests',
            'owner_id' => $owner->id,
            'responsible_id' => $owner->id,
            'status_id' => $this->createTicketStatus('Todo')->id,
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
            'estimation' => 3,
        ], $overrides));
    }

    private function createTicketStatus(string $name): TicketStatus
    {
        return TicketStatus::create([
            'name' => $name . ' ' . uniqid(),
            'color' => '#64748b',
            'is_default' => false,
            'order' => random_int(1, 1000),
        ]);
    }
}
