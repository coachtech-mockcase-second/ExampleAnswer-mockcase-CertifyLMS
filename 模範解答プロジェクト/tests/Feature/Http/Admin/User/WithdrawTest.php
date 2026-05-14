<?php

namespace Tests\Feature\Http\Admin\User;

use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserStatusLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WithdrawTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_withdraw_active_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['email' => 'leaving@example.test']);

        $response = $this->actingAs($admin)->post(route('admin.users.withdraw', $target), [
            'reason' => '利用規約違反',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('success');
    }

    public function test_renames_email_and_soft_deletes(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['email' => 'leaving@example.test']);

        $this->actingAs($admin)->post(route('admin.users.withdraw', $target), [
            'reason' => '利用規約違反',
        ])->assertRedirect();

        $fresh = User::withTrashed()->find($target->id);

        $this->assertNotNull($fresh->deleted_at);
        $this->assertSame(UserStatus::Withdrawn, $fresh->status);
        $this->assertSame("{$target->id}@deleted.invalid", $fresh->email);
    }

    public function test_inserts_user_status_log_with_admin_as_changer(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.withdraw', $target), [
            'reason' => 'コーチからの依頼',
        ])->assertRedirect();

        $this->assertDatabaseHas('user_status_logs', [
            'user_id' => $target->id,
            'changed_by_user_id' => $admin->id,
            'status' => UserStatus::Withdrawn->value,
            'changed_reason' => 'コーチからの依頼',
        ]);
    }

    public function test_admin_cannot_withdraw_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.withdraw', $admin), [
            'reason' => 'なんとなく',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'deleted_at' => null,
        ]);
    }

    public function test_cannot_withdraw_invited_user(): void
    {
        $admin = User::factory()->admin()->create();
        $invited = User::factory()->invited()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.withdraw', $invited), [
            'reason' => 'テスト',
        ]);

        $response->assertStatus(422);
        $this->assertNotSoftDeleted($invited);
    }

    public function test_reason_is_required(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.users.show', $target))
            ->post(route('admin.users.withdraw', $target), [
                'reason' => '',
            ]);

        $response->assertSessionHasErrors('reason');
        $this->assertNotSoftDeleted($target);
    }
}
