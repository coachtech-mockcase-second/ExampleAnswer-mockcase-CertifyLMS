<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Api;

use App\Enums\UserStatus;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class UserIndexTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_KEY = 'test-analytics-api-key-32-chars-aaaa';

    protected function setUp(): void
    {
        parent::setUp();

        config(['analytics-export.api_key' => self::VALID_KEY]);
    }

    private function authedGet(string $url): TestResponse
    {
        return $this->withHeader('X-API-KEY', self::VALID_KEY)->getJson($url);
    }

    public function test_returns_all_active_users_ordered_by_created_at(): void
    {
        User::query()->delete();
        $u1 = User::factory()->student()->create(['created_at' => now()->subDays(3)]);
        $u2 = User::factory()->coach()->create(['created_at' => now()->subDays(2)]);
        $u3 = User::factory()->admin()->create(['created_at' => now()->subDays(1)]);

        $response = $this->authedGet('/api/v1/admin/users');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $u1->id);
        $response->assertJsonPath('data.1.id', $u2->id);
        $response->assertJsonPath('data.2.id', $u3->id);
    }

    public function test_excludes_withdrawn_users(): void
    {
        User::query()->delete();
        $active = User::factory()->student()->create();
        $gone = User::factory()->withdrawn()->create();

        $response = $this->authedGet('/api/v1/admin/users');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($active->id, $ids);
        $this->assertNotContains($gone->id, $ids);
    }

    public function test_filter_by_role(): void
    {
        User::query()->delete();
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $response = $this->authedGet('/api/v1/admin/users?role=coach');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$coach->id], $ids);
    }

    public function test_filter_by_status_invited(): void
    {
        User::query()->delete();
        $invited = User::factory()->invited()->create();
        $inProgress = User::factory()->create(['status' => UserStatus::InProgress->value]);

        $response = $this->authedGet('/api/v1/admin/users?status=invited');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($invited->id, $ids);
        $this->assertNotContains($inProgress->id, $ids);
    }

    public function test_filter_by_status_graduated(): void
    {
        User::query()->delete();
        $graduated = User::factory()->graduated()->create();
        $inProgress = User::factory()->create(['status' => UserStatus::InProgress->value]);

        $response = $this->authedGet('/api/v1/admin/users?status=graduated');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$graduated->id], $ids);
    }

    public function test_filter_by_status_withdrawn_returns_422(): void
    {
        $response = $this->authedGet('/api/v1/admin/users?status=withdrawn');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    public function test_per_page_over_500_returns_422(): void
    {
        $response = $this->authedGet('/api/v1/admin/users?per_page=501');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['per_page']);
    }

    public function test_per_page_within_range_paginates(): void
    {
        User::query()->delete();
        User::factory()->student()->count(5)->create();

        $response = $this->authedGet('/api/v1/admin/users?per_page=2');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.per_page', 2);
    }

    public function test_resource_includes_plan_fields(): void
    {
        User::query()->delete();
        $plan = Plan::factory()->create();
        $now = now();
        $student = User::factory()->student()->create([
            'plan_id' => $plan->id,
            'plan_started_at' => $now->copy()->subDays(10),
            'plan_expires_at' => $now->copy()->addDays(80),
            'max_meetings' => 6,
        ]);

        $response = $this->authedGet('/api/v1/admin/users');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                [
                    'id', 'name', 'email', 'role', 'status', 'last_login_at',
                    'plan_id', 'plan_started_at', 'plan_expires_at', 'max_meetings',
                    'created_at', 'updated_at',
                ],
            ],
        ]);
        $studentRow = collect($response->json('data'))->firstWhere('id', $student->id);
        $this->assertNotNull($studentRow);
        $this->assertSame($plan->id, $studentRow['plan_id']);
        $this->assertSame(6, $studentRow['max_meetings']);
    }

    public function test_resource_excludes_sensitive_columns(): void
    {
        User::query()->delete();
        User::factory()->student()->create([
            'bio' => 'シークレットなバイオ',
            'avatar_url' => '/uploads/secret.png',
            'meeting_url' => 'https://zoom.us/j/secret',
        ]);

        $response = $this->authedGet('/api/v1/admin/users');

        $response->assertOk();
        $raw = $response->getContent();
        $this->assertStringNotContainsString('password', $raw);
        $this->assertStringNotContainsString('remember_token', $raw);
        $this->assertStringNotContainsString('bio', $raw);
        $this->assertStringNotContainsString('avatar_url', $raw);
        $this->assertStringNotContainsString('profile_setup_completed', $raw);
        $this->assertStringNotContainsString('email_verified_at', $raw);
        $this->assertStringNotContainsString('meeting_url', $raw);
    }

    public function test_invalid_api_key_returns_401(): void
    {
        $response = $this->withHeader('X-API-KEY', 'wrong')->getJson('/api/v1/admin/users');

        $response->assertStatus(401);
        $response->assertJsonPath('error_code', 'INVALID_API_KEY');
    }
}
