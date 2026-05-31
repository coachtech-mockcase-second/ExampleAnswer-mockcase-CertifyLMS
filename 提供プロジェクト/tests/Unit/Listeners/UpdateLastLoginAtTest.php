<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Listeners\UpdateLastLoginAt;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UpdateLastLoginAt リスナーが Login イベントを受けて User.last_login_at を更新することを検証する Unit テスト。
 */
class UpdateLastLoginAtTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_updates_last_login_at_for_user(): void
    {
        // Arrange
        $user = User::factory()->inProgress()->create(['last_login_at' => null]);
        $event = new Login('web', $user, false);

        // Act
        (new UpdateLastLoginAt)->handle($event);

        // Assert
        $this->assertNotNull($user->fresh()->last_login_at, 'ログインイベント後に last_login_at が記録されるはず');
    }
}
