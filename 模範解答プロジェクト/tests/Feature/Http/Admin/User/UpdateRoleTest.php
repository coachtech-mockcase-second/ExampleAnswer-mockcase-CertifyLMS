<?php

namespace Tests\Feature\Http\Admin\User;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserStatusLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_change_role(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->coach()->create();

        $response = $this->actingAs($admin)->patch(route('admin.users.updateRole', $target), [
            'role' => UserRole::Admin->value,
        ]);

        $response->assertRedirect(route('admin.users.show', $target));
        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'role' => UserRole::Admin->value,
        ]);
    }

    public function test_admin_cannot_change_own_role(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->patch(route('admin.users.updateRole', $admin), [
            'role' => UserRole::Coach->value,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => UserRole::Admin->value,
        ]);
    }

    public function test_does_not_insert_user_status_log_on_role_change(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->student()->create();
        $before = UserStatusLog::count();

        $this->actingAs($admin)->patch(route('admin.users.updateRole', $target), [
            'role' => UserRole::Coach->value,
        ])->assertRedirect();

        $this->assertSame($before, UserStatusLog::count());
    }

    public function test_validates_role_must_be_in_allowed_list(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->student()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.users.show', $target))
            ->patch(route('admin.users.updateRole', $target), [
                'role' => 'invalid-role',
            ]);

        $response->assertSessionHasErrors('role');
    }
}
