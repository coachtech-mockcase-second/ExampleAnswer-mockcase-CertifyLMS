<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Admin\Invitation;

use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\User;
use App\Models\UserStatusLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ResendTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_resend_invitation_with_force_true(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $target = User::factory()->invited()->create([
            'email' => 'pending@example.test',
            'role' => UserRole::Student->value,
        ]);
        $oldPending = Invitation::factory()->forUser($target)->pending()->create([
            'invited_by_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.invitations.resend', $target));

        $response->assertRedirect(route('admin.users.show', $target));
        $response->assertSessionHas('success');

        Mail::assertSent(InvitationMail::class);
    }

    public function test_old_pending_is_revoked_and_user_stays_invited(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $target = User::factory()->invited()->create([
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
        $target = User::factory()->invited()->create([
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
