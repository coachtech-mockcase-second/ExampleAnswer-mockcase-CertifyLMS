<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\SettingsProfile\Avatar;

use App\Exceptions\SettingsProfile\AvatarStorageFailedException;
use App\Models\User;
use App\UseCases\Avatar\StoreAction;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Throwable;

class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_stores_avatar_and_updates_avatar_url(): void
    {
        Storage::fake('public');
        $user = User::factory()->student()->create(['avatar_url' => null]);
        $file = UploadedFile::fake()->image('me.png', 100, 100)->mimeType('image/png');

        $result = app(StoreAction::class)($user, $file);

        $this->assertStringStartsWith('/storage/avatars/', $result->avatar_url);
        $relative = substr(ltrim((string) parse_url($result->avatar_url, PHP_URL_PATH), '/'), strlen('storage/'));
        Storage::disk('public')->assertExists($relative);
    }

    public function test_deletes_old_avatar_file_when_overwriting(): void
    {
        Storage::fake('public');
        $user = User::factory()->student()->create(['avatar_url' => null]);

        $first = app(StoreAction::class)($user, UploadedFile::fake()->image('a.png', 100, 100)->mimeType('image/png'));
        $firstRelative = substr(ltrim((string) parse_url($first->avatar_url, PHP_URL_PATH), '/'), strlen('storage/'));
        Storage::disk('public')->assertExists($firstRelative);

        $second = app(StoreAction::class)($user->fresh(), UploadedFile::fake()->image('b.webp', 100, 100)->mimeType('image/webp'));

        Storage::disk('public')->assertMissing($firstRelative);
        $secondRelative = substr(ltrim((string) parse_url($second->avatar_url, PHP_URL_PATH), '/'), strlen('storage/'));
        Storage::disk('public')->assertExists($secondRelative);
    }

    public function test_does_not_delete_old_file_when_url_format_is_unexpected(): void
    {
        Storage::fake('public');
        $user = User::factory()->student()->create([
            'avatar_url' => 'https://cdn.example.com/legacy/file.png',
        ]);
        $file = UploadedFile::fake()->image('new.png', 100, 100)->mimeType('image/png');

        $result = app(StoreAction::class)($user, $file);

        $this->assertStringStartsWith('/storage/avatars/', $result->avatar_url);
        $this->assertNotSame('https://cdn.example.com/legacy/file.png', $result->avatar_url);
    }

    public function test_throws_avatar_storage_failed_exception_when_disk_save_fails(): void
    {
        $user = User::factory()->student()->create(['avatar_url' => null]);
        $file = UploadedFile::fake()->image('me.png', 100, 100)->mimeType('image/png');

        // 公開 disk への putFileAs を例外で失敗させる
        $disk = \Mockery::mock(Filesystem::class);
        $disk->shouldReceive('putFileAs')->andThrow(new \RuntimeException('disk full'));
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);

        try {
            app(StoreAction::class)($user, $file);
            $this->fail('Expected AvatarStorageFailedException to be thrown');
        } catch (AvatarStorageFailedException $e) {
            // DB の avatar_url は未変更
            $this->assertNull($user->fresh()->avatar_url);
            $this->assertSame(500, $e->getStatusCode());
        }
    }

    public function test_rolls_back_new_file_when_db_update_fails(): void
    {
        Storage::fake('public');
        $user = User::factory()->student()->create(['avatar_url' => null]);
        $file = UploadedFile::fake()->image('me.png', 100, 100)->mimeType('image/png');

        // User::update を例外で失敗させる
        $userMock = \Mockery::mock($user)->makePartial();
        $userMock->shouldReceive('update')
            ->once()
            ->andThrow(new \RuntimeException('db error'));

        try {
            app(StoreAction::class)($userMock, $file);
            $this->fail('Expected exception to be thrown');
        } catch (Throwable $e) {
            $this->assertSame('db error', $e->getMessage());

            // 新ファイルは Storage から削除されている
            $files = Storage::disk('public')->files('avatars');
            $this->assertEmpty($files, '新ファイルがロールバックされていません');
        }
    }
}
