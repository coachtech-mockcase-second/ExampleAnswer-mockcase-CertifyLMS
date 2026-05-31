<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentSwitcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_switcher_is_populated_via_view_composer(): void
    {
        $plan = Plan::factory()->published()->create();
        $student = User::factory()->student()->inProgress()->withPlan($plan)->create();
        $cert = Certification::factory()->published()->create(['name' => 'スイッチャー表示資格']);
        $enrollment = Enrollment::factory()->for($student)->for($cert)->learning()->create();
        $student->update(['default_enrollment_id' => $enrollment->id]);

        $response = $this->actingAs($student)->get(route('dashboard.index'));

        $response->assertOk();
        // EnrollmentSwitcherComposer が $switcherEnrollments を注入できていれば switcher に資格名が出る。
        $response->assertSee('スイッチャー表示資格');
        // 注入に失敗すると switcher が空になり @empty 分岐の文言が出る (注入の回帰検知)。
        $response->assertDontSee('受講中資格がありません');
    }
}
