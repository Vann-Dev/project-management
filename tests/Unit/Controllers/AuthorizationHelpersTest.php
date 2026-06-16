<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Api\EpicController;
use App\Http\Controllers\Api\SprintController;
use App\Http\Controllers\Api\TicketCommentController;
use App\Http\Controllers\Api\TicketHourController;
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
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AuthorizationHelpersTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorize_sprint_access_allows_owner_of_sprint_project(): void
    {
        // Arrange: Create sprint with project owner
        $owner = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $owner->id,
            'status_id' => $status->id,
        ]);

        $sprint = Sprint::create([
            'name' => 'Sprint 1',
            'starts_at' => now()->addDays(1),
            'ends_at' => now()->addDays(14),
            'project_id' => $project->id,
        ]);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $owner);

        $controller = new SprintController();
        $method = new \ReflectionMethod(SprintController::class, 'authorizeSprintAccess');
        $method->setAccessible(true);

        // Act & Assert: Owner should have access (no exception thrown)
        $method->invoke($controller, $request, $project, $sprint);
        $this->assertTrue(true);
    }

    public function test_authorize_sprint_access_allows_member_of_sprint_project(): void
    {
        // Arrange: Create sprint with project member
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $owner->id,
            'status_id' => $status->id,
        ]);

        $project->users()->attach($member->id, ['role' => 'developer']);

        $sprint = Sprint::create([
            'name' => 'Sprint 1',
            'starts_at' => now()->addDays(1),
            'ends_at' => now()->addDays(14),
            'project_id' => $project->id,
        ]);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $member);

        $controller = new SprintController();
        $method = new \ReflectionMethod(SprintController::class, 'authorizeSprintAccess');
        $method->setAccessible(true);

        // Act & Assert: Member should have access
        $method->invoke($controller, $request, $project, $sprint);
        $this->assertTrue(true);
    }

    public function test_authorize_sprint_access_denies_sprint_from_different_project(): void
    {
        // Arrange: Create sprint in one project, try to access from different project
        $owner = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project1 = Project::factory()->create([
            'owner_id' => $owner->id,
            'status_id' => $status->id,
        ]);
        $project2 = Project::factory()->create([
            'owner_id' => $owner->id,
            'status_id' => $status->id,
        ]);

        $sprint = Sprint::create([
            'name' => 'Sprint in Project 1',
            'starts_at' => now()->addDays(1),
            'ends_at' => now()->addDays(14),
            'project_id' => $project1->id,
        ]);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $owner);

        $controller = new SprintController();
        $method = new \ReflectionMethod(SprintController::class, 'authorizeSprintAccess');
        $method->setAccessible(true);

        // Assert: Expect 404 exception when sprint doesn't belong to project
        $this->expectException(HttpException::class);

        // Act: Try to access sprint from wrong project
        $method->invoke($controller, $request, $project2, $sprint);
    }

    public function test_authorize_sprint_access_denies_non_member(): void
    {
        // Arrange: Create sprint and unrelated user
        $owner = User::factory()->create();
        $nonMember = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $owner->id,
            'status_id' => $status->id,
        ]);

        $sprint = Sprint::create([
            'name' => 'Sprint 1',
            'starts_at' => now()->addDays(1),
            'ends_at' => now()->addDays(14),
            'project_id' => $project->id,
        ]);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $nonMember);

        $controller = new SprintController();
        $method = new \ReflectionMethod(SprintController::class, 'authorizeSprintAccess');
        $method->setAccessible(true);

        // Assert: Expect 403 exception for non-member
        $this->expectException(HttpException::class);

        // Act: Non-member should be denied
        $method->invoke($controller, $request, $project, $sprint);
    }

    public function test_authorize_epic_access_allows_owner_of_epic_project(): void
    {
        // Arrange: Create epic with project owner
        $owner = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $owner->id,
            'status_id' => $status->id,
        ]);

        $epic = Epic::create([
            'name' => 'Epic 1',
            'starts_at' => now()->addDays(1),
            'ends_at' => now()->addDays(30),
            'project_id' => $project->id,
        ]);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $owner);

        $controller = new EpicController();
        $method = new \ReflectionMethod(EpicController::class, 'authorizeEpicAccess');
        $method->setAccessible(true);

        // Act & Assert: Owner should have access
        $method->invoke($controller, $request, $project, $epic);
        $this->assertTrue(true);
    }

    public function test_authorize_epic_access_allows_member_of_epic_project(): void
    {
        // Arrange: Create epic with project member
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $owner->id,
            'status_id' => $status->id,
        ]);

        $project->users()->attach($member->id, ['role' => 'developer']);

        $epic = Epic::create([
            'name' => 'Epic 1',
            'starts_at' => now()->addDays(1),
            'ends_at' => now()->addDays(30),
            'project_id' => $project->id,
        ]);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $member);

        $controller = new EpicController();
        $method = new \ReflectionMethod(EpicController::class, 'authorizeEpicAccess');
        $method->setAccessible(true);

        // Act & Assert: Member should have access
        $method->invoke($controller, $request, $project, $epic);
        $this->assertTrue(true);
    }

    public function test_authorize_epic_access_denies_epic_from_different_project(): void
    {
        // Arrange: Create epic in one project, try to access from different project
        $owner = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project1 = Project::factory()->create([
            'owner_id' => $owner->id,
            'status_id' => $status->id,
        ]);
        $project2 = Project::factory()->create([
            'owner_id' => $owner->id,
            'status_id' => $status->id,
        ]);

        $epic = Epic::create([
            'name' => 'Epic in Project 1',
            'starts_at' => now()->addDays(1),
            'ends_at' => now()->addDays(30),
            'project_id' => $project1->id,
        ]);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $owner);

        $controller = new EpicController();
        $method = new \ReflectionMethod(EpicController::class, 'authorizeEpicAccess');
        $method->setAccessible(true);

        // Assert: Expect 404 exception
        $this->expectException(HttpException::class);

        // Act: Try to access epic from wrong project
        $method->invoke($controller, $request, $project2, $epic);
    }

    public function test_authorize_epic_access_denies_non_member(): void
    {
        // Arrange: Create epic and unrelated user
        $owner = User::factory()->create();
        $nonMember = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $owner->id,
            'status_id' => $status->id,
        ]);

        $epic = Epic::create([
            'name' => 'Epic 1',
            'starts_at' => now()->addDays(1),
            'ends_at' => now()->addDays(30),
            'project_id' => $project->id,
        ]);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $nonMember);

        $controller = new EpicController();
        $method = new \ReflectionMethod(EpicController::class, 'authorizeEpicAccess');
        $method->setAccessible(true);

        // Assert: Expect 403 exception
        $this->expectException(HttpException::class);

        // Act: Non-member should be denied
        $method->invoke($controller, $request, $project, $epic);
    }

    public function test_authorize_comment_access_allows_when_user_has_ticket_access(): void
    {
        // Arrange: Create comment where user is ticket owner
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

        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
            'content' => 'Test comment',
        ]);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $owner);

        $controller = new TicketCommentController();
        $method = new \ReflectionMethod(TicketCommentController::class, 'authorizeCommentAccess');
        $method->setAccessible(true);

        // Act & Assert: User with ticket access should have comment access
        $method->invoke($controller, $request, $ticket, $comment);
        $this->assertTrue(true);
    }

    public function test_authorize_comment_access_allows_comment_author(): void
    {
        // Arrange: Create comment with author having ticket access
        $author = User::factory()->create();
        $projectOwner = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $projectOwner->id,
            'status_id' => $status->id,
        ]);

        $ticket = Ticket::factory()->create([
            'project_id' => $project->id,
            'owner_id' => $author->id,
            'responsible_id' => $author->id,
        ]);

        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $author->id,
            'content' => 'Test comment by author',
        ]);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $author);

        $controller = new TicketCommentController();
        $method = new \ReflectionMethod(TicketCommentController::class, 'authorizeCommentAccess');
        $method->setAccessible(true);

        // Act & Assert: Comment author should have access
        $method->invoke($controller, $request, $ticket, $comment);
        $this->assertTrue(true);
    }

    public function test_authorize_comment_access_denies_comment_from_different_ticket(): void
    {
        // Arrange: Create comment on one ticket, try to access from different ticket
        $owner = User::factory()->create();
        $projectOwner = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $projectOwner->id,
            'status_id' => $status->id,
        ]);

        $ticket1 = Ticket::factory()->create([
            'project_id' => $project->id,
            'owner_id' => $owner->id,
            'responsible_id' => $owner->id,
        ]);

        $ticket2 = Ticket::factory()->create([
            'project_id' => $project->id,
            'owner_id' => $owner->id,
            'responsible_id' => $owner->id,
        ]);

        $comment = TicketComment::create([
            'ticket_id' => $ticket1->id,
            'user_id' => $owner->id,
            'content' => 'Comment on ticket 1',
        ]);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $owner);

        $controller = new TicketCommentController();
        $method = new \ReflectionMethod(TicketCommentController::class, 'authorizeCommentAccess');
        $method->setAccessible(true);

        // Assert: Expect 404 exception
        $this->expectException(HttpException::class);

        // Act: Try to access comment from wrong ticket
        $method->invoke($controller, $request, $ticket2, $comment);
    }

    public function test_authorize_comment_access_denies_user_without_ticket_access(): void
    {
        // Arrange: Create comment and user without ticket access
        $ticketOwner = User::factory()->create();
        $projectOwner = User::factory()->create();
        $unrelatedUser = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $projectOwner->id,
            'status_id' => $status->id,
        ]);

        $ticket = Ticket::factory()->create([
            'project_id' => $project->id,
            'owner_id' => $ticketOwner->id,
            'responsible_id' => $ticketOwner->id,
        ]);

        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $ticketOwner->id,
            'content' => 'Test comment',
        ]);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $unrelatedUser);

        $controller = new TicketCommentController();
        $method = new \ReflectionMethod(TicketCommentController::class, 'authorizeCommentAccess');
        $method->setAccessible(true);

        // Assert: Expect 403 exception
        $this->expectException(HttpException::class);

        // Act: User without ticket access should be denied
        $method->invoke($controller, $request, $ticket, $comment);
    }

    public function test_authorize_hour_access_allows_when_user_has_ticket_access(): void
    {
        // Arrange: Create hour log where user is ticket owner
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

        $hour = TicketHour::create([
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
            'value' => 2.5,
        ]);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $owner);

        $controller = new TicketHourController();
        $method = new \ReflectionMethod(TicketHourController::class, 'authorizeHourAccess');
        $method->setAccessible(true);

        // Act & Assert: User with ticket access should have hour access
        $method->invoke($controller, $request, $ticket, $hour);
        $this->assertTrue(true);
    }

    public function test_authorize_hour_access_denies_hour_from_different_ticket(): void
    {
        // Arrange: Create hour on one ticket, try to access from different ticket
        $owner = User::factory()->create();
        $projectOwner = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $projectOwner->id,
            'status_id' => $status->id,
        ]);

        $ticket1 = Ticket::factory()->create([
            'project_id' => $project->id,
            'owner_id' => $owner->id,
            'responsible_id' => $owner->id,
        ]);

        $ticket2 = Ticket::factory()->create([
            'project_id' => $project->id,
            'owner_id' => $owner->id,
            'responsible_id' => $owner->id,
        ]);

        $hour = TicketHour::create([
            'ticket_id' => $ticket1->id,
            'user_id' => $owner->id,
            'value' => 3.0,
        ]);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $owner);

        $controller = new TicketHourController();
        $method = new \ReflectionMethod(TicketHourController::class, 'authorizeHourAccess');
        $method->setAccessible(true);

        // Assert: Expect 404 exception
        $this->expectException(HttpException::class);

        // Act: Try to access hour from wrong ticket
        $method->invoke($controller, $request, $ticket2, $hour);
    }

    public function test_authorize_hour_access_denies_user_without_ticket_access(): void
    {
        // Arrange: Create hour and user without ticket access
        $ticketOwner = User::factory()->create();
        $projectOwner = User::factory()->create();
        $unrelatedUser = User::factory()->create();
        $status = ProjectStatus::factory()->create(['name' => 'Active_' . uniqid()]);
        $project = Project::factory()->create([
            'owner_id' => $projectOwner->id,
            'status_id' => $status->id,
        ]);

        $ticket = Ticket::factory()->create([
            'project_id' => $project->id,
            'owner_id' => $ticketOwner->id,
            'responsible_id' => $ticketOwner->id,
        ]);

        $hour = TicketHour::create([
            'ticket_id' => $ticket->id,
            'user_id' => $ticketOwner->id,
            'value' => 4.0,
        ]);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $unrelatedUser);

        $controller = new TicketHourController();
        $method = new \ReflectionMethod(TicketHourController::class, 'authorizeHourAccess');
        $method->setAccessible(true);

        // Assert: Expect 403 exception
        $this->expectException(HttpException::class);

        // Act: User without ticket access should be denied
        $method->invoke($controller, $request, $ticket, $hour);
    }
}
