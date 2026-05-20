<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Dashboard;

use App\Models\User;
use App\Services\EnrollmentStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('dashboard.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_admin_user_sees_admin_dashboard_blade(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        $response = $this->actingAs($admin)->get(route('dashboard.index'));

        $response->assertOk();
        $response->assertViewIs('dashboard.admin');
    }

    public function test_coach_user_sees_coach_dashboard_blade(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        $response = $this->actingAs($coach)->get(route('dashboard.index'));

        $response->assertOk();
        $response->assertViewIs('dashboard.coach');
    }

    public function test_student_user_sees_student_dashboard_blade(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($student)->get(route('dashboard.index'));

        $response->assertOk();
        $response->assertViewIs('dashboard.student');
    }

    public function test_graduated_student_sees_graduated_dashboard_blade(): void
    {
        $graduated = User::factory()->student()->graduated()->create();

        $response = $this->actingAs($graduated)->get(route('dashboard.index'));

        $response->assertOk();
        $response->assertViewIs('dashboard.graduated');
    }

    public function test_admin_dashboard_renders_even_when_kpi_service_throws(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        $mock = Mockery::mock(EnrollmentStatsService::class);
        $mock->shouldReceive('adminKpi')->andThrow(new \RuntimeException('boom'));
        $mock->shouldReceive('completionRateByCertification')->andThrow(new \RuntimeException('boom'));
        $this->app->instance(EnrollmentStatsService::class, $mock);

        $response = $this->actingAs($admin)->get(route('dashboard.index'));

        $response->assertOk();
        $response->assertViewIs('dashboard.admin');
        $response->assertSee('まずはプランを作成してユーザーを招待してください');
    }
}
