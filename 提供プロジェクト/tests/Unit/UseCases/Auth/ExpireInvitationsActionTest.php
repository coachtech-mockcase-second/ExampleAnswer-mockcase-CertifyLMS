<?php

declare(strict_types=1);

namespace Tests\Unit\UseCases\Auth;

use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\User;
use App\UseCases\Auth\ExpireInvitationsAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireInvitationsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_only_pending_past_expiry_as_expired(): void
    {
        $admin = User::factory()->admin()->create();
        $u1 = User::factory()->invited()->create();
        $u2 = User::factory()->invited()->create();
        $past = Invitation::factory()->forUser($u1)->pending()->expiringAt(now()->subDay())
            ->create(['invited_by_user_id' => $admin->id]);
        $future = Invitation::factory()->forUser($u2)->pending()->expiringAt(now()->addDays(3))
            ->create(['invited_by_user_id' => $admin->id]);

        app(ExpireInvitationsAction::class)();

        $this->assertSame(InvitationStatus::Expired, $past->fresh()->status);
        $this->assertSame(InvitationStatus::Pending, $future->fresh()->status);
    }

    public function test_does_not_touch_accepted_or_revoked_invitations(): void
    {
        $admin = User::factory()->admin()->create();
        $u = User::factory()->invited()->create();
        $accepted = Invitation::factory()->forUser($u)->accepted()
            ->create(['invited_by_user_id' => $admin->id]);
        $revoked = Invitation::factory()->revoked()
            ->create(['invited_by_user_id' => $admin->id]);

        app(ExpireInvitationsAction::class)();

        $this->assertSame(InvitationStatus::Accepted, $accepted->fresh()->status);
        $this->assertSame(InvitationStatus::Revoked, $revoked->fresh()->status);
    }
}
