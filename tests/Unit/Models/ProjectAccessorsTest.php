<?php

namespace Tests\Unit\Models;

use App\Models\Epic;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Sprint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProjectAccessorsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a project with required relationships for testing
     */
    private function createProject(array $attributes = []): Project
    {
        $owner = User::factory()->create();

        $projectStatus = ProjectStatus::create([
            'name' => 'Active_' . uniqid(),
            'color' => '#000000',
            'is_default' => false
        ]);

        $project = Project::create(array_merge([
            'name' => 'Test Project_' . uniqid(),
            'owner_id' => $owner->id,
            'status_id' => $projectStatus->id,
            'ticket_prefix' => 'TST' . strtoupper(substr(uniqid(), -3))
        ], $attributes));

        return $project->fresh(['owner', 'users']);
    }

    public function test_contributors_merges_users_and_owner_uniquely(): void
    {
        $owner = User::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

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

        // Add 3 users to project
        $project->users()->attach($user1->id, ['role' => 'developer']);
        $project->users()->attach($user2->id, ['role' => 'developer']);
        $project->users()->attach($user3->id, ['role' => 'manager']);

        $project = $project->fresh(['owner', 'users']);

        // Should include: 3 project users + owner = 4 contributors
        $this->assertCount(4, $project->contributors);
        $this->assertTrue($project->contributors->contains($owner));
        $this->assertTrue($project->contributors->contains($user1));
        $this->assertTrue($project->contributors->contains($user2));
        $this->assertTrue($project->contributors->contains($user3));
    }

    public function test_contributors_ensures_uniqueness_when_owner_is_project_user(): void
    {
        $owner = User::factory()->create();
        $user1 = User::factory()->create();

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

        // Add owner as project user (should still only count once)
        $project->users()->attach($owner->id, ['role' => 'developer']);
        $project->users()->attach($user1->id, ['role' => 'developer']);

        $project = $project->fresh(['owner', 'users']);

        // Should be unique: owner appears in both but counted once = 2 total
        $this->assertCount(2, $project->contributors);
        $this->assertTrue($project->contributors->contains($owner));
        $this->assertTrue($project->contributors->contains($user1));
    }

    public function test_current_sprint_returns_active_sprint(): void
    {
        $project = $this->createProject();

        // Create a sprint that hasn't started yet
        $notStarted = Sprint::create([
            'name' => 'Sprint Not Started_' . uniqid(),
            'project_id' => $project->id,
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(15),
            'started_at' => null,
            'ended_at' => null
        ]);

        // Create an active sprint (started but not ended)
        $activeSprint = Sprint::create([
            'name' => 'Sprint Active_' . uniqid(),
            'project_id' => $project->id,
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(10),
            'started_at' => now()->subDays(5),
            'ended_at' => null
        ]);

        // Create a completed sprint
        $completed = Sprint::create([
            'name' => 'Sprint Completed_' . uniqid(),
            'project_id' => $project->id,
            'starts_at' => now()->subDays(20),
            'ends_at' => now()->subDays(10),
            'started_at' => now()->subDays(20),
            'ended_at' => now()->subDays(10)
        ]);

        $project = $project->fresh();

        // Should return only the active sprint
        $this->assertNotNull($project->current_sprint);
        $this->assertEquals($activeSprint->id, $project->current_sprint->id);
        $this->assertInstanceOf(Sprint::class, $project->current_sprint);
    }

    public function test_current_sprint_returns_null_when_no_active_sprint(): void
    {
        $project = $this->createProject();

        // Create only completed sprints
        Sprint::create([
            'name' => 'Sprint Completed_' . uniqid(),
            'project_id' => $project->id,
            'starts_at' => now()->subDays(20),
            'ends_at' => now()->subDays(10),
            'started_at' => now()->subDays(20),
            'ended_at' => now()->subDays(10)
        ]);

        $project = $project->fresh();

        $this->assertNull($project->current_sprint);
    }

    public function test_epics_first_date_returns_earliest_date(): void
    {
        $project = $this->createProject();

        $earliestDate = Carbon::parse('2026-01-01');
        $middleDate = Carbon::parse('2026-02-01');
        $latestDate = Carbon::parse('2026-03-01');

        // Create epics with different start dates
        Epic::create([
            'name' => 'Epic Middle_' . uniqid(),
            'project_id' => $project->id,
            'starts_at' => $middleDate,
            'ends_at' => Carbon::parse('2026-02-28')
        ]);

        Epic::create([
            'name' => 'Epic Earliest_' . uniqid(),
            'project_id' => $project->id,
            'starts_at' => $earliestDate,
            'ends_at' => Carbon::parse('2026-01-31')
        ]);

        Epic::create([
            'name' => 'Epic Latest_' . uniqid(),
            'project_id' => $project->id,
            'starts_at' => $latestDate,
            'ends_at' => Carbon::parse('2026-03-31')
        ]);

        $project = $project->fresh();

        $this->assertEquals($earliestDate->toDateString(), $project->epics_first_date->toDateString());
        $this->assertInstanceOf(Carbon::class, $project->epics_first_date);
    }

    public function test_epics_first_date_returns_now_when_no_epics(): void
    {
        $project = $this->createProject();

        // Project has no epics, should return now()
        $this->assertEquals(now()->toDateString(), $project->epics_first_date->toDateString());
        $this->assertInstanceOf(Carbon::class, $project->epics_first_date);
    }

    public function test_epics_last_date_returns_latest_date(): void
    {
        $project = $this->createProject();

        $earliestEndDate = Carbon::parse('2026-01-31');
        $middleEndDate = Carbon::parse('2026-02-28');
        $latestEndDate = Carbon::parse('2026-03-31');

        // Create epics with different end dates
        Epic::create([
            'name' => 'Epic Early End_' . uniqid(),
            'project_id' => $project->id,
            'starts_at' => Carbon::parse('2026-01-01'),
            'ends_at' => $earliestEndDate
        ]);

        Epic::create([
            'name' => 'Epic Middle End_' . uniqid(),
            'project_id' => $project->id,
            'starts_at' => Carbon::parse('2026-02-01'),
            'ends_at' => $middleEndDate
        ]);

        Epic::create([
            'name' => 'Epic Latest End_' . uniqid(),
            'project_id' => $project->id,
            'starts_at' => Carbon::parse('2026-03-01'),
            'ends_at' => $latestEndDate
        ]);

        $project = $project->fresh();

        $this->assertEquals($latestEndDate->toDateString(), $project->epics_last_date->toDateString());
        $this->assertInstanceOf(Carbon::class, $project->epics_last_date);
    }

    public function test_epics_last_date_returns_now_when_no_epics(): void
    {
        $project = $this->createProject();

        // Project has no epics, should return now()
        $this->assertEquals(now()->toDateString(), $project->epics_last_date->toDateString());
        $this->assertInstanceOf(Carbon::class, $project->epics_last_date);
    }
}
