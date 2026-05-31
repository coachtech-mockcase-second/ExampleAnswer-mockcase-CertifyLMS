<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Dashboard;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\EnrollmentStatsService;
use App\UseCases\Dashboard\FetchAdminDashboardAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class FetchAdminDashboardActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_kpi_aggregated_from_enrollments(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $cert = Certification::factory()->published()->create(['name' => '基本情報技術者']);
        Enrollment::factory()->for($cert)->learning()->count(3)->create();
        Enrollment::factory()->for($cert)->passed()->count(2)->create();
        Enrollment::factory()->for($cert)->failed()->count(1)->create();

        $vm = app(FetchAdminDashboardAction::class)($admin);

        $this->assertSame(3, $vm->kpi['learning_count']);
        $this->assertSame(2, $vm->kpi['passed_count']);
        $this->assertSame(1, $vm->kpi['failed_count']);
        $this->assertFalse($vm->isEmptyState);
    }

    public function test_kpi_array_does_not_carry_pending_count_key(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        Enrollment::factory()->for($cert)->learning()->create();

        $vm = app(FetchAdminDashboardAction::class)($admin);

        $this->assertArrayNotHasKey('pending_count', $vm->kpi);
    }

    public function test_by_certification_breakdown_is_top_10(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $certifications = Certification::factory()->published()->count(12)->create();
        foreach ($certifications as $index => $cert) {
            Enrollment::factory()->for($cert)->learning()->count($index + 1)->create();
        }

        $vm = app(FetchAdminDashboardAction::class)($admin);

        $this->assertCount(10, $vm->byCertificationTop10);
        // 上位は受講中件数の多い順
        $this->assertSame(12, $vm->byCertificationTop10->first()['learning']);
    }

    public function test_completion_rate_excludes_zero_total_certifications(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $certWithEnrollments = Certification::factory()->published()->create(['name' => 'A']);
        Certification::factory()->published()->create(['name' => 'B']);
        Enrollment::factory()->for($certWithEnrollments)->passed()->count(3)->create();
        Enrollment::factory()->for($certWithEnrollments)->learning()->create();

        $vm = app(FetchAdminDashboardAction::class)($admin);

        $this->assertCount(1, $vm->completionRateByCertification);
        $row = $vm->completionRateByCertification->first();
        $this->assertSame('A', $row['certification_name']);
        $this->assertSame(0.75, $row['completion_rate']);
    }

    public function test_empty_state_is_true_when_no_enrollments(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        $vm = app(FetchAdminDashboardAction::class)($admin);

        $this->assertTrue($vm->isEmptyState);
    }

    public function test_kpi_null_when_service_throws_and_does_not_have_notifications_property(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        $mock = Mockery::mock(EnrollmentStatsService::class);
        $mock->shouldReceive('adminKpi')->andThrow(new \RuntimeException('failure'));
        $mock->shouldReceive('completionRateByCertification')->andReturn(collect());
        $this->app->instance(EnrollmentStatsService::class, $mock);

        $vm = app(FetchAdminDashboardAction::class)($admin);

        $this->assertNull($vm->kpi);
        $this->assertTrue($vm->isEmptyState);
        $this->assertFalse(property_exists($vm, 'recentNotifications'));
        $this->assertFalse(property_exists($vm, 'unreadNotificationCount'));
    }
}
