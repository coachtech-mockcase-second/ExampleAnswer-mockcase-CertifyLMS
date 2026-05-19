<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Api;

use App\Models\Certification;
use App\Models\Enrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class EnrollmentIndexTest extends TestCase
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

    public function test_returns_all_enrollments_with_batch_fields(): void
    {
        Enrollment::factory()->learning()->count(2)->create();

        $response = $this->authedGet('/api/v1/admin/enrollments');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                [
                    'id', 'user_id', 'certification_id', 'status', 'current_term',
                    'exam_date', 'passed_at', 'progress_rate', 'last_activity_at',
                    'created_at', 'updated_at',
                ],
            ],
            'meta' => ['per_page', 'current_page', 'total'],
        ]);
    }

    public function test_filter_by_status_learning(): void
    {
        $learning = Enrollment::factory()->learning()->create();
        $passed = Enrollment::factory()->passed()->create();

        $response = $this->authedGet('/api/v1/admin/enrollments?status=learning');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($learning->id, $ids);
        $this->assertNotContains($passed->id, $ids);
    }

    public function test_filter_by_status_passed(): void
    {
        $passed = Enrollment::factory()->passed()->create();
        $learning = Enrollment::factory()->learning()->create();

        $response = $this->authedGet('/api/v1/admin/enrollments?status=passed');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$passed->id], $ids);
    }

    public function test_filter_by_status_failed(): void
    {
        $failed = Enrollment::factory()->failed()->create();
        Enrollment::factory()->learning()->create();

        $response = $this->authedGet('/api/v1/admin/enrollments?status=failed');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$failed->id], $ids);
    }

    public function test_filter_by_status_paused_returns_422(): void
    {
        $response = $this->authedGet('/api/v1/admin/enrollments?status=paused');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    public function test_filter_by_assigned_coach_id_is_unknown_query_so_ignored(): void
    {
        // assigned_coach_id クエリは撤回されており、IndexRequest にも rule が無いため
        // 単純に無視される。422 にはしない (Laravel の FormRequest 既定挙動)。
        $enrollment = Enrollment::factory()->learning()->create();

        $response = $this->authedGet('/api/v1/admin/enrollments?assigned_coach_id=01HXX');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($enrollment->id, $ids);
    }

    public function test_filter_by_certification_id(): void
    {
        $cert = Certification::factory()->published()->create();
        $target = Enrollment::factory()->learning()->create(['certification_id' => $cert->id]);
        Enrollment::factory()->learning()->create();

        $response = $this->authedGet("/api/v1/admin/enrollments?certification_id={$cert->id}");

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$target->id], $ids);
    }

    public function test_filter_by_unknown_certification_id_returns_422(): void
    {
        $response = $this->authedGet('/api/v1/admin/enrollments?certification_id=01HXX');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['certification_id']);
    }

    public function test_include_user_certification_loads_relations(): void
    {
        $enrollment = Enrollment::factory()->learning()->create();

        $response = $this->authedGet('/api/v1/admin/enrollments?include=user,certification');

        $response->assertOk();
        $response->assertJsonPath('data.0.user.id', $enrollment->user_id);
        $response->assertJsonPath('data.0.certification.id', $enrollment->certification_id);
    }

    public function test_include_assigned_coach_is_silently_ignored(): void
    {
        Enrollment::factory()->learning()->create();

        $response = $this->authedGet('/api/v1/admin/enrollments?include=assigned_coach');

        $response->assertOk();
        $body = $response->getContent();
        $this->assertStringNotContainsString('"assigned_coach"', $body);
    }

    public function test_include_unknown_key_is_silently_ignored(): void
    {
        $enrollment = Enrollment::factory()->learning()->create();

        $response = $this->authedGet('/api/v1/admin/enrollments?include=foo,user');

        $response->assertOk();
        $response->assertJsonPath('data.0.user.id', $enrollment->user_id);
    }

    public function test_resource_does_not_include_assigned_coach_or_completion_requested_at(): void
    {
        Enrollment::factory()->learning()->create();

        $response = $this->authedGet('/api/v1/admin/enrollments');

        $response->assertOk();
        $first = $response->json('data.0');
        $this->assertArrayNotHasKey('assigned_coach_id', $first);
        $this->assertArrayNotHasKey('assigned_coach', $first);
        $this->assertArrayNotHasKey('completion_requested_at', $first);
    }

    public function test_n_plus_one_safety_with_includes(): void
    {
        Enrollment::factory()->learning()->count(8)->create();

        DB::enableQueryLog();
        $response = $this->withHeader('X-API-KEY', self::VALID_KEY)
            ->getJson('/api/v1/admin/enrollments?include=user,certification');
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        $response->assertOk();
        $this->assertLessThan(20, $count, "Expected limited query count but got {$count}");
    }

    public function test_invalid_api_key_returns_401(): void
    {
        $response = $this->withHeader('X-API-KEY', 'wrong')->getJson('/api/v1/admin/enrollments');

        $response->assertStatus(401);
        $response->assertJsonPath('error_code', 'INVALID_API_KEY');
    }
}
