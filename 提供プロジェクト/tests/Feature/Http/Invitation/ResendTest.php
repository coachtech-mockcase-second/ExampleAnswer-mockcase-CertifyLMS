<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Invitation;

use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserStatusLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * 管理者の招待再送信 HTTP エンドポイント(`POST /admin/users/{user}/resend-invitation`)の Feature テスト。
 * 旧 pending の revoke + 同 User への新 Invitation 発行 + status 不変(invited 継続)を担保する。
 */
class ResendTest extends TestCase
{
    use RefreshDatabase;

    private function plan(): Plan
    {
        return Plan::factory()->published()->create([
            'duration_days' => 90,
            'default_meeting_quota' => 6,
        ]);
    }

    public function test_admin_can_resend_invitation_with_force_true(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $plan = $this->plan();
        $target = User::factory()->invited()->withPlan($plan)->create([
            'email' => 'pending@example.test',
            'role' => UserRole::Student->value,
        ]);
        Invitation::factory()->forUser($target)->pending()->create([
            'invited_by_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.invitations.resend', $target));

        $response->assertRedirect(route('admin.users.show', $target));
        $response->assertSessionHas('success');

        Mail::assertQueued(InvitationMail::class);
    }

    public function test_old_pending_is_revoked_and_user_stays_invited(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $plan = $this->plan();
        $target = User::factory()->invited()->withPlan($plan)->create([
            'email' => 'pending@example.test',
            'role' => UserRole::Student->value,
        ]);
        $oldPending = Invitation::factory()->forUser($target)->pending()->create([
            'invited_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.invitations.resend', $target))
            ->assertRedirect();

        $this->assertSame(InvitationStatus::Revoked, $oldPending->fresh()->status);

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'status' => UserStatus::Invited->value,
        ]);

        // 新 Invitation が同 user_id に存在
        $this->assertSame(2, Invitation::where('user_id', $target->id)->count());
        $this->assertSame(
            1,
            Invitation::where('user_id', $target->id)
                ->where('status', InvitationStatus::Pending->value)
                ->count(),
        );
    }

    public function test_does_not_insert_new_status_log_on_resend(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $plan = $this->plan();
        $target = User::factory()->invited()->withPlan($plan)->create([
            'email' => 'pending@example.test',
        ]);
        Invitation::factory()->forUser($target)->pending()->create([
            'invited_by_user_id' => $admin->id,
        ]);

        $before = UserStatusLog::where('user_id', $target->id)->count();

        $this->actingAs($admin)
            ->post(route('admin.invitations.resend', $target))
            ->assertRedirect();

        $this->assertSame(
            $before,
            UserStatusLog::where('user_id', $target->id)->count(),
        );
    }
}
