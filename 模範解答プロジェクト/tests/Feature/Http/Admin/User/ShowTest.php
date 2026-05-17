<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Admin\User;

use App\Enums\UserStatus;
use App\Models\Invitation;
use App\Models\User;
use App\Models\UserStatusLog;
use App\Services\UserWithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_active_user_detail(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->student()->create([
            'name' => '受講生 A',
            'email' => 'student-a@example.test',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.show', $target));

        $response->assertOk();
        $response->assertViewIs('admin.users.show');
        $response->assertSee('受講生 A');
        $response->assertSee('student-a@example.test');
    }

    public function test_admin_can_view_withdrawn_user_detail_with_renamed_email(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['email' => 'gone@example.test']);
        app(UserWithdrawalService::class)->withdraw($target);
        $renamed = $target->fresh()->email;

        $response = $this->actingAs($admin)->get(route('admin.users.show', $target));

        $response->assertOk();
        $response->assertSee($renamed);
        $response->assertSee(UserStatus::Withdrawn->label());
    }

    public function test_displays_status_logs_and_invitations(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->student()->create();

        UserStatusLog::factory()->create([
            'user_id' => $target->id,
            'changed_by_user_id' => $admin->id,
            'status' => UserStatus::InProgress->value,
            'changed_at' => now()->subDays(2),
            'changed_reason' => 'オンボーディング完了',
        ]);

        Invitation::factory()->forUser($target)->accepted()->create([
            'invited_by_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.show', $target));

        $response->assertOk();
        $response->assertSee('オンボーディング完了');
    }

    public function test_returns_404_for_non_existing_user(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/admin/users/01ABCDEFGHJKMNPQRSTUVWXYZX');

        $response->assertNotFound();
    }

    public function test_status_log_shows_system_actor_when_changed_by_is_null(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->invited()->create();

        UserStatusLog::factory()->bySystem()->create([
            'user_id' => $target->id,
            'status' => UserStatus::Withdrawn->value,
            'changed_reason' => '招待期限切れ',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.show', $target));

        $response->assertOk();
        $response->assertSee('システム');
        $response->assertSee('招待期限切れ');
    }
}
