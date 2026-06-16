<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdditionalTableCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_access_tokens_crud_operations(): void
    {
        $user = User::factory()->create();

        // Create token for user
        $token = $user->createToken('test-token', ['read', 'write']);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'test-token',
        ]);

        // Update last_used_at timestamp
        $personalAccessToken = PersonalAccessToken::where('tokenable_id', $user->id)->first();
        $timestamp = now();
        $personalAccessToken->forceFill(['last_used_at' => $timestamp])->save();

        // Verify the token still exists and timestamp was updated
        $updatedToken = PersonalAccessToken::find($personalAccessToken->id);
        $this->assertNotNull($updatedToken);
        $this->assertNotNull($updatedToken->last_used_at);

        // Delete token and verify removal
        $personalAccessToken->delete();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $personalAccessToken->id,
        ]);
    }

    public function test_password_reset_workflow(): void
    {
        $email = 'test@example.com';
        $token = Str::random(64);

        // Create password reset token
        DB::table('password_resets')->insert([
            'email' => $email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('password_resets', [
            'email' => $email,
        ]);

        // Delete token after use
        DB::table('password_resets')->where('email', $email)->delete();

        $this->assertDatabaseMissing('password_resets', [
            'email' => $email,
        ]);
    }

    public function test_pending_user_email_verification(): void
    {
        $user = User::factory()->create();
        $newEmail = 'newemail@example.com';
        $token = Str::random(32);

        // Create pending email record
        DB::table('pending_user_emails')->insert([
            'user_type' => User::class,
            'user_id' => $user->id,
            'email' => $newEmail,
            'token' => $token,
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('pending_user_emails', [
            'user_type' => User::class,
            'user_id' => $user->id,
            'email' => $newEmail,
        ]);

        // Verify token generation
        $pending = DB::table('pending_user_emails')->where('user_id', $user->id)->first();
        $this->assertEquals($token, $pending->token);

        // Delete after verification
        DB::table('pending_user_emails')->where('user_id', $user->id)->delete();

        $this->assertDatabaseMissing('pending_user_emails', [
            'user_id' => $user->id,
        ]);
    }

    public function test_socialite_user_oauth_integration(): void
    {
        $user = User::factory()->create();

        // Create socialite user for GitHub provider
        $githubId = DB::table('socialite_users')->insertGetId([
            'user_id' => $user->id,
            'provider' => 'github',
            'provider_id' => 'github_12345',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('socialite_users', [
            'user_id' => $user->id,
            'provider' => 'github',
            'provider_id' => 'github_12345',
        ]);

        // Create second provider (Google) for same user
        $googleId = DB::table('socialite_users')->insertGetId([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'google_67890',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('socialite_users', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'google_67890',
        ]);

        // Verify multi-provider support
        $socialiteCount = DB::table('socialite_users')->where('user_id', $user->id)->count();
        $this->assertEquals(2, $socialiteCount);
    }

    public function test_roles_and_permissions_crud(): void
    {
        // Create role with guard_name
        $role = Role::create([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        // Create permission with guard_name
        $permission = Permission::create([
            'name' => 'edit-posts',
            'guard_name' => 'web',
        ]);

        $this->assertDatabaseHas('permissions', [
            'name' => 'edit-posts',
            'guard_name' => 'web',
        ]);

        // Verify timestamps
        $this->assertNotNull($role->created_at);
        $this->assertNotNull($permission->created_at);
    }

    public function test_user_role_assignment_polymorphic(): void
    {
        $user = User::factory()->create();
        $role = $this->createRole('editor');

        // Assign role to user
        $user->assignRole($role);

        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => $role->id,
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);

        // Assign multiple roles
        $adminRole = $this->createRole('admin');
        $user->assignRole($adminRole);

        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => $adminRole->id,
            'model_id' => $user->id,
        ]);

        // Remove role assignment
        $user->removeRole($role);

        $this->assertDatabaseMissing('model_has_roles', [
            'role_id' => $role->id,
            'model_id' => $user->id,
        ]);
    }

    public function test_role_permission_mappings(): void
    {
        $role = $this->createRole('manager');
        $permission1 = $this->createPermission('create-posts');
        $permission2 = $this->createPermission('delete-posts');
        $permission3 = $this->createPermission('edit-posts');

        // Create role with multiple permissions
        $role->givePermissionTo([$permission1, $permission2, $permission3]);

        // Verify all mappings in role_has_permissions
        $this->assertDatabaseHas('role_has_permissions', [
            'permission_id' => $permission1->id,
            'role_id' => $role->id,
        ]);

        $this->assertDatabaseHas('role_has_permissions', [
            'permission_id' => $permission2->id,
            'role_id' => $role->id,
        ]);

        $this->assertDatabaseHas('role_has_permissions', [
            'permission_id' => $permission3->id,
            'role_id' => $role->id,
        ]);

        // Remove permission from role
        $role->revokePermissionTo($permission2);

        $this->assertDatabaseMissing('role_has_permissions', [
            'permission_id' => $permission2->id,
            'role_id' => $role->id,
        ]);

        // Verify role still exists after permission removal
        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
        ]);
    }

    public function test_direct_user_permissions(): void
    {
        $user = User::factory()->create();
        $permission = $this->createPermission('bypass-approval');

        // Assign permission directly to user
        $user->givePermissionTo($permission);

        $this->assertDatabaseHas('model_has_permissions', [
            'permission_id' => $permission->id,
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);

        // Remove direct permission
        $user->revokePermissionTo($permission);

        $this->assertDatabaseMissing('model_has_permissions', [
            'permission_id' => $permission->id,
            'model_id' => $user->id,
        ]);
    }

    public function test_time_sheets_and_cells_integration(): void
    {
        // Skip if time_sheets table doesn't exist in migrations
        if (!\Illuminate\Support\Facades\Schema::hasTable('time_sheets')) {
            $this->markTestSkipped('time_sheets table not available in migrations');
        }

        $user = User::factory()->create();
        $project = $this->createProject($user, 'TSH');

        // Create timesheet for user and project
        $timesheetId = DB::table('time_sheets')->insertGetId([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'task' => 'Development work',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('time_sheets', [
            'user_id' => $user->id,
            'project_id' => $project->id,
            'task' => 'Development work',
        ]);

        // Create multiple time_sheet_cells with dates
        $cell1Id = DB::table('time_sheet_cells')->insertGetId([
            'time_sheet_id' => $timesheetId,
            'value' => 8.0,
            'is_trip' => false,
            'comment' => 'Regular work day',
            'date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cell2Id = DB::table('time_sheet_cells')->insertGetId([
            'time_sheet_id' => $timesheetId,
            'value' => 4.5,
            'is_trip' => true,
            'comment' => 'Business trip',
            'date' => now()->addDay()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Verify value (hours) storage as double
        $this->assertDatabaseHas('time_sheet_cells', [
            'time_sheet_id' => $timesheetId,
            'value' => 8.0,
        ]);

        // Test is_trip boolean flag
        $this->assertDatabaseHas('time_sheet_cells', [
            'id' => $cell2Id,
            'is_trip' => true,
        ]);

        // Update cell comment
        DB::table('time_sheet_cells')->where('id', $cell1Id)->update([
            'comment' => 'Updated comment',
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('time_sheet_cells', [
            'id' => $cell1Id,
            'comment' => 'Updated comment',
        ]);

        // Test soft delete on timesheet
        DB::table('time_sheets')->where('id', $timesheetId)->update([
            'deleted_at' => now(),
        ]);

        $this->assertSoftDeleted('time_sheets', [
            'id' => $timesheetId,
        ]);
    }

    public function test_media_library_attachments(): void
    {
        $user = User::factory()->create();
        $ticket = $this->createTicket($user);

        // Create media record attached to ticket (polymorphic)
        $media = Media::create([
            'model_type' => Ticket::class,
            'model_id' => $ticket->id,
            'uuid' => Str::uuid(),
            'collection_name' => 'attachments',
            'name' => 'test-document',
            'file_name' => 'test-document.pdf',
            'mime_type' => 'application/pdf',
            'disk' => 'public',
            'size' => 1024000,
            'manipulations' => [],
            'custom_properties' => ['uploaded_by' => $user->id],
            'generated_conversions' => [],
            'responsive_images' => [],
            'order_column' => 1,
        ]);

        $this->assertDatabaseHas('media', [
            'model_type' => Ticket::class,
            'model_id' => $ticket->id,
            'collection_name' => 'attachments',
            'mime_type' => 'application/pdf',
        ]);

        // Test custom_properties JSON field
        $freshMedia = Media::find($media->id);
        $this->assertEquals($user->id, $freshMedia->custom_properties['uploaded_by']);

        // Test order_column for sorting
        $this->assertDatabaseHas('media', [
            'id' => $media->id,
            'order_column' => 1,
        ]);
    }

    public function test_notifications_storage_and_read_status(): void
    {
        $user = User::factory()->create();

        // Create notification for user (polymorphic notifiable)
        DB::table('notifications')->insert([
            'id' => Str::uuid(),
            'type' => 'App\Notifications\TicketAssigned',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['ticket_id' => 123, 'message' => 'You have been assigned']),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'type' => 'App\Notifications\TicketAssigned',
        ]);

        // Verify data JSON field
        $notification = DB::table('notifications')->where('notifiable_id', $user->id)->first();
        $data = json_decode($notification->data, true);
        $this->assertEquals(123, $data['ticket_id']);

        // Mark as read and verify read_at timestamp
        DB::table('notifications')->where('id', $notification->id)->update(['read_at' => now()]);

        $updatedNotification = DB::table('notifications')->where('id', $notification->id)->first();
        $this->assertNotNull($updatedNotification->read_at);
    }

    public function test_queue_jobs_and_failed_jobs(): void
    {
        // Insert job into jobs table
        $jobId = DB::table('jobs')->insertGetId([
            'queue' => 'default',
            'payload' => json_encode(['job' => 'ProcessTicket', 'data' => ['ticket_id' => 1]]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $this->assertDatabaseHas('jobs', [
            'id' => $jobId,
            'queue' => 'default',
        ]);

        // Verify attempts
        $job = DB::table('jobs')->where('id', $jobId)->first();
        $this->assertEquals(0, $job->attempts);

        // Simulate job failure - insert into failed_jobs
        $failedJobId = DB::table('failed_jobs')->insertGetId([
            'uuid' => Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['job' => 'ProcessTicket', 'data' => ['ticket_id' => 1]]),
            'exception' => 'Exception: Job processing failed',
            'failed_at' => now(),
        ]);

        $this->assertDatabaseHas('failed_jobs', [
            'id' => $failedJobId,
            'queue' => 'default',
        ]);

        // Verify exception storage
        $failedJob = DB::table('failed_jobs')->where('id', $failedJobId)->first();
        $this->assertStringContainsString('Job processing failed', $failedJob->exception);

        // Verify failed_at timestamp
        $this->assertNotNull($failedJob->failed_at);
    }

    // Helper methods

    private function createRole(string $name = 'admin'): Role
    {
        return Role::create([
            'name' => $name . '_' . uniqid(),
            'guard_name' => 'web',
        ]);
    }

    private function createPermission(string $name = 'edit-posts'): Permission
    {
        return Permission::create([
            'name' => $name . '_' . uniqid(),
            'guard_name' => 'web',
        ]);
    }

    private function createProject(User $owner, string $prefix): Project
    {
        return Project::create([
            'name' => 'Test Project ' . $prefix,
            'description' => 'Test project for additional table coverage',
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

    private function createTicket(User $owner): Ticket
    {
        $project = $this->createProject($owner, strtoupper(substr(uniqid(), -3)));

        return Ticket::create([
            'name' => 'Test Ticket ' . uniqid(),
            'content' => 'Test ticket for additional coverage',
            'owner_id' => $owner->id,
            'responsible_id' => $owner->id,
            'status_id' => $this->createTicketStatus($project)->id,
            'project_id' => $project->id,
            'type_id' => $this->createTicketType()->id,
            'priority_id' => $this->createTicketPriority()->id,
            'estimation' => 3.0,
        ]);
    }

    private function createTicketStatus(Project $project): TicketStatus
    {
        return TicketStatus::create([
            'name' => 'Todo ' . uniqid(),
            'color' => '#64748b',
            'is_default' => true,
            'order' => 1,
            'project_id' => $project->id,
        ]);
    }

    private function createTicketType(): TicketType
    {
        return TicketType::create([
            'name' => 'Task ' . uniqid(),
            'icon' => 'heroicon-o-clipboard',
            'color' => '#06b6d4',
            'is_default' => true,
        ]);
    }

    private function createTicketPriority(): TicketPriority
    {
        return TicketPriority::create([
            'name' => 'Normal ' . uniqid(),
            'color' => '#64748b',
            'is_default' => true,
        ]);
    }
}
