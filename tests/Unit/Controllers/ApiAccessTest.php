<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Api\ApiAccess;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Test controller class that uses ApiAccess trait for testing
 */
class ApiAccessTestController
{
    use ApiAccess;

    public function testAuthorizeProjectAccess(Request $request, Project $project): void
    {
        $this->authorizeProjectAccess($request, $project);
    }

    public function testAuthorizeTicketAccess(Request $request, Ticket $ticket): void
    {
        $this->authorizeTicketAccess($request, $ticket);
    }
}

class ApiAccessTest extends TestCase
{
    use RefreshDatabase;

    private ApiAccessTestController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new ApiAccessTestController();
    }

    public function test_authorize_project_access_allows_project_owner(): void
    {
        // Arrange: Create project owner
        $owner = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $owner->id,
            'status_id' => $status->id,
        ]);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $owner);

        // Act & Assert: Owner should have access (no exception thrown)
        $this->controller->testAuthorizeProjectAccess($request, $project);
        $this->assertTrue(true); // If we reach here, authorization passed
    }

    public function test_authorize_project_access_allows_project_member(): void
    {
        // Arrange: Create project with owner and member
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $owner->id,
            'status_id' => $status->id,
        ]);

        // Add member to project
        $project->users()->attach($member->id, ['role' => 'developer']);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $member);

        // Act & Assert: Member should have access
        $this->controller->testAuthorizeProjectAccess($request, $project);
        $this->assertTrue(true);
    }

    public function test_authorize_project_access_denies_non_member(): void
    {
        // Arrange: Create project and unrelated user
        $owner = User::factory()->create();
        $nonMember = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $owner->id,
            'status_id' => $status->id,
        ]);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $nonMember);

        // Assert: Expect 403 exception
        $this->expectException(HttpException::class);

        // Act: Non-member should be denied
        $this->controller->testAuthorizeProjectAccess($request, $project);
    }

    public function test_authorize_project_access_allows_multiple_members(): void
    {
        // Arrange: Create project with multiple members
        $owner = User::factory()->create();
        $member1 = User::factory()->create();
        $member2 = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $owner->id,
            'status_id' => $status->id,
        ]);

        $project->users()->attach($member1->id, ['role' => 'developer']);
        $project->users()->attach($member2->id, ['role' => 'tester']);

        // Act & Assert: Both members should have access
        $request1 = Request::create('/test', 'GET');
        $request1->setUserResolver(fn() => $member1);
        $this->controller->testAuthorizeProjectAccess($request1, $project);

        $request2 = Request::create('/test', 'GET');
        $request2->setUserResolver(fn() => $member2);
        $this->controller->testAuthorizeProjectAccess($request2, $project);

        $this->assertTrue(true);
    }

    public function test_authorize_project_access_denies_after_member_removed(): void
    {
        // Arrange: Create project with member, then remove them
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $owner->id,
            'status_id' => $status->id,
        ]);

        $project->users()->attach($member->id, ['role' => 'developer']);
        $project->users()->detach($member->id); // Remove member

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $member);

        // Assert: Expect 403 exception after removal
        $this->expectException(HttpException::class);

        // Act: Removed member should be denied
        $this->controller->testAuthorizeProjectAccess($request, $project);
    }

    public function test_authorize_ticket_access_allows_ticket_owner(): void
    {
        // Arrange: Create ticket with owner
        $owner = User::factory()->create();
        $responsible = User::factory()->create();
        $projectOwner = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $projectOwner->id,
            'status_id' => $status->id,
        ]);

        $ticketStatus = TicketStatus::factory()->create(['name' => 'Open_' . uniqid()]);
        $ticketType = TicketType::factory()->create(['name' => 'Bug_' . uniqid()]);
        $ticketPriority = TicketPriority::factory()->create(['name' => 'High_' . uniqid()]);

        $ticket = Ticket::factory()->create([
            'project_id' => $project->id,
            'owner_id' => $owner->id,
            'responsible_id' => $responsible->id,
            'status_id' => $ticketStatus->id,
            'type_id' => $ticketType->id,
            'priority_id' => $ticketPriority->id,
        ]);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $owner);

        // Act & Assert: Ticket owner should have access
        $this->controller->testAuthorizeTicketAccess($request, $ticket);
        $this->assertTrue(true);
    }

    public function test_authorize_ticket_access_allows_ticket_responsible(): void
    {
        // Arrange: Create ticket with responsible user
        $owner = User::factory()->create();
        $responsible = User::factory()->create();
        $projectOwner = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $projectOwner->id,
            'status_id' => $status->id,
        ]);

        $ticketStatus = TicketStatus::factory()->create(['name' => 'Open_' . uniqid()]);
        $ticketType = TicketType::factory()->create(['name' => 'Bug_' . uniqid()]);
        $ticketPriority = TicketPriority::factory()->create(['name' => 'High_' . uniqid()]);

        $ticket = Ticket::factory()->create([
            'project_id' => $project->id,
            'owner_id' => $owner->id,
            'responsible_id' => $responsible->id,
            'status_id' => $ticketStatus->id,
            'type_id' => $ticketType->id,
            'priority_id' => $ticketPriority->id,
        ]);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $responsible);

        // Act & Assert: Responsible user should have access
        $this->controller->testAuthorizeTicketAccess($request, $ticket);
        $this->assertTrue(true);
    }

    public function test_authorize_ticket_access_allows_project_owner(): void
    {
        // Arrange: Create ticket where user is project owner but not ticket owner/responsible
        $ticketOwner = User::factory()->create();
        $responsible = User::factory()->create();
        $projectOwner = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $projectOwner->id,
            'status_id' => $status->id,
        ]);

        $ticketStatus = TicketStatus::factory()->create(['name' => 'Open_' . uniqid()]);
        $ticketType = TicketType::factory()->create(['name' => 'Bug_' . uniqid()]);
        $ticketPriority = TicketPriority::factory()->create(['name' => 'High_' . uniqid()]);

        $ticket = Ticket::factory()->create([
            'project_id' => $project->id,
            'owner_id' => $ticketOwner->id,
            'responsible_id' => $responsible->id,
            'status_id' => $ticketStatus->id,
            'type_id' => $ticketType->id,
            'priority_id' => $ticketPriority->id,
        ]);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $projectOwner);

        // Act & Assert: Project owner should have access to all project tickets
        $this->controller->testAuthorizeTicketAccess($request, $ticket);
        $this->assertTrue(true);
    }

    public function test_authorize_ticket_access_allows_project_member(): void
    {
        // Arrange: Create ticket where user is project member but not ticket owner/responsible
        $ticketOwner = User::factory()->create();
        $responsible = User::factory()->create();
        $projectOwner = User::factory()->create();
        $projectMember = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $projectOwner->id,
            'status_id' => $status->id,
        ]);

        // Add member to project
        $project->users()->attach($projectMember->id, ['role' => 'developer']);

        $ticketStatus = TicketStatus::factory()->create(['name' => 'Open_' . uniqid()]);
        $ticketType = TicketType::factory()->create(['name' => 'Bug_' . uniqid()]);
        $ticketPriority = TicketPriority::factory()->create(['name' => 'High_' . uniqid()]);

        $ticket = Ticket::factory()->create([
            'project_id' => $project->id,
            'owner_id' => $ticketOwner->id,
            'responsible_id' => $responsible->id,
            'status_id' => $ticketStatus->id,
            'type_id' => $ticketType->id,
            'priority_id' => $ticketPriority->id,
        ]);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $projectMember);

        // Act & Assert: Project member should have access to project tickets
        $this->controller->testAuthorizeTicketAccess($request, $ticket);
        $this->assertTrue(true);
    }

    public function test_authorize_ticket_access_denies_unrelated_user(): void
    {
        // Arrange: Create ticket and completely unrelated user
        $ticketOwner = User::factory()->create();
        $responsible = User::factory()->create();
        $projectOwner = User::factory()->create();
        $unrelatedUser = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $projectOwner->id,
            'status_id' => $status->id,
        ]);

        $ticketStatus = TicketStatus::factory()->create(['name' => 'Open_' . uniqid()]);
        $ticketType = TicketType::factory()->create(['name' => 'Bug_' . uniqid()]);
        $ticketPriority = TicketPriority::factory()->create(['name' => 'High_' . uniqid()]);

        $ticket = Ticket::factory()->create([
            'project_id' => $project->id,
            'owner_id' => $ticketOwner->id,
            'responsible_id' => $responsible->id,
            'status_id' => $ticketStatus->id,
            'type_id' => $ticketType->id,
            'priority_id' => $ticketPriority->id,
        ]);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $unrelatedUser);

        // Assert: Expect 403 exception
        $this->expectException(HttpException::class);

        // Act: Unrelated user should be denied
        $this->controller->testAuthorizeTicketAccess($request, $ticket);
    }

    public function test_authorize_ticket_access_allows_when_user_is_both_owner_and_responsible(): void
    {
        // Arrange: Create ticket where same user is both owner and responsible
        $user = User::factory()->create();
        $projectOwner = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $projectOwner->id,
            'status_id' => $status->id,
        ]);

        $ticketStatus = TicketStatus::factory()->create(['name' => 'Open_' . uniqid()]);
        $ticketType = TicketType::factory()->create(['name' => 'Bug_' . uniqid()]);
        $ticketPriority = TicketPriority::factory()->create(['name' => 'High_' . uniqid()]);

        $ticket = Ticket::factory()->create([
            'project_id' => $project->id,
            'owner_id' => $user->id,
            'responsible_id' => $user->id, // Same user
            'status_id' => $ticketStatus->id,
            'type_id' => $ticketType->id,
            'priority_id' => $ticketPriority->id,
        ]);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $user);

        // Act & Assert: User should have access when they're both owner and responsible
        $this->controller->testAuthorizeTicketAccess($request, $ticket);
        $this->assertTrue(true);
    }

    public function test_authorize_ticket_access_denies_after_removed_from_project(): void
    {
        // Arrange: Create ticket, add user as project member, then remove them
        $ticketOwner = User::factory()->create();
        $responsible = User::factory()->create();
        $projectOwner = User::factory()->create();
        $formerMember = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $projectOwner->id,
            'status_id' => $status->id,
        ]);

        // Add then remove member
        $project->users()->attach($formerMember->id, ['role' => 'developer']);
        $project->users()->detach($formerMember->id);

        $ticketStatus = TicketStatus::factory()->create(['name' => 'Open_' . uniqid()]);
        $ticketType = TicketType::factory()->create(['name' => 'Bug_' . uniqid()]);
        $ticketPriority = TicketPriority::factory()->create(['name' => 'High_' . uniqid()]);

        $ticket = Ticket::factory()->create([
            'project_id' => $project->id,
            'owner_id' => $ticketOwner->id,
            'responsible_id' => $responsible->id,
            'status_id' => $ticketStatus->id,
            'type_id' => $ticketType->id,
            'priority_id' => $ticketPriority->id,
        ]);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $formerMember);

        // Assert: Expect 403 exception after removal
        $this->expectException(HttpException::class);

        // Act: Former member should be denied
        $this->controller->testAuthorizeTicketAccess($request, $ticket);
    }
}
