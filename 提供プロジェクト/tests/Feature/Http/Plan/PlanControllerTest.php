<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_plans_index(): void
    {
        $admin = User::factory()->admin()->create();
        Plan::factory()->published()->count(3)->create();

        $response = $this->actingAs($admin)->get(route('admin.plans.index'));

        $response->assertOk();
        $response->assertViewIs('plan.management.index');
        $response->assertViewHas('plans');
    }

    public function test_coach_and_student_cannot_access_admin_plans_index(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $this->actingAs($coach)->get(route('admin.plans.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.plans.index'))->assertForbidden();
    }

    public function test_status_filter_returns_only_matching_plans(): void
    {
        $admin = User::factory()->admin()->create();
        Plan::factory()->draft()->create(['name' => 'Draft Plan']);
        Plan::factory()->published()->create(['name' => 'Published Plan']);

        // 公開中フィルタ: 公開中のみ表示・下書きは非表示(絞り込み条件が下書き固定だとここで落ちる)
        $response = $this->actingAs($admin)->get(route('admin.plans.index', ['status' => 'published']));
        $response->assertOk();
        $response->assertSee('Published Plan');
        $response->assertDontSee('Draft Plan');

        // 下書きフィルタ: 下書きのみ表示・公開中は非表示
        $response = $this->actingAs($admin)->get(route('admin.plans.index', ['status' => 'draft']));
        $response->assertOk();
        $response->assertSee('Draft Plan');
        $response->assertDontSee('Published Plan');
    }
}
