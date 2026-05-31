<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile\Avatar;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_is_redirected(): void
    {
        $response = $this->post(route('settings.avatar.store'));

        $response->assertRedirect(route('login'));
    }

    public function test_student_can_upload_png_avatar(): void
    {
        Storage::fake('public');
        $student = User::factory()->student()->create(['avatar_url' => null]);

        $response = $this->actingAs($student)->post(route('settings.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('me.png', 200, 200)->mimeType('image/png'),
        ]);

        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'profile']));
        $response->assertSessionHas('success', 'アバター画像を更新しました。');

        $fresh = $student->fresh();
        $this->assertNotNull($fresh->avatar_url);
        $this->assertStringStartsWith('/storage/avatars/', $fresh->avatar_url);

        $relative = ltrim((string) parse_url($fresh->avatar_url, PHP_URL_PATH), '/');
        $relative = substr($relative, strlen('storage/'));
        Storage::disk('public')->assertExists($relative);
    }

    public function test_uploading_replaces_old_avatar_and_deletes_old_file(): void
    {
        Storage::fake('public');
        $student = User::factory()->student()->create(['avatar_url' => null]);

        $this->actingAs($student)->post(route('settings.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('first.png', 100, 100)->mimeType('image/png'),
        ]);
        $firstUrl = $student->fresh()->avatar_url;
        $firstRelative = substr(ltrim((string) parse_url($firstUrl, PHP_URL_PATH), '/'), strlen('storage/'));

        $this->actingAs($student)->post(route('settings.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('second.webp', 120, 120)->mimeType('image/webp'),
        ]);

        $fresh = $student->fresh();
        $this->assertNotSame($firstUrl, $fresh->avatar_url);
        Storage::disk('public')->assertMissing($firstRelative);
    }

    public function test_validation_fails_when_avatar_is_missing(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)
            ->from(route('settings.profile.edit'))
            ->post(route('settings.avatar.store'), []);

        $response->assertSessionHasErrors(['avatar']);
    }

    public function test_validation_rejects_non_image_mime(): void
    {
        Storage::fake('public');
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)
            ->from(route('settings.profile.edit'))
            ->post(route('settings.avatar.store'), [
                'avatar' => UploadedFile::fake()->create('script.php', 100, 'application/x-php'),
            ]);

        $response->assertSessionHasErrors(['avatar']);
    }

    public function test_validation_rejects_oversize_image(): void
    {
        Storage::fake('public');
        $student = User::factory()->student()->create();

        $oversize = UploadedFile::fake()->image('big.png', 3000, 3000)->size(3000)->mimeType('image/png');

        $response = $this->actingAs($student)
            ->from(route('settings.profile.edit'))
            ->post(route('settings.avatar.store'), [
                'avatar' => $oversize,
            ]);

        $response->assertSessionHasErrors(['avatar']);
    }
}
