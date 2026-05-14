<?php

namespace Tests\Feature\Http\Admin\Invitation;

use App\Enums\InvitationStatus;
use App\Enums\UserStatus;
use App\Exceptions\Auth\InvitationNotPendingException;
use App\Models\Invitation;
use App\Models\User;
use App\UseCases\Invitation\DestroyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_cancel_pending_invitation(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->invited()->create();
        $invitation = Invitation::factory()->forUser($target)->pending()->create([
            'invited_by_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.invitations.destroy', $invitation));

        $response->assertRedirect(route('admin.users.show', $target->id));
        $response->assertSessionHas('success');

        $this->assertSame(InvitationStatus::Revoked, $invitation->fresh()->status);
    }

    public function test_user_is_cascade_withdrawn_with_renamed_email(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->invited()->create(['email' => 'invited@example.test']);
        $invitation = Invitation::factory()->forUser($target)->pending()->create([
            'invited_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.invitations.destroy', $invitation))
            ->assertRedirect();

        $fresh = User::withTrashed()->find($target->id);
        $this->assertNotNull($fresh->deleted_at);
        $this->assertSame(UserStatus::Withdrawn, $fresh->status);
        $this->assertSame("{$target->id}@deleted.invalid", $fresh->email);
    }

    public function test_inserts_user_status_log_with_admin_as_changer(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->invited()->create();
        $invitation = Invitation::factory()->forUser($target)->pending()->create([
            'invited_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.invitations.destroy', $invitation))
            ->assertRedirect();

        $this->assertDatabaseHas('user_status_logs', [
            'user_id' => $target->id,
            'changed_by_user_id' => $admin->id,
            'status' => UserStatus::Withdrawn->value,
            'changed_reason' => '招待取消',
        ]);
    }

    public function test_throws_invitation_not_pending_for_accepted_invitation(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        $invitation = Invitation::factory()->forUser($target)->accepted()->create([
            'invited_by_user_id' => $admin->id,
        ]);

        $this->expectException(InvitationNotPendingException::class);

        app(DestroyAction::class)($invitation, $admin);
    }
}
