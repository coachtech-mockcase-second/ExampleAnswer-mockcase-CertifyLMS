<?php

declare(strict_types=1);

namespace Tests\Feature\Http\User;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WithdrawTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_withdraw_in_progress_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->inProgress()->create(['email' => 'leaving@example.test']);

        $response = $this->actingAs($admin)->post(route('admin.users.withdraw', $target));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('success');
    }

    public function test_renames_email_and_soft_deletes(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->inProgress()->create(['email' => 'leaving@example.test']);

        $this->actingAs($admin)->post(route('admin.users.withdraw', $target))->assertRedirect();

        $fresh = User::withTrashed()->find($target->id);

        $this->assertNotNull($fresh->deleted_at);
        $this->assertSame(UserStatus::Withdrawn, $fresh->status);
        $this->assertSame("{$target->id}@deleted.invalid", $fresh->email);
    }

    public function test_inserts_user_status_log_with_admin_as_changer(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->inProgress()->create();

        $this->actingAs($admin)->post(route('admin.users.withdraw', $target))->assertRedirect();

        $this->assertDatabaseHas('user_status_logs', [
            'user_id' => $target->id,
            'changed_by_user_id' => $admin->id,
            'from_status' => UserStatus::InProgress->value,
            'to_status' => UserStatus::Withdrawn->value,
            'changed_reason' => '管理者による退会',
        ]);
    }

    public function test_returns_409_for_already_withdrawn_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->withdrawn()->create();

        $response = $this->actingAs($admin)->postJson(route('admin.users.withdraw', $target));

        $response->assertStatus(409);
    }

    public function test_returns_409_for_last_remaining_admin_self_withdraw(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        $response = $this->actingAs($admin)->postJson(route('admin.users.withdraw', $admin));

        $response->assertStatus(409);
        $this->assertNotSoftDeleted($admin);
        $this->assertSame(UserStatus::InProgress, $admin->fresh()->status);
    }

    public function test_coach_cannot_withdraw_user(): void
    {
        $coach = User::factory()->coach()->create();
        $target = User::factory()->inProgress()->create();

        $response = $this->actingAs($coach)->post(route('admin.users.withdraw', $target));

        $response->assertForbidden();
    }

    public function test_student_cannot_withdraw_user(): void
    {
        $student = User::factory()->student()->create();
        $target = User::factory()->inProgress()->create();

        $response = $this->actingAs($student)->post(route('admin.users.withdraw', $target));

        $response->assertForbidden();
    }

    public function test_html_request_redirects_back_with_flash_error_on_conflict(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->withdrawn()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.users.show', $target))
            ->post(route('admin.users.withdraw', $target));

        $response->assertRedirect(route('admin.users.show', $target));
        $response->assertSessionHas('error');
    }
}
