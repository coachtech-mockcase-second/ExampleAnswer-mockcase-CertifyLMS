<?php

namespace Tests\Feature\UseCases\Auth;

use App\Enums\InvitationStatus;
use App\Enums\UserStatus;
use App\Models\Invitation;
use App\Models\User;
use App\UseCases\Auth\ExpireInvitationsAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireInvitationsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_expired_and_cascade_withdraws_users(): void
    {
        $admin = User::factory()->admin()->create();
        $user1 = User::factory()->invited()->create();
        $user2 = User::factory()->invited()->create();

        $past1 = Invitation::factory()->forUser($user1)->pending()->expiringAt(now()->subDay())
            ->create(['invited_by_user_id' => $admin->id]);
        $past2 = Invitation::factory()->forUser($user2)->pending()->expiringAt(now()->subHour())
            ->create(['invited_by_user_id' => $admin->id]);

        $count = app(ExpireInvitationsAction::class)();

        $this->assertSame(2, $count);
        $this->assertSame(InvitationStatus::Expired, $past1->fresh()->status);
        $this->assertSame(InvitationStatus::Expired, $past2->fresh()->status);

        foreach ([$user1, $user2] as $u) {
            $fresh = User::withTrashed()->find($u->id);
            $this->assertSame(UserStatus::Withdrawn, $fresh->status);
            $this->assertTrue($fresh->trashed());
        }
    }

    public function test_does_not_touch_active_or_accepted_users(): void
    {
        $admin = User::factory()->admin()->create();
        $activeUser = User::factory()->create(['status' => UserStatus::Active]);
        $acceptedInv = Invitation::factory()->forUser($activeUser)->accepted()
            ->create(['invited_by_user_id' => $admin->id]);
        $futureUser = User::factory()->invited()->create();
        $futureInv = Invitation::factory()->forUser($futureUser)->pending()
            ->expiringAt(now()->addDays(3))
            ->create(['invited_by_user_id' => $admin->id]);

        app(ExpireInvitationsAction::class)();

        $this->assertSame(InvitationStatus::Accepted, $acceptedInv->fresh()->status);
        $this->assertSame(InvitationStatus::Pending, $futureInv->fresh()->status);
        $this->assertSame(UserStatus::Active, $activeUser->fresh()->status);
        $this->assertSame(UserStatus::Invited, $futureUser->fresh()->status);
    }

    public function test_inserts_user_status_log_with_null_actor_for_each_expired_user(): void
    {
        $admin = User::factory()->admin()->create();
        $u1 = User::factory()->invited()->create();
        $u2 = User::factory()->invited()->create();
        Invitation::factory()->forUser($u1)->pending()->expiringAt(now()->subDay())
            ->create(['invited_by_user_id' => $admin->id]);
        Invitation::factory()->forUser($u2)->pending()->expiringAt(now()->subDay())
            ->create(['invited_by_user_id' => $admin->id]);

        app(ExpireInvitationsAction::class)();

        foreach ([$u1, $u2] as $u) {
            $this->assertDatabaseHas('user_status_logs', [
                'user_id' => $u->id,
                'status' => UserStatus::Withdrawn->value,
                'changed_by_user_id' => null,
                'changed_reason' => '招待期限切れ',
            ]);
        }
    }
}
