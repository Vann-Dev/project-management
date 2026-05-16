<?php

namespace Tests\Feature;

use App\Models\Activity;
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
use Tests\TestCase;

class DatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_stored_in_database(): void
    {
        $user = User::factory()->create([
            'name' => 'Database User',
            'email' => 'database-user@example.com',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Database User',
            'email' => 'database-user@example.com',
        ]);
    }

    public function test_project_can_be_stored_with_owner_and_status(): void
    {
        $user = User::factory()->create();
        $status = $this->createProjectStatus();

        $project = Project::create([
            'name' => 'Project Database Test',
            'description' => 'Project created by database testing',
            'owner_id' => $user->id,
            'status_id' => $status->id,
            'ticket_prefix' => 'PDB',
            'status_type' => 'default',
            'type' => 'kanban',
        ]);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'owner_id' => $user->id,
            'status_id' => $status->id,
            'ticket_prefix' => 'PDB',
        ]);
    }

    public function test_ticket_can_be_stored_with_required_relations(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user, 'TDB');
        $status = $this->createTicketStatus();
        $type = $this->createTicketType();
        $priority = $this->createTicketPriority();

        $ticket = Ticket::create([
            'name' => 'Ticket Database Test',
            'content' => 'Ticket created by database testing',
            'owner_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => $status->id,
            'project_id' => $project->id,
            'type_id' => $type->id,
            'priority_id' => $priority->id,
            'estimation' => 5,
        ]);

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'name' => 'Ticket Database Test',
            'project_id' => $project->id,
            'status_id' => $status->id,
            'type_id' => $type->id,
            'priority_id' => $priority->id,
        ]);
    }

    public function test_ticket_comment_can_be_stored_in_database(): void
    {
        $user = User::factory()->create();
        $ticket = $this->createTicket($user);

        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'content' => 'Database testing comment',
        ]);

        $this->assertDatabaseHas('ticket_comments', [
            'id' => $comment->id,
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'content' => 'Database testing comment',
        ]);
    }

    public function test_ticket_hour_can_be_stored_with_activity(): void
    {
        $user = User::factory()->create();
        $ticket = $this->createTicket($user);
        $activity = Activity::create([
            'name' => 'Development',
            'description' => 'Coding activity',
        ]);

        $hour = TicketHour::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'value' => 2.5,
            'comment' => 'Implementing feature',
            'activity_id' => $activity->id,
        ]);

        $this->assertDatabaseHas('ticket_hours', [
            'id' => $hour->id,
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'value' => 2.5,
            'activity_id' => $activity->id,
        ]);
    }

    public function test_project_uses_soft_delete(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user, 'SDP');

        $project->delete();

        $this->assertSoftDeleted('projects', [
            'id' => $project->id,
        ]);
    }

    public function test_ticket_uses_soft_delete(): void
    {
        $user = User::factory()->create();
        $ticket = $this->createTicket($user);

        $ticket->delete();

        $this->assertSoftDeleted('tickets', [
            'id' => $ticket->id,
        ]);
    }

    public function test_sprint_creation_creates_related_epic(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user, 'SPR');

        $sprint = Sprint::create([
            'name' => 'Sprint Database Test',
            'description' => 'Sprint created by database testing',
            'project_id' => $project->id,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addDays(14)->toDateString(),
        ])->fresh();

        $this->assertDatabaseHas('sprints', [
            'id' => $sprint->id,
            'name' => 'Sprint Database Test',
            'project_id' => $project->id,
        ]);

        $this->assertDatabaseHas('epics', [
            'id' => $sprint->epic_id,
            'name' => 'Sprint Database Test',
            'project_id' => $project->id,
        ]);
    }

    public function test_epic_can_have_parent_epic(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user, 'EPC');

        $parent = Epic::create([
            'name' => 'Parent Epic',
            'project_id' => $project->id,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
        ]);

        $child = Epic::create([
            'name' => 'Child Epic',
            'project_id' => $project->id,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addWeeks(2)->toDateString(),
            'parent_id' => $parent->id,
        ]);

        $this->assertDatabaseHas('epics', [
            'id' => $child->id,
            'name' => 'Child Epic',
            'parent_id' => $parent->id,
        ]);
    }

    public function test_project_user_pivot_can_be_stored(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = $this->createProject($owner, 'PVT');

        $project->users()->attach($member->id, [
            'role' => 'manager',
        ]);

        $this->assertDatabaseHas('project_users', [
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role' => 'manager',
        ]);
    }

    private function createProject(User $owner, string $prefix): Project
    {
        return Project::create([
            'name' => 'Database Project ' . $prefix,
            'description' => 'Reusable database project fixture',
            'owner_id' => $owner->id,
            'status_id' => $this->createProjectStatus()->id,
            'ticket_prefix' => $prefix,
            'status_type' => 'default',
            'type' => 'kanban',
        ]);
    }

    private function createTicket(User $owner): Ticket
    {
        $project = $this->createProject($owner, strtoupper(substr(uniqid(), -3)));

        return Ticket::create([
            'name' => 'Database Ticket',
            'content' => 'Reusable database ticket fixture',
            'owner_id' => $owner->id,
            'responsible_id' => $owner->id,
            'status_id' => $this->createTicketStatus()->id,
            'project_id' => $project->id,
            'type_id' => $this->createTicketType()->id,
            'priority_id' => $this->createTicketPriority()->id,
            'estimation' => 3,
        ]);
    }

    private function createProjectStatus(): ProjectStatus
    {
        return ProjectStatus::create([
            'name' => 'Active ' . uniqid(),
            'color' => '#16a34a',
            'is_default' => true,
        ]);
    }

    private function createTicketStatus(): TicketStatus
    {
        return TicketStatus::create([
            'name' => 'Todo ' . uniqid(),
            'color' => '#64748b',
            'is_default' => true,
            'order' => 1,
        ]);
    }

    private function createTicketType(): TicketType
    {
        return TicketType::create([
            'name' => 'Bug ' . uniqid(),
            'icon' => 'heroicon-o-bug-ant',
            'color' => '#ef4444',
            'is_default' => true,
        ]);
    }

    private function createTicketPriority(): TicketPriority
    {
        return TicketPriority::create([
            'name' => 'High ' . uniqid(),
            'color' => '#f97316',
            'is_default' => true,
        ]);
    }
}
