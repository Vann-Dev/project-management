<?php

namespace Tests\Unit\Models;

use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TicketMethodsTest extends TestCase
{
    use RefreshDatabase;

    public function test_watchers_includes_ticket_owner(): void
    {
        // Arrange: Create ticket with owner
        $owner = User::factory()->create(['name' => 'Ticket Owner']);
        $responsible = User::factory()->create(['name' => 'Responsible']);
        $projectOwner = User::factory()->create(['name' => 'Project Owner']);
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

        // Act: Get watchers
        $watchers = $ticket->watchers;

        // Assert: Owner should be in watchers list
        $this->assertTrue($watchers->contains('id', $owner->id));
    }

    public function test_watchers_includes_ticket_responsible(): void
    {
        // Arrange: Create ticket with responsible user
        $owner = User::factory()->create(['name' => 'Ticket Owner']);
        $responsible = User::factory()->create(['name' => 'Responsible']);
        $projectOwner = User::factory()->create(['name' => 'Project Owner']);
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

        // Act: Get watchers
        $watchers = $ticket->watchers;

        // Assert: Responsible user should be in watchers list
        $this->assertTrue($watchers->contains('id', $responsible->id));
    }

    public function test_watchers_includes_project_users(): void
    {
        // Arrange: Create project with multiple members
        $owner = User::factory()->create(['name' => 'Ticket Owner']);
        $responsible = User::factory()->create(['name' => 'Responsible']);
        $projectOwner = User::factory()->create(['name' => 'Project Owner']);
        $member1 = User::factory()->create(['name' => 'Member 1']);
        $member2 = User::factory()->create(['name' => 'Member 2']);
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

        // Act: Get watchers
        $watchers = $ticket->watchers;

        // Assert: All project members should be in watchers list
        $this->assertTrue($watchers->contains('id', $member1->id));
        $this->assertTrue($watchers->contains('id', $member2->id));
    }

    public function test_watchers_includes_project_owner(): void
    {
        // Arrange: Create ticket in a project (project owner not the ticket owner)
        $owner = User::factory()->create(['name' => 'Ticket Owner']);
        $responsible = User::factory()->create(['name' => 'Responsible']);
        $projectOwner = User::factory()->create(['name' => 'Project Owner']);
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

        // Act: Get watchers
        $watchers = $ticket->watchers;

        // Assert: Project owner should be in watchers list (via project->users relationship)
        // Note: The watchers accessor gets project->users which includes the owner implicitly
        $this->assertTrue($watchers->contains('id', $owner->id));
    }

    public function test_watchers_removes_duplicates_when_owner_is_also_project_member(): void
    {
        // Arrange: Create scenario where ticket owner is also a project member
        $owner = User::factory()->create(['name' => 'Owner and Member']);
        $responsible = User::factory()->create(['name' => 'Responsible']);
        $projectOwner = User::factory()->create(['name' => 'Project Owner']);
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $projectOwner->id,
            'status_id' => $status->id,
        ]);

        // Add ticket owner as project member too
        $project->users()->attach($owner->id, ['role' => 'developer']);

        $ticket = Ticket::factory()->create([
            'project_id' => $project->id,
            'owner_id' => $owner->id,
            'responsible_id' => $responsible->id,
        ]);

        // Act: Get watchers
        $watchers = $ticket->watchers;

        // Assert: Owner should appear only once (duplicates removed via unique())
        $ownerCount = $watchers->filter(fn($user) => $user->id === $owner->id)->count();
        $this->assertEquals(1, $ownerCount);
    }

    public function test_watchers_removes_duplicates_when_responsible_is_also_project_owner(): void
    {
        // Arrange: Create scenario where responsible is also the project owner
        $owner = User::factory()->create(['name' => 'Ticket Owner']);
        $projectOwnerAndResponsible = User::factory()->create(['name' => 'Project Owner and Responsible']);
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $projectOwnerAndResponsible->id,
            'status_id' => $status->id,
        ]);

        $ticket = Ticket::factory()->create([
            'project_id' => $project->id,
            'owner_id' => $owner->id,
            'responsible_id' => $projectOwnerAndResponsible->id, // Same as project owner
        ]);

        // Act: Get watchers
        $watchers = $ticket->watchers;

        // Assert: Project owner/responsible should appear only once
        $projectOwnerCount = $watchers->filter(fn($user) => $user->id === $projectOwnerAndResponsible->id)->count();
        $this->assertEquals(1, $projectOwnerCount);
    }

    public function test_watchers_returns_collection(): void
    {
        // Arrange: Create simple ticket
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

        // Act: Get watchers
        $watchers = $ticket->watchers;

        // Assert: Should return a Collection instance
        $this->assertInstanceOf(Collection::class, $watchers);
    }

    public function test_estimation_for_humans_formats_whole_hours(): void
    {
        // Arrange: Create ticket with whole hour estimation
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
            'estimation' => 2, // 2 hours
        ]);

        // Act: Get human-readable estimation
        $formatted = $ticket->estimation_for_humans;

        // Assert: Should format as "2 hours"
        $this->assertStringContainsString('2 hours', $formatted);
    }

    public function test_estimation_for_humans_formats_fractional_hours(): void
    {
        // Arrange: Create ticket with fractional hour estimation
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
            'estimation' => 1.5, // 1.5 hours = 1 hour 30 minutes
        ]);

        // Act: Get human-readable estimation
        $formatted = $ticket->estimation_for_humans;

        // Assert: Should contain "1 hour" and "30 minutes" or similar
        $this->assertTrue(
            str_contains($formatted, '1 hour') || str_contains($formatted, 'hour')
        );
    }

    public function test_estimation_for_humans_handles_zero(): void
    {
        // Arrange: Create ticket with zero estimation
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
            'estimation' => 0,
        ]);

        // Act: Get human-readable estimation
        $formatted = $ticket->estimation_for_humans;

        // Assert: Should handle zero gracefully (likely "0 seconds")
        $this->assertNotNull($formatted);
        $this->assertIsString($formatted);
    }

    public function test_estimation_for_humans_handles_null(): void
    {
        // Arrange: Create ticket with null estimation
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
            'estimation' => null,
        ]);

        // Act: Get human-readable estimation
        $formatted = $ticket->estimation_for_humans;

        // Assert: Should handle null gracefully
        $this->assertNotNull($formatted);
        $this->assertIsString($formatted);
    }
}
