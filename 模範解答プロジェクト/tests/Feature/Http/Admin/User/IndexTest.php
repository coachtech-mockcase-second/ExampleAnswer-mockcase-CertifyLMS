<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Admin\User;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\UserWithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_user_list(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->student()->count(3)->create();

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertViewIs('admin.users.index');
        $response->assertViewHas('users');
    }

    public function test_coach_and_student_cannot_access_admin_users_index(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $this->actingAs($coach)
            ->get(route('admin.users.index'))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_keyword_search_filters_by_name_and_email(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->student()->create(['name' => '山田太郎', 'email' => 'yamada@example.test']);
        User::factory()->student()->create(['name' => '佐藤花子', 'email' => 'sato@example.test']);
        User::factory()->student()->create(['name' => 'Smith John', 'email' => 'smith@example.test']);

        $response = $this->actingAs($admin)->get(route('admin.users.index', ['keyword' => '山田']));
        $response->assertOk();
        $response->assertSee('yamada@example.test');
        $response->assertDontSee('sato@example.test');

        $response = $this->actingAs($admin)->get(route('admin.users.index', ['keyword' => 'sato@']));
        $response->assertOk();
        $response->assertSee('sato@example.test');
        $response->assertDontSee('yamada@example.test');
    }

    public function test_role_filter_returns_only_matching_role(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'admin@example.test']);
        User::factory()->coach()->create(['email' => 'coach1@example.test']);
        User::factory()->student()->create(['email' => 'student1@example.test']);

        $response = $this->actingAs($admin)->get(route('admin.users.index', ['role' => 'coach']));

        $response->assertOk();
        $response->assertSee('coach1@example.test');
        $response->assertDontSee('student1@example.test');
        $response->assertDontSee('admin@example.test');
    }

    public function test_status_filter_returns_only_matching_status(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'active1@example.test', 'status' => UserStatus::InProgress->value]);
        User::factory()->invited()->create(['email' => 'invited1@example.test']);

        $response = $this->actingAs($admin)->get(route('admin.users.index', ['status' => 'invited']));

        $response->assertOk();
        $response->assertSee('invited1@example.test');
        $response->assertDontSee('active1@example.test');
    }

    public function test_status_filter_excludes_withdrawn_by_default(): void
    {
        $admin = User::factory()->admin()->create();
        $active = User::factory()->create(['email' => 'active@example.test']);
        $gone = User::factory()->create(['email' => 'gone@example.test']);
        app(UserWithdrawalService::class)->withdraw($gone);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee('active@example.test');
        $response->assertDontSee('gone@example.test');
        // UserWithdrawalService 経由で email が {ulid}@deleted.invalid にリネームされる
        $response->assertDontSee($gone->fresh()->email);
    }

    public function test_status_filter_includes_withdrawn_when_explicitly_selected(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'active@example.test']);
        $gone = User::factory()->create(['email' => 'gone@example.test']);
        app(UserWithdrawalService::class)->withdraw($gone);
        $renamedEmail = $gone->fresh()->email;

        $response = $this->actingAs($admin)->get(route('admin.users.index', ['status' => 'withdrawn']));

        $response->assertOk();
        $response->assertSee($renamedEmail);
        $response->assertDontSee('active@example.test');
    }

    public function test_paginates_20_per_page(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->student()->count(25)->create();

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk();
        $users = $response->viewData('users');
        $this->assertSame(20, $users->perPage());
        $this->assertSame(26, $users->total()); // 25 students + 1 admin
    }

    public function test_orders_by_status_priority_then_created_at_desc(): void
    {
        $admin = User::factory()->admin()->create();

        $oldInvited = User::factory()->invited()->create([
            'email' => 'old-invited@example.test',
            'created_at' => now()->subDays(5),
        ]);
        $newActive = User::factory()->create([
            'email' => 'new-active@example.test',
            'created_at' => now()->subHour(),
        ]);
        $oldActive = User::factory()->create([
            'email' => 'old-active@example.test',
            'created_at' => now()->subDays(10),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk();
        $users = $response->viewData('users');
        $emails = $users->getCollection()->pluck('email')->all();

        $newActivePos = array_search('new-active@example.test', $emails);
        $oldActivePos = array_search('old-active@example.test', $emails);
        $oldInvitedPos = array_search('old-invited@example.test', $emails);

        // Active comes before Invited (status priority)
        $this->assertLessThan($oldInvitedPos, $newActivePos);
        $this->assertLessThan($oldInvitedPos, $oldActivePos);
        // Within Active: newer created_at comes first
        $this->assertLessThan($oldActivePos, $newActivePos);
    }
}
