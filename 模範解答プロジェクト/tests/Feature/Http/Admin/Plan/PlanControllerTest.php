<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Admin\Plan;

use App\Enums\PlanStatus;
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
        $response->assertViewIs('admin.plans.index');
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

        $response = $this->actingAs($admin)->get(route('admin.plans.index', ['status' => 'draft']));

        $response->assertOk();
        $response->assertSee('Draft Plan');
        $response->assertDontSee('Published Plan');
    }

    public function test_admin_can_create_plan_in_draft_status(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => '新規プラン',
            'description' => 'テスト用説明',
            'duration_days' => 30,
            'default_meeting_quota' => 4,
            'sort_order' => 10,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('plans', [
            'name' => '新規プラン',
            'duration_days' => 30,
            'default_meeting_quota' => 4,
            'status' => 'draft',
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_store_validates_duration_days_range(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => 'Plan',
            'duration_days' => 0,
            'default_meeting_quota' => 4,
        ]);

        $response->assertSessionHasErrors('duration_days');

        $response = $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => 'Plan',
            'duration_days' => 4000,
            'default_meeting_quota' => 4,
        ]);

        $response->assertSessionHasErrors('duration_days');
    }

    public function test_store_validates_default_meeting_quota_range(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => 'Plan',
            'duration_days' => 30,
            'default_meeting_quota' => -1,
        ]);

        $response->assertSessionHasErrors('default_meeting_quota');

        $response = $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => 'Plan',
            'duration_days' => 30,
            'default_meeting_quota' => 2000,
        ]);

        $response->assertSessionHasErrors('default_meeting_quota');
    }

    public function test_admin_can_update_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $response = $this->actingAs($admin)->put(route('admin.plans.update', $plan), [
            'name' => '更新後',
            'description' => null,
            'duration_days' => 60,
            'default_meeting_quota' => 8,
            'sort_order' => 20,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'name' => '更新後',
            'duration_days' => 60,
            'default_meeting_quota' => 8,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_destroy_draft_plan_with_no_users(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $response = $this->actingAs($admin)->delete(route('admin.plans.destroy', $plan));

        $response->assertRedirect();
        $this->assertSoftDeleted('plans', ['id' => $plan->id]);
    }

    public function test_destroy_returns_409_for_published_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        $response = $this->actingAs($admin)->delete(route('admin.plans.destroy', $plan));

        $this->assertSame(409, $response->status());
        $this->assertDatabaseHas('plans', ['id' => $plan->id, 'deleted_at' => null]);
    }

    public function test_destroy_returns_409_for_archived_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->archived()->create();

        $response = $this->actingAs($admin)->delete(route('admin.plans.destroy', $plan));

        $this->assertSame(409, $response->status());
        $this->assertDatabaseHas('plans', ['id' => $plan->id, 'deleted_at' => null]);
    }

    public function test_destroy_returns_409_for_draft_plan_with_users(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();
        User::factory()->withPlan($plan)->create();

        $response = $this->actingAs($admin)->delete(route('admin.plans.destroy', $plan));

        $this->assertSame(409, $response->status());
        $this->assertDatabaseHas('plans', ['id' => $plan->id, 'deleted_at' => null]);
    }

    public function test_publish_transitions_draft_to_published(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $response = $this->actingAs($admin)->post(route('admin.plans.publish', $plan));

        $response->assertRedirect();
        $this->assertSame(PlanStatus::Published, $plan->fresh()->status);
    }

    public function test_publish_returns_409_when_plan_is_not_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        $response = $this->actingAs($admin)->post(route('admin.plans.publish', $plan));

        $this->assertSame(409, $response->status());
    }

    public function test_archive_transitions_published_to_archived(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        $response = $this->actingAs($admin)->post(route('admin.plans.archive', $plan));

        $response->assertRedirect();
        $this->assertSame(PlanStatus::Archived, $plan->fresh()->status);
    }

    public function test_unarchive_transitions_archived_to_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->archived()->create();

        $response = $this->actingAs($admin)->post(route('admin.plans.unarchive', $plan));

        $response->assertRedirect();
        $this->assertSame(PlanStatus::Draft, $plan->fresh()->status);
    }

    public function test_state_transitions_require_admin_role(): void
    {
        $coach = User::factory()->coach()->create();
        $plan = Plan::factory()->draft()->create();

        $this->actingAs($coach)->post(route('admin.plans.publish', $plan))->assertForbidden();
        $this->actingAs($coach)->post(route('admin.plans.archive', $plan))->assertForbidden();
        $this->actingAs($coach)->post(route('admin.plans.unarchive', $plan))->assertForbidden();
    }
}
