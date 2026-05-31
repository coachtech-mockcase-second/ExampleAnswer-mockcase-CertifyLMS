<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingPackControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_plans_index(): void
    {
        $admin = User::factory()->admin()->create();
        MeetingPack::factory()->published()->count(3)->create();

        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.index'));

        $response->assertOk();
        $response->assertViewIs('meeting-pack.management.index');
        $response->assertViewHas('plans');
    }

    public function test_coach_and_student_cannot_access_admin_index(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $this->actingAs($coach)->get(route('admin.meeting-packs.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.meeting-packs.index'))->assertForbidden();
    }

    public function test_status_filter_returns_only_matching_plans(): void
    {
        $admin = User::factory()->admin()->create();
        MeetingPack::factory()->draft()->create(['name' => 'Draft Pack']);
        MeetingPack::factory()->published()->create(['name' => 'Published Pack']);

        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.index', ['status' => 'draft']));

        $response->assertOk();
        $response->assertSee('Draft Pack');
        $response->assertDontSee('Published Pack');
    }

    public function test_admin_can_create_plan_in_draft_status(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.meeting-packs.store'), [
            'name' => '新規 5 回パック',
            'description' => 'テスト用説明',
            'meeting_count' => 5,
            'price' => 12000,
            'sort_order' => 10,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('meeting_packs', [
            'name' => '新規 5 回パック',
            'meeting_count' => 5,
            'price' => 12000,
            'status' => 'draft',
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_store_validates_meeting_count_range(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.meeting-packs.store'), [
            'name' => 'X',
            'meeting_count' => 0,
            'price' => 1000,
        ]);

        $response->assertSessionHasErrors('meeting_count');

        $response = $this->actingAs($admin)->post(route('admin.meeting-packs.store'), [
            'name' => 'X',
            'meeting_count' => 101,
            'price' => 1000,
        ]);

        $response->assertSessionHasErrors('meeting_count');
    }

    public function test_store_validates_price_range(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.meeting-packs.store'), [
            'name' => 'X',
            'meeting_count' => 1,
            'price' => -1,
        ]);

        $response->assertSessionHasErrors('price');

        $response = $this->actingAs($admin)->post(route('admin.meeting-packs.store'), [
            'name' => 'X',
            'meeting_count' => 1,
            'price' => 2_000_000,
        ]);

        $response->assertSessionHasErrors('price');
    }

    public function test_admin_can_update_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->draft()->create();

        $response = $this->actingAs($admin)->put(route('admin.meeting-packs.update', $plan), [
            'name' => '更新後',
            'description' => null,
            'meeting_count' => 7,
            'price' => 18000,
            'sort_order' => 20,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('meeting_packs', [
            'id' => $plan->id,
            'name' => '更新後',
            'meeting_count' => 7,
            'price' => 18000,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_destroy_draft_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->draft()->create();

        $response = $this->actingAs($admin)->delete(route('admin.meeting-packs.destroy', $plan));

        $response->assertRedirect();
        $this->assertDatabaseMissing('meeting_packs', ['id' => $plan->id]);
    }

    public function test_admin_can_destroy_archived_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->archived()->create();

        $response = $this->actingAs($admin)->delete(route('admin.meeting-packs.destroy', $plan));

        $response->assertRedirect();
        $this->assertDatabaseMissing('meeting_packs', ['id' => $plan->id]);
    }

    public function test_destroy_returns_409_for_published_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->published()->create();

        $response = $this->actingAs($admin)->deleteJson(route('admin.meeting-packs.destroy', $plan));

        $this->assertSame(409, $response->status());
        $this->assertDatabaseHas('meeting_packs', ['id' => $plan->id]);
    }

    public function test_destroy_via_browser_redirects_back_with_flash_error_for_published_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->published()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.meeting-packs.show', $plan))
            ->delete(route('admin.meeting-packs.destroy', $plan));

        $response->assertRedirect(route('admin.meeting-packs.show', $plan));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('meeting_packs', ['id' => $plan->id]);
    }

    public function test_publish_transitions_draft_to_published(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->draft()->create();

        $response = $this->actingAs($admin)->post(route('admin.meeting-packs.publish', $plan));

        $response->assertRedirect();
        $this->assertSame(MeetingPackStatus::Published, $plan->fresh()->status);
    }

    public function test_publish_returns_409_when_plan_is_not_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->published()->create();

        $response = $this->actingAs($admin)->postJson(route('admin.meeting-packs.publish', $plan));

        $this->assertSame(409, $response->status());
    }

    public function test_archive_transitions_published_to_archived(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->published()->create();

        $response = $this->actingAs($admin)->post(route('admin.meeting-packs.archive', $plan));

        $response->assertRedirect();
        $this->assertSame(MeetingPackStatus::Archived, $plan->fresh()->status);
    }

    public function test_unarchive_transitions_archived_to_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->archived()->create();

        $response = $this->actingAs($admin)->post(route('admin.meeting-packs.unarchive', $plan));

        $response->assertRedirect();
        $this->assertSame(MeetingPackStatus::Draft, $plan->fresh()->status);
    }

    public function test_state_transitions_require_admin_role(): void
    {
        $coach = User::factory()->coach()->create();
        $plan = MeetingPack::factory()->draft()->create();

        $this->actingAs($coach)->post(route('admin.meeting-packs.publish', $plan))->assertForbidden();
        $this->actingAs($coach)->post(route('admin.meeting-packs.archive', $plan))->assertForbidden();
        $this->actingAs($coach)->post(route('admin.meeting-packs.unarchive', $plan))->assertForbidden();
    }
}
