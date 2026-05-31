<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile\Avatar;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_is_redirected(): void
    {
        $response = $this->delete(route('settings.avatar.destroy'));

        $response->assertRedirect(route('login'));
    }

    public function test_destroying_removes_storage_file_and_clears_url(): void
    {
        Storage::fake('public');
        $student = User::factory()->student()->create(['avatar_url' => null]);

        $this->actingAs($student)->post(route('settings.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('me.png', 100, 100)->mimeType('image/png'),
        ]);
        $uploadedUrl = $student->fresh()->avatar_url;
        $relative = substr(ltrim((string) parse_url($uploadedUrl, PHP_URL_PATH), '/'), strlen('storage/'));
        Storage::disk('public')->assertExists($relative);

        $response = $this->actingAs($student)->delete(route('settings.avatar.destroy'));

        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'profile']));
        $response->assertSessionHas('success', 'アバター画像を削除しました。');

        $this->assertNull($student->fresh()->avatar_url);
        Storage::disk('public')->assertMissing($relative);
    }

    public function test_destroying_when_avatar_url_is_null_does_not_fail(): void
    {
        Storage::fake('public');
        $student = User::factory()->student()->create(['avatar_url' => null]);

        $response = $this->actingAs($student)->delete(route('settings.avatar.destroy'));

        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'profile']));
        $this->assertNull($student->fresh()->avatar_url);
    }
}
