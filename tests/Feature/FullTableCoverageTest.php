<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Epic;
use App\Models\Project;
use App\Models\ProjectFavorite;
use App\Models\ProjectStatus;
use App\Models\Sprint;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketSubscriber;
use App\Models\TicketType;
use App\Models\TimeSheet;
use App\Models\TimeSheetCell;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FullTableCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_statuses_crud_operations(): void
    {
        // Create project status
        $status = ProjectStatus::create([
            'name' => 'Active',
            'color' => '#00ff00',
            'is_default' => true,
        ]);

        $this->assertDatabaseHas('project_statuses', [
            'name' => 'Active',
            'color' => '#00ff00',
            'is_default' => true,
        ]);

        // Update status
        $status->update(['name' => 'Completed']);

        $this->assertDatabaseHas('project_statuses', [
            'id' => $status->id,
            'name' => 'Completed',
        ]);

        // Soft delete
        $status->delete();

        $this->assertSoftDeleted('project_statuses', [
            'id' => $status->id,
        ]);
    }

    public function test_project_favorites_for_users(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user, 'FAV');

        // Add project to favorites
        ProjectFavorite::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
        ]);

        $this->assertDatabaseHas('project_favorites', [
            'user_id' => $user->id,
            'project_id' => $project->id,
        ]);

        // Remove from favorites
        ProjectFavorite::where('user_id', $user->id)
            ->where('project_id', $project->id)
            ->delete();

        $this->assertDatabaseMissing('project_favorites', [
            'user_id' => $user->id,
            'project_id' => $project->id,
        ]);
    }

    public function test_ticket_types_with_defaults(): void
    {
        // Create multiple ticket types
        $task = TicketType::create([
            'name' => 'Task',
            'icon' => 'heroicon-o-clipboard',
            'color' => '#06b6d4',
            'is_default' => true,
        ]);

        $bug = TicketType::create([
            'name' => 'Bug',
            'icon' => 'heroicon-o-bug-ant',
            'color' => '#ef4444',
            'is_default' => false,
        ]);

        $this->assertDatabaseHas('ticket_types', [
            'name' => 'Task',
            'is_default' => true,
        ]);

        $this->assertDatabaseHas('ticket_types', [
            'name' => 'Bug',
            'is_default' => false,
        ]);

        // Test soft delete
        $bug->delete();

        $this->assertSoftDeleted('ticket_types', [
            'id' => $bug->id,
        ]);
    }

    public function test_ticket_priorities_hierarchy(): void
    {
        // Create priority levels
        $low = TicketPriority::create([
            'name' => 'Low',
            'color' => '#22c55e',
            'is_default' => false,
        ]);

        $normal = TicketPriority::create([
            'name' => 'Normal',
            'color' => '#64748b',
            'is_default' => true,
        ]);

        $high = TicketPriority::create([
            'name' => 'High',
            'color' => '#ef4444',
            'is_default' => false,
        ]);

        $this->assertDatabaseHas('ticket_priorities', [
            'name' => 'Normal',
            'is_default' => true,
        ]);

        // Verify ticket can use priority
        $user = User::factory()->create();
        $ticket = $this->createTicket($user, null, $high);

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'priority_id' => $high->id,
        ]);
    }

    public function test_ticket_statuses_per_project(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user, 'STS');

        // Create project-specific statuses
        $backlog = TicketStatus::create([
            'name' => 'Backlog',
            'color' => '#64748b',
            'is_default' => true,
            'order' => 1,
            'project_id' => $project->id,
        ]);

        $inProgress = TicketStatus::create([
            'name' => 'In Progress',
            'color' => '#3b82f6',
            'is_default' => false,
            'order' => 2,
            'project_id' => $project->id,
        ]);

        $this->assertDatabaseHas('ticket_statuses', [
            'name' => 'Backlog',
            'project_id' => $project->id,
            'order' => 1,
        ]);

        $this->assertDatabaseHas('ticket_statuses', [
            'name' => 'In Progress',
            'project_id' => $project->id,
            'order' => 2,
        ]);

        // Test soft delete
        $inProgress->delete();

        $this->assertSoftDeleted('ticket_statuses', [
            'id' => $inProgress->id,
        ]);
    }

    public function test_ticket_subscribers_many_to_many(): void
    {
        $owner = User::factory()->create();
        $subscriber1 = User::factory()->create();
        $subscriber2 = User::factory()->create();
        $subscriber3 = User::factory()->create();

        $ticket = $this->createTicket($owner);

        // Add multiple subscribers
        TicketSubscriber::create([
            'ticket_id' => $ticket->id,
            'user_id' => $subscriber1->id,
        ]);

        TicketSubscriber::create([
            'ticket_id' => $ticket->id,
            'user_id' => $subscriber2->id,
        ]);

        TicketSubscriber::create([
            'ticket_id' => $ticket->id,
            'user_id' => $subscriber3->id,
        ]);

        // Assert all subscribers exist
        $this->assertDatabaseHas('ticket_subscribers', [
            'ticket_id' => $ticket->id,
            'user_id' => $subscriber1->id,
        ]);

        $this->assertDatabaseHas('ticket_subscribers', [
            'ticket_id' => $ticket->id,
            'user_id' => $subscriber2->id,
        ]);

        // Remove one subscriber
        TicketSubscriber::where('ticket_id', $ticket->id)
            ->where('user_id', $subscriber2->id)
            ->delete();

        $this->assertDatabaseMissing('ticket_subscribers', [
            'ticket_id' => $ticket->id,
            'user_id' => $subscriber2->id,
        ]);

        // Verify others remain
        $this->assertDatabaseHas('ticket_subscribers', [
            'ticket_id' => $ticket->id,
            'user_id' => $subscriber1->id,
        ]);
    }

    public function test_ticket_relations_and_dependencies(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user, 'REL');

        $parentTicket = $this->createTicket($user, $project);
        $childTicket = $this->createTicket($user, $project);
        $blockedTicket = $this->createTicket($user, $project);

        // Create ticket relations
        $relation1 = \App\Models\TicketRelation::create([
            'ticket_id' => $parentTicket->id,
            'relation_id' => $childTicket->id,
            'type' => 'blocks',
            'sort' => 1,
        ]);

        $relation2 = \App\Models\TicketRelation::create([
            'ticket_id' => $childTicket->id,
            'relation_id' => $blockedTicket->id,
            'type' => 'blocks',
            'sort' => 1,
        ]);

        // Assert relations exist
        $this->assertDatabaseHas('ticket_relations', [
            'ticket_id' => $parentTicket->id,
            'relation_id' => $childTicket->id,
            'type' => 'blocks',
        ]);

        $this->assertDatabaseHas('ticket_relations', [
            'ticket_id' => $childTicket->id,
            'relation_id' => $blockedTicket->id,
            'type' => 'blocks',
        ]);

        // Verify sort order
        $this->assertDatabaseHas('ticket_relations', [
            'id' => $relation1->id,
            'sort' => 1,
        ]);

        // Test bidirectional relation
        $reverseRelation = \App\Models\TicketRelation::create([
            'ticket_id' => $blockedTicket->id,
            'relation_id' => $childTicket->id,
            'type' => 'blocked_by',
            'sort' => 1,
        ]);

        $this->assertDatabaseHas('ticket_relations', [
            'ticket_id' => $blockedTicket->id,
            'relation_id' => $childTicket->id,
            'type' => 'blocked_by',
        ]);
    }

    public function test_ticket_activities_track_status_changes(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user, 'ACT');

        $todoStatus = $this->createTicketStatus('Todo', $project, 1);
        $inProgressStatus = $this->createTicketStatus('In Progress', $project, 2);
        $doneStatus = $this->createTicketStatus('Done', $project, 3);

        $ticket = $this->createTicket($user, $project);

        // Manually create ticket activities to test the table
        $activity1 = TicketActivity::create([
            'ticket_id' => $ticket->id,
            'old_status_id' => $todoStatus->id,
            'new_status_id' => $inProgressStatus->id,
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'old_status_id' => $todoStatus->id,
            'new_status_id' => $inProgressStatus->id,
            'user_id' => $user->id,
        ]);

        // Create second activity
        $activity2 = TicketActivity::create([
            'ticket_id' => $ticket->id,
            'old_status_id' => $inProgressStatus->id,
            'new_status_id' => $doneStatus->id,
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'old_status_id' => $inProgressStatus->id,
            'new_status_id' => $doneStatus->id,
        ]);

        // Verify we have 2 activity records for this ticket
        $activityCount = TicketActivity::where('ticket_id', $ticket->id)->count();
        $this->assertEquals(2, $activityCount);
    }

    public function test_activities_for_time_tracking(): void
    {
        // Create activities
        $programming = Activity::create([
            'name' => 'Programming',
            'description' => 'Software development work',
        ]);

        $testing = Activity::create([
            'name' => 'Testing',
            'description' => 'QA and testing work',
        ]);

        $this->assertDatabaseHas('activities', [
            'name' => 'Programming',
        ]);

        $this->assertDatabaseHas('activities', [
            'name' => 'Testing',
        ]);

        // Link ticket hour to activity
        $user = User::factory()->create();
        $ticket = $this->createTicket($user);

        $hour = \App\Models\TicketHour::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'value' => 3.5,
            'comment' => 'Implemented feature',
            'activity_id' => $programming->id,
        ]);

        $this->assertDatabaseHas('ticket_hours', [
            'id' => $hour->id,
            'activity_id' => $programming->id,
        ]);

        // Test soft delete
        $testing->delete();

        $this->assertSoftDeleted('activities', [
            'id' => $testing->id,
        ]);
    }

    public function test_epic_hierarchy_with_parent(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user, 'EPC');

        // Create parent epic
        $parentEpic = Epic::create([
            'name' => 'Q1 2026 Initiative',
            'project_id' => $project->id,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonths(3)->toDateString(),
        ]);

        $this->assertDatabaseHas('epics', [
            'name' => 'Q1 2026 Initiative',
            'project_id' => $project->id,
            'parent_id' => null,
        ]);

        // Create child epic
        $childEpic = Epic::create([
            'name' => 'January Sprint',
            'project_id' => $project->id,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addWeeks(2)->toDateString(),
            'parent_id' => $parentEpic->id,
        ]);

        $this->assertDatabaseHas('epics', [
            'name' => 'January Sprint',
            'parent_id' => $parentEpic->id,
        ]);

        // Verify ticket can belong to epic
        $ticket = $this->createTicket($user, $project);
        $ticket->update(['epic_id' => $childEpic->id]);

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'epic_id' => $childEpic->id,
        ]);

        // Test soft delete
        $childEpic->delete();

        $this->assertSoftDeleted('epics', [
            'id' => $childEpic->id,
        ]);
    }

    public function test_sprint_with_epic_and_tickets(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user, 'SPR');

        // Create sprint - it will auto-create an epic with the same name
        $sprint = Sprint::create([
            'name' => 'Sprint 1',
            'description' => 'First sprint of the epic',
            'project_id' => $project->id,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addWeeks(2)->toDateString(),
        ])->fresh();

        $this->assertDatabaseHas('sprints', [
            'name' => 'Sprint 1',
            'project_id' => $project->id,
        ]);

        // Assert epic was auto-created
        $this->assertNotNull($sprint->epic_id);
        $this->assertDatabaseHas('epics', [
            'id' => $sprint->epic_id,
            'name' => 'Sprint 1',
            'project_id' => $project->id,
        ]);

        // Add tickets to sprint
        $ticket1 = $this->createTicket($user, $project);
        $ticket1->update(['sprint_id' => $sprint->id]);

        $ticket2 = $this->createTicket($user, $project);
        $ticket2->update(['sprint_id' => $sprint->id]);

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket1->id,
            'sprint_id' => $sprint->id,
        ]);

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket2->id,
            'sprint_id' => $sprint->id,
        ]);

        // Verify sprint dates
        $this->assertNotNull($sprint->starts_at);
        $this->assertNotNull($sprint->ends_at);

        // Test soft delete
        $sprint->delete();

        $this->assertSoftDeleted('sprints', [
            'id' => $sprint->id,
        ]);
    }

    // Helper methods

    private function createProject(User $owner, string $prefix): Project
    {
        return Project::create([
            'name' => 'Test Project ' . $prefix,
            'description' => 'Test project for database coverage',
            'owner_id' => $owner->id,
            'status_id' => $this->createProjectStatus()->id,
            'ticket_prefix' => $prefix,
            'status_type' => 'default',
            'type' => 'kanban',
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

    private function createTicket(User $owner, ?Project $project = null, ?TicketPriority $priority = null): Ticket
    {
        if ($project === null) {
            $project = $this->createProject($owner, strtoupper(substr(uniqid(), -3)));
        }

        if ($priority === null) {
            $priority = TicketPriority::create([
                'name' => 'Normal ' . uniqid(),
                'color' => '#64748b',
                'is_default' => true,
            ]);
        }

        $status = $this->createTicketStatus('Todo', $project);
        $type = TicketType::create([
            'name' => 'Task ' . uniqid(),
            'icon' => 'heroicon-o-clipboard',
            'color' => '#06b6d4',
            'is_default' => true,
        ]);

        return Ticket::create([
            'name' => 'Test Ticket ' . uniqid(),
            'content' => 'Test ticket for database coverage',
            'owner_id' => $owner->id,
            'responsible_id' => $owner->id,
            'status_id' => $status->id,
            'project_id' => $project->id,
            'type_id' => $type->id,
            'priority_id' => $priority->id,
            'estimation' => 5.0,
        ]);
    }

    private function createTicketStatus(string $name, ?Project $project = null, int $order = 0): TicketStatus
    {
        return TicketStatus::create([
            'name' => $name . ' ' . uniqid(),
            'color' => '#64748b',
            'is_default' => true,
            'order' => $order,
            'project_id' => $project?->id,
        ]);
    }
}

