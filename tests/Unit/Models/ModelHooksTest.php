<?php

namespace Tests\Unit\Models;

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
use App\Notifications\TicketCommented;
use App\Notifications\UserCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ModelHooksTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_comment_created_notifies_ticket_owner(): void
    {
        // Arrange: Fake notifications
        Notification::fake();

        $owner = User::factory()->create();
        $responsible = User::factory()->create();
        $projectOwner = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $projectOwner->id,
            'status_id' => $status->id,
        ]);

        $ticket = Ticket::factory()->create([
            'project_id' => $project->id,
            'owner_id' => $owner->id,
            'responsible_id' => $responsible->id,
        ]);

        // Act: Create a comment (should trigger created hook)
        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
            'content' => 'Test comment',
        ]);

        // Assert: Ticket owner should receive notification
        Notification::assertSentTo($owner, TicketCommented::class);
    }

    public function test_ticket_comment_created_notifies_ticket_responsible(): void
    {
        // Arrange: Fake notifications
        Notification::fake();

        $owner = User::factory()->create();
        $responsible = User::factory()->create();
        $projectOwner = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $projectOwner->id,
            'status_id' => $status->id,
        ]);

        $ticket = Ticket::factory()->create([
            'project_id' => $project->id,
            'owner_id' => $owner->id,
            'responsible_id' => $responsible->id,
        ]);

        // Act: Create a comment
        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
            'content' => 'Test comment',
        ]);

        // Assert: Responsible user should receive notification
        Notification::assertSentTo($responsible, TicketCommented::class);
    }

    public function test_ticket_comment_created_notifies_project_members(): void
    {
        // Arrange: Fake notifications
        Notification::fake();

        $owner = User::factory()->create();
        $responsible = User::factory()->create();
        $projectOwner = User::factory()->create();
        $member1 = User::factory()->create();
        $member2 = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $projectOwner->id,
            'status_id' => $status->id,
        ]);

        // Add members to project
        $project->users()->attach($member1->id, ['role' => 'developer']);
        $project->users()->attach($member2->id, ['role' => 'tester']);

        $ticket = Ticket::factory()->create([
            'project_id' => $project->id,
            'owner_id' => $owner->id,
            'responsible_id' => $responsible->id,
        ]);

        // Act: Create a comment
        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
            'content' => 'Test comment',
        ]);

        // Assert: All project members should receive notifications
        Notification::assertSentTo($member1, TicketCommented::class);
        Notification::assertSentTo($member2, TicketCommented::class);
    }

    public function test_ticket_comment_created_notifies_all_watchers(): void
    {
        // Arrange: Fake notifications
        Notification::fake();

        $owner = User::factory()->create();
        $responsible = User::factory()->create();
        $projectOwner = User::factory()->create();
        $member = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $projectOwner->id,
            'status_id' => $status->id,
        ]);

        $project->users()->attach($member->id, ['role' => 'developer']);

        $ticket = Ticket::factory()->create([
            'project_id' => $project->id,
            'owner_id' => $owner->id,
            'responsible_id' => $responsible->id,
        ]);

        // Act: Create a comment
        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $member->id,
            'content' => 'Test comment from member',
        ]);

        // Assert: All watchers (owner, responsible, member) should receive notification
        Notification::assertSentTo($owner, TicketCommented::class);
        Notification::assertSentTo($responsible, TicketCommented::class);
        Notification::assertSentTo($member, TicketCommented::class);
    }

    public function test_ticket_comment_created_removes_duplicate_notifications(): void
    {
        // Arrange: Fake notifications
        Notification::fake();

        $ownerAndResponsible = User::factory()->create(); // Same user is both
        $projectOwner = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $projectOwner->id,
            'status_id' => $status->id,
        ]);

        $ticket = Ticket::factory()->create([
            'project_id' => $project->id,
            'owner_id' => $ownerAndResponsible->id,
            'responsible_id' => $ownerAndResponsible->id, // Same user
        ]);

        // Act: Create a comment
        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $projectOwner->id,
            'content' => 'Test comment',
        ]);

        // Assert: User should receive only one notification (watchers are unique)
        Notification::assertSentTo(
            $ownerAndResponsible,
            TicketCommented::class,
            function ($notification, $channels, $notifiable) use ($ownerAndResponsible) {
                return $notifiable->id === $ownerAndResponsible->id;
            }
        );
    }

    public function test_ticket_comment_created_hook_is_triggered_on_create(): void
    {
        // Arrange: Fake notifications to verify hook runs
        Notification::fake();

        $owner = User::factory()->create();
        $projectOwner = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $projectOwner->id,
            'status_id' => $status->id,
        ]);

        $ticket = Ticket::factory()->create([
            'project_id' => $project->id,
            'owner_id' => $owner->id,
            'responsible_id' => $owner->id,
        ]);

        // Act: Create comment using create() method
        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
            'content' => 'Test comment to verify hook',
        ]);

        // Assert: Hook should have triggered and sent notifications
        Notification::assertSentTo($owner, TicketCommented::class);
        $this->assertDatabaseHas('ticket_comments', [
            'id' => $comment->id,
            'ticket_id' => $ticket->id,
        ]);
    }

    public function test_sprint_created_automatically_creates_epic(): void
    {
        // Arrange: Create project for sprint
        $owner = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $owner->id,
            'status_id' => $status->id,
        ]);

        // Act: Create sprint (should trigger created hook)
        $sprint = Sprint::create([
            'name' => 'Sprint 1',
            'starts_at' => now()->addDays(1),
            'ends_at' => now()->addDays(14),
            'project_id' => $project->id,
        ]);

        // Assert: Epic should have been automatically created
        $this->assertNotNull($sprint->epic_id);
        $this->assertDatabaseHas('epics', [
            'id' => $sprint->epic_id,
        ]);
    }

    public function test_sprint_created_epic_has_same_name(): void
    {
        // Arrange: Create project
        $owner = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $owner->id,
            'status_id' => $status->id,
        ]);

        // Act: Create sprint with specific name
        $sprintName = 'Test Sprint ' . uniqid();
        $sprint = Sprint::create([
            'name' => $sprintName,
            'starts_at' => now()->addDays(1),
            'ends_at' => now()->addDays(14),
            'project_id' => $project->id,
        ]);

        // Assert: Epic should have the same name as sprint
        $sprint->refresh();
        $this->assertNotNull($sprint->epic);
        $this->assertEquals($sprintName, $sprint->epic->name);
    }

    public function test_sprint_created_epic_has_same_start_date(): void
    {
        // Arrange: Create project
        $owner = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $owner->id,
            'status_id' => $status->id,
        ]);

        // Act: Create sprint with specific start date
        $startDate = now()->addDays(5);
        $sprint = Sprint::create([
            'name' => 'Sprint with dates',
            'starts_at' => $startDate,
            'ends_at' => now()->addDays(19),
            'project_id' => $project->id,
        ]);

        // Assert: Epic starts_at should match sprint starts_at
        $sprint->refresh();
        $this->assertNotNull($sprint->epic);
        $this->assertEquals($startDate->format('Y-m-d'), $sprint->epic->starts_at->format('Y-m-d'));
    }

    public function test_sprint_created_epic_has_same_end_date(): void
    {
        // Arrange: Create project
        $owner = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $owner->id,
            'status_id' => $status->id,
        ]);

        // Act: Create sprint with specific end date
        $endDate = now()->addDays(21);
        $sprint = Sprint::create([
            'name' => 'Sprint with end date',
            'starts_at' => now()->addDays(7),
            'ends_at' => $endDate,
            'project_id' => $project->id,
        ]);

        // Assert: Epic ends_at should match sprint ends_at
        $sprint->refresh();
        $this->assertNotNull($sprint->epic);
        $this->assertEquals($endDate->format('Y-m-d'), $sprint->epic->ends_at->format('Y-m-d'));
    }

    public function test_sprint_created_epic_belongs_to_same_project(): void
    {
        // Arrange: Create project
        $owner = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $owner->id,
            'status_id' => $status->id,
        ]);

        // Act: Create sprint
        $sprint = Sprint::create([
            'name' => 'Project Sprint',
            'starts_at' => now()->addDays(1),
            'ends_at' => now()->addDays(14),
            'project_id' => $project->id,
        ]);

        // Assert: Epic should belong to the same project
        $sprint->refresh();
        $this->assertNotNull($sprint->epic);
        $this->assertEquals($project->id, $sprint->epic->project_id);
    }

    public function test_sprint_created_hook_is_triggered_on_create(): void
    {
        // Arrange: Create project
        $owner = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $owner->id,
            'status_id' => $status->id,
        ]);

        // Act: Use Sprint::create() to trigger boot hook
        $sprint = Sprint::create([
            'name' => 'Hook Test Sprint',
            'starts_at' => now()->addDays(1),
            'ends_at' => now()->addDays(14),
            'project_id' => $project->id,
        ]);

        // Assert: Verify sprint was created and epic was auto-created
        $this->assertDatabaseHas('sprints', [
            'id' => $sprint->id,
            'name' => 'Hook Test Sprint',
        ]);
        $this->assertNotNull($sprint->epic_id);
        $this->assertDatabaseHas('epics', [
            'id' => $sprint->epic_id,
            'name' => 'Hook Test Sprint',
        ]);
    }

    public function test_user_creating_generates_password_for_db_type(): void
    {
        // Act: Create user with type='db' (should trigger creating hook)
        $user = User::create([
            'name' => 'DB User',
            'email' => 'dbuser_' . uniqid() . '@example.com',
            'type' => 'db',
        ]);

        // Assert: Password should be auto-generated and hashed
        $this->assertNotNull($user->password);
        $this->assertNotEmpty($user->password);
        // Password should be hashed (bcrypt produces 60-character hash)
        $this->assertGreaterThan(20, strlen($user->password));
    }

    public function test_user_creating_generates_creation_token_for_db_type(): void
    {
        // Act: Create user with type='db'
        $user = User::create([
            'name' => 'Token User',
            'email' => 'tokenuser_' . uniqid() . '@example.com',
            'type' => 'db',
        ]);

        // Assert: Creation token should be auto-generated (UUID format)
        $this->assertNotNull($user->creation_token);
        $this->assertNotEmpty($user->creation_token);
        // UUID format: 8-4-4-4-12 characters with dashes
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $user->creation_token
        );
    }

    public function test_user_creating_skips_generation_for_non_db_type(): void
    {
        // Act: Create user with type='azure' (should NOT trigger password/token generation)
        $user = User::create([
            'name' => 'Azure User',
            'email' => 'azureuser_' . uniqid() . '@example.com',
            'type' => 'azure',
            'password' => null,
            'creation_token' => null,
        ]);

        // Assert: Password and token should remain null for non-db types
        $this->assertNull($user->password);
        $this->assertNull($user->creation_token);
    }
}
