<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * ResolveDefaultEnrollment Middleware の検証。
 *
 * URL に {enrollment} があれば skip、無ければデフォルト資格を解決して 302 redirect、
 * 解決できなければ Controller に委譲(フォールバック UI 表示用)する分岐を網羅する。
 */
class ResolveDefaultEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 2 階層目: redirect の到着点
        Route::get('/__test/middleware-dest/{enrollment}', fn () => 'destination-page')
            ->middleware(['auth', 'role:student', 'active-learning'])
            ->name('__test.middleware.dest');

        // 1 階層目: Middleware の対象。redirect 先 route name 引数を渡す
        Route::get('/__test/middleware-src', fn () => 'fallback-page')
            ->middleware(['auth', 'role:student', 'active-learning', 'resolve-default-enrollment:__test.middleware.dest'])
            ->name('__test.middleware.src');

        // chain ->name() で設定した名前を Route name lookup table に反映
        app('router')->getRoutes()->refreshNameLookups();
    }

    public function test_redirects_to_default_when_set_and_active(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $default = Enrollment::factory()->for($user)->learning()->create();
        $user->update(['default_enrollment_id' => $default->id]);

        $response = $this->actingAs($user)->get('/__test/middleware-src');

        $response->assertRedirect('/__test/middleware-dest/'.$default->id);
    }

    public function test_redirects_to_default_when_set_and_passed(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $default = Enrollment::factory()->for($user)->passed()->create();
        $user->update(['default_enrollment_id' => $default->id]);

        $response = $this->actingAs($user)->get('/__test/middleware-src');

        $response->assertRedirect('/__test/middleware-dest/'.$default->id);
    }

    public function test_clears_invalid_default_and_falls_back_when_default_is_failed(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $defaulted = Enrollment::factory()->for($user)->failed()->create();
        $user->update(['default_enrollment_id' => $defaulted->id]);

        // 残存 0 件 → next pass で fallback 表示
        $response = $this->actingAs($user)->get('/__test/middleware-src');

        $response->assertOk();
        $response->assertSee('fallback-page');
        $this->assertNull($user->fresh()->default_enrollment_id);
    }

    public function test_clears_invalid_default_and_falls_back_when_default_is_soft_deleted(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $defaulted = Enrollment::factory()->for($user)->learning()->create();
        $user->update(['default_enrollment_id' => $defaulted->id]);
        $defaulted->delete();

        $response = $this->actingAs($user)->get('/__test/middleware-src');

        $response->assertOk();
        $this->assertNull($user->fresh()->default_enrollment_id);
    }

    public function test_redirects_after_clearing_invalid_default_when_one_active_remains(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $defaulted = Enrollment::factory()->for($user)->failed()->create();
        $user->update(['default_enrollment_id' => $defaulted->id]);
        $remaining = Enrollment::factory()->for($user)->learning()->create();

        $response = $this->actingAs($user)->get('/__test/middleware-src');

        $response->assertRedirect(route('__test.middleware.dest', ['enrollment' => $remaining->id]));
        $this->assertNull($user->fresh()->default_enrollment_id);
    }

    public function test_auto_redirects_when_default_null_and_exactly_one_active(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $only = Enrollment::factory()->for($user)->learning()->create();

        $response = $this->actingAs($user)->get('/__test/middleware-src');

        $response->assertRedirect(route('__test.middleware.dest', ['enrollment' => $only->id]));
        $this->assertNull($user->fresh()->default_enrollment_id);
    }

    public function test_passes_through_when_default_null_and_two_or_more_active(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        Enrollment::factory()->for($user)->learning()->create();
        Enrollment::factory()->for($user)->passed()->create();

        $response = $this->actingAs($user)->get('/__test/middleware-src');

        $response->assertOk();
        $response->assertSee('fallback-page');
    }

    public function test_passes_through_when_no_active_enrollments(): void
    {
        $user = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($user)->get('/__test/middleware-src');

        $response->assertOk();
        $response->assertSee('fallback-page');
    }

    public function test_skips_resolution_when_route_has_enrollment_parameter(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $defaultEnrollment = Enrollment::factory()->for($user)->learning()->create();
        $user->update(['default_enrollment_id' => $defaultEnrollment->id]);
        $explicit = Enrollment::factory()->for($user)->learning()->create();

        Route::middleware(['auth', 'role:student', 'active-learning', 'resolve-default-enrollment:__test.middleware.dest'])
            ->get('/__test/middleware-explicit/{enrollment}', fn () => 'explicit-page')
            ->name('__test.middleware.explicit');

        $response = $this->actingAs($user)->get('/__test/middleware-explicit/'.$explicit->id);

        $response->assertOk();
        $response->assertSee('explicit-page');
    }

    public function test_failed_enrollment_is_excluded_from_auto_redirect(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        Enrollment::factory()->for($user)->failed()->create();

        // 唯一の Enrollment が failed なので auto redirect は発火せず fallback
        $response = $this->actingAs($user)->get('/__test/middleware-src');

        $response->assertOk();
        $response->assertSee('fallback-page');
    }
}
