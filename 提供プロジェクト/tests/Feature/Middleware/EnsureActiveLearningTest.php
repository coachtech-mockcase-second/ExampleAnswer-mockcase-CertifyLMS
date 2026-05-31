<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Http\Middleware\EnsureActiveLearning;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * EnsureActiveLearning Middleware の検証。
 *
 * 受講中(in_progress)以外のユーザー(invited / graduated / withdrawn)を 403 で弾き、
 * 未認証アクセスは Authenticate middleware で先に弾かれることを確認する。
 *
 * テスト用ルートを動的に登録して Middleware の挙動だけを切り出して検証する。
 */
class EnsureActiveLearningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth', EnsureActiveLearning::class])
            ->get('/__test/ensure-active-learning', fn () => 'ok')
            ->name('__test.ensure-active-learning');
    }

    public function test_in_progress_student_passes(): void
    {
        $user = User::factory()->student()->inProgress()->create();

        $this->actingAs($user)
            ->get('/__test/ensure-active-learning')
            ->assertOk()
            ->assertSee('ok');
    }

    public function test_in_progress_coach_passes(): void
    {
        $user = User::factory()->coach()->inProgress()->create();

        $this->actingAs($user)
            ->get('/__test/ensure-active-learning')
            ->assertOk();
    }

    public function test_in_progress_admin_passes(): void
    {
        $user = User::factory()->admin()->inProgress()->create();

        $this->actingAs($user)
            ->get('/__test/ensure-active-learning')
            ->assertOk();
    }

    public function test_graduated_user_is_forbidden(): void
    {
        $user = User::factory()->student()->graduated()->create();

        $this->actingAs($user)
            ->get('/__test/ensure-active-learning')
            ->assertForbidden();
    }

    public function test_withdrawn_user_is_forbidden(): void
    {
        $user = User::factory()->student()->withdrawn()->create();

        $this->actingAs($user)
            ->get('/__test/ensure-active-learning')
            ->assertForbidden();
    }

    public function test_invited_user_is_forbidden(): void
    {
        $user = User::factory()->student()->invited()->create();

        $this->actingAs($user)
            ->get('/__test/ensure-active-learning')
            ->assertForbidden();
    }

    public function test_unauthenticated_request_is_redirected_to_login(): void
    {
        $this->get('/__test/ensure-active-learning')
            ->assertRedirect('/login');
    }
}
