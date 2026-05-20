<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Dashboard;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_query_count_is_within_limit(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        Enrollment::factory()->for($cert)->learning()->count(3)->create();

        $this->actingAs($admin);

        $queries = $this->countQueriesFor(fn () => $this->get(route('dashboard.index')));

        $this->assertLessThanOrEqual(20, $queries, "admin dashboard exceeded query budget: {$queries}");
    }

    public function test_coach_dashboard_query_count_is_within_limit(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        $cert->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => User::factory()->admin()->create()->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
        Enrollment::factory()->for($cert)->learning()->count(4)->create();

        $this->actingAs($coach);

        $queries = $this->countQueriesFor(fn () => $this->get(route('dashboard.index')));

        $this->assertLessThanOrEqual(25, $queries, "coach dashboard exceeded query budget: {$queries}");
    }

    public function test_student_dashboard_query_count_is_within_limit(): void
    {
        $plan = Plan::factory()->published()->create();
        $student = User::factory()->student()->inProgress()->withPlan($plan)->create();
        $cert1 = Certification::factory()->published()->create();
        $cert2 = Certification::factory()->published()->create();
        Enrollment::factory()->for($student)->for($cert1)->learning()->create();
        Enrollment::factory()->for($student)->for($cert2)->learning()->create();

        $this->actingAs($student);

        $queries = $this->countQueriesFor(fn () => $this->get(route('dashboard.index')));

        // 上流 Service(LearningHourTargetService::compute / WeaknessAnalysisService 2 メソッド)が
        // 受講中資格カードごとに数クエリ走るため、2 Enrollment + プラン情報 + サイドバーバッジで 30 程度まで許容。
        // dashboard Feature の責務外として上流 Service の最適化は実施しないが、N+1 検知用にカードを増やしても
        // 1 枚あたり追加で 5 クエリ程度に収まる(線形増加)ことは保証する。
        $this->assertLessThanOrEqual(30, $queries, "student dashboard exceeded query budget: {$queries}");
    }

    public function test_student_dashboard_does_not_explode_with_more_enrollments(): void
    {
        $plan = Plan::factory()->published()->create();
        $student = User::factory()->student()->inProgress()->withPlan($plan)->create();
        for ($i = 0; $i < 5; $i++) {
            $cert = Certification::factory()->published()->create();
            Enrollment::factory()->for($student)->for($cert)->learning()->create();
        }

        $this->actingAs($student);
        $queries = $this->countQueriesFor(fn () => $this->get(route('dashboard.index')));

        // 5 Enrollment(2 Enrollment + 3 枚分の追加)で +15 クエリ以内(1 枚あたり 5 クエリ程度の線形増加)
        $this->assertLessThanOrEqual(45, $queries, "student dashboard exceeded scaled query budget: {$queries}");
    }

    public function test_graduated_dashboard_query_count_is_within_limit(): void
    {
        $graduated = User::factory()->student()->graduated()->create();
        Enrollment::factory()->for($graduated)
            ->for(Certification::factory()->published()->create())
            ->passed()
            ->create(['passed_at' => now()->subDay()]);

        $this->actingAs($graduated);

        $queries = $this->countQueriesFor(fn () => $this->get(route('dashboard.index')));

        $this->assertLessThanOrEqual(10, $queries, "graduated dashboard exceeded query budget: {$queries}");
    }

    private function countQueriesFor(\Closure $closure): int
    {
        $count = 0;
        DB::listen(function () use (&$count): void {
            $count++;
        });

        $closure();

        return $count;
    }
}
