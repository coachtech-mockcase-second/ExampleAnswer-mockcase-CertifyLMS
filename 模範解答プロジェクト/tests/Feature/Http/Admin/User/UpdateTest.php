<?php

namespace Tests\Feature\Http\Admin\User;

use App\Models\User;
use App\Models\UserStatusLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_user_profile(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->student()->create([
            'name' => '旧名前',
            'email' => 'old@example.test',
            'bio' => null,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.users.update', $target), [
            'name' => '新名前',
            'email' => 'new@example.test',
            'bio' => 'よろしくお願いします',
        ]);

        $response->assertRedirect(route('admin.users.show', $target));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'name' => '新名前',
            'email' => 'new@example.test',
            'bio' => 'よろしくお願いします',
        ]);
    }

    public function test_email_uniqueness_excludes_target_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['email' => 'same@example.test']);

        $response = $this->actingAs($admin)->patch(route('admin.users.update', $target), [
            'name' => '名前',
            'email' => 'same@example.test',
            'bio' => null,
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
    }

    public function test_rejects_duplicate_email(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->student()->create();
        User::factory()->create(['email' => 'taken@example.test']);

        $response = $this->actingAs($admin)
            ->from(route('admin.users.show', $target))
            ->patch(route('admin.users.update', $target), [
                'name' => '名前',
                'email' => 'taken@example.test',
                'bio' => null,
            ]);

        $response->assertRedirect(route('admin.users.show', $target));
        $response->assertSessionHasErrors('email');
    }

    public function test_does_not_insert_user_status_log_on_profile_update(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->student()->create();
        $before = UserStatusLog::count();

        $this->actingAs($admin)->patch(route('admin.users.update', $target), [
            'name' => '更新後',
            'email' => $target->email,
            'bio' => null,
        ])->assertRedirect();

        $this->assertSame($before, UserStatusLog::count());
    }

    public function test_validates_required_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->student()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.users.show', $target))
            ->patch(route('admin.users.update', $target), [
                'name' => '',
                'email' => 'not-an-email',
            ]);

        $response->assertSessionHasErrors(['name', 'email']);
    }
}
