<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * graduated 受講生もプロフィール / パスワード / アバターを操作できることを保証する。
 * EnsureActiveLearning Middleware は本 Feature のルートに適用しない方針(`product.md` L482 と整合)。
 */
class GraduatedAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_graduated_student_can_view_profile_settings(): void
    {
        $student = User::factory()->student()->graduated()->create();

        $response = $this->actingAs($student)->get(route('settings.profile.edit'));

        $response->assertOk();
        $response->assertSeeText('プロフィール設定');
    }

    public function test_graduated_student_can_update_profile(): void
    {
        $student = User::factory()->student()->graduated()->create();

        $response = $this->actingAs($student)->patch(route('settings.profile.update'), [
            'name' => '卒業生',
            'bio' => '卒業後のステータスです',
        ]);

        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'profile']));
        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'name' => '卒業生',
        ]);
    }

    public function test_graduated_student_can_update_password(): void
    {
        $student = User::factory()->student()->graduated()->create();

        $response = $this->actingAs($student)
            ->from(route('settings.profile.edit', ['tab' => 'password']))
            ->put(route('settings.password.update'), [
                'current_password' => 'password',
                'password' => 'new-strong-pass-1',
                'password_confirmation' => 'new-strong-pass-1',
            ]);

        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'password']));
        $response->assertSessionHas('status', 'password-updated');
    }

    public function test_graduated_student_can_upload_avatar(): void
    {
        Storage::fake('public');
        $student = User::factory()->student()->graduated()->create(['avatar_url' => null]);

        $response = $this->actingAs($student)->post(route('settings.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('me.png', 100, 100)->mimeType('image/png'),
        ]);

        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'profile']));
        $this->assertNotNull($student->fresh()->avatar_url);
    }

    public function test_settings_withdraw_url_returns_404(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get('/settings/withdraw');

        $response->assertNotFound();
    }
}
