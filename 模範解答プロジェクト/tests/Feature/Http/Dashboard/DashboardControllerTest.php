<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Dashboard;

use App\Models\Certificate;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\LearningSession;
use App\Models\Part;
use App\Models\Plan;
use App\Models\Section;
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

    public function test_student_dashboard_renders_resume_card_with_last_viewed_section(): void
    {
        // Arrange: 学習中資格 + 公開 Section + 直近の学習履歴
        $plan = Plan::factory()->published()->create();
        $student = User::factory()->student()->inProgress()->withPlan($plan)->create();
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($cert)->learning()->create();
        $part = Part::factory()->forCertification($cert)->published()->create(['order' => 1]);
        $chapter = Chapter::factory()->forPart($part)->published()->create(['order' => 1]);
        $section = Section::factory()->forChapter($chapter)->published()->create([
            'order' => 1,
            'title' => '前回見たセクション',
        ]);
        LearningSession::factory()->forEnrollment($enrollment)->forSection($section)->create([
            'started_at' => now()->subMinutes(5),
        ]);

        // Act
        $response = $this->actingAs($student)->get(route('dashboard.index'));

        // Assert: 前回の続きカードが最後に開いた Section へのリンクとして描画される
        $response->assertOk();
        $response->assertSee('前回の続き');
        $response->assertSee('前回見たセクション');
        $response->assertSee(route('learning.sections.show', $section->id));
    }

    public function test_student_dashboard_passed_section_links_to_enrollment_review_and_certificate(): void
    {
        // Arrange: 修了済資格 + 発行済み修了証
        $plan = Plan::factory()->published()->create();
        $student = User::factory()->student()->inProgress()->withPlan($plan)->create();
        $cert = Certification::factory()->published()->create(['name' => '基本情報技術者']);
        $enrollment = Enrollment::factory()->for($student)->for($cert)->passed()->create([
            'passed_at' => now()->subDay(),
        ]);
        Certificate::factory()->for($student)->for($enrollment)->for($cert)->create();

        // Act
        $response = $this->actingAs($student)->get(route('dashboard.index'));

        // Assert: 修了済セクションが 資格名→受講登録詳細 / 復習→教材 / 修了証 PDF の 3 導線を描画
        $response->assertOk();
        $response->assertSee(route('enrollments.show', $enrollment->id));
        $response->assertSee(route('learning.enrollments.show', $enrollment->id));
        $response->assertSee('修了証 PDF');
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
