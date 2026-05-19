<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Api;

use App\Models\MockExamSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MockExamSessionIndexTest extends TestCase
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

    public function test_returns_all_sessions_with_resource_shape(): void
    {
        MockExamSession::factory()->graded(true)->count(2)->create();

        $response = $this->authedGet('/api/v1/admin/mock-exam-sessions');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                [
                    'id', 'user_id', 'mock_exam_id', 'enrollment_id', 'status',
                    'total_correct', 'passing_score_snapshot', 'pass',
                    'started_at', 'submitted_at', 'graded_at',
                    'category_breakdown', 'created_at',
                ],
            ],
            'meta' => ['per_page', 'current_page'],
        ]);
    }

    public function test_passing_score_snapshot_is_returned_as_is(): void
    {
        $session = MockExamSession::factory()
            ->graded(true)
            ->create(['passing_score_snapshot' => 72]);

        $response = $this->authedGet('/api/v1/admin/mock-exam-sessions');

        $response->assertOk();
        $found = collect($response->json('data'))
            ->firstWhere('id', $session->id);
        $this->assertSame(72, $found['passing_score_snapshot']);
    }

    public function test_category_breakdown_is_empty_for_non_graded_sessions(): void
    {
        $notStarted = MockExamSession::factory()->notStarted()->create();

        $response = $this->authedGet('/api/v1/admin/mock-exam-sessions');

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('id', $notStarted->id);
        $this->assertSame([], $row['category_breakdown']);
    }

    public function test_filter_by_pass_true_returns_only_passed(): void
    {
        $pass = MockExamSession::factory()->graded(true)->create();
        $fail = MockExamSession::factory()->graded(false)->create();

        $response = $this->authedGet('/api/v1/admin/mock-exam-sessions?pass=true');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($pass->id, $ids);
        $this->assertNotContains($fail->id, $ids);
    }

    public function test_filter_by_pass_invalid_value_returns_422(): void
    {
        $response = $this->authedGet('/api/v1/admin/mock-exam-sessions?pass=foo');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['pass']);
    }

    public function test_filter_by_date_range(): void
    {
        $old = MockExamSession::factory()
            ->graded(true)
            ->create(['submitted_at' => now()->subMonth()]);
        $recent = MockExamSession::factory()
            ->graded(true)
            ->create(['submitted_at' => now()->subDay()]);

        $from = now()->subWeek()->format('Y-m-d');
        $to = now()->format('Y-m-d');
        $response = $this->authedGet("/api/v1/admin/mock-exam-sessions?from={$from}&to={$to}");

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($recent->id, $ids);
        $this->assertNotContains($old->id, $ids);
    }

    public function test_from_after_to_returns_422(): void
    {
        $response = $this->authedGet('/api/v1/admin/mock-exam-sessions?from=2026-05-10&to=2026-05-01');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['to']);
    }

    public function test_invalid_from_format_returns_422(): void
    {
        $response = $this->authedGet('/api/v1/admin/mock-exam-sessions?from=2026/05/10');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['from']);
    }

    public function test_filter_by_status(): void
    {
        $graded = MockExamSession::factory()->graded(true)->create();
        $cancelled = MockExamSession::factory()->canceled()->create();

        $response = $this->authedGet('/api/v1/admin/mock-exam-sessions?status=graded');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($graded->id, $ids);
        $this->assertNotContains($cancelled->id, $ids);
    }

    public function test_include_user_mock_exam_enrollment_loads_relations(): void
    {
        $session = MockExamSession::factory()->graded(true)->create();

        $response = $this->authedGet('/api/v1/admin/mock-exam-sessions?include=user,mock_exam,enrollment');

        $response->assertOk();
        $response->assertJsonPath('data.0.user.id', $session->user_id);
        $response->assertJsonPath('data.0.mock_exam.id', $session->mock_exam_id);
        $response->assertJsonPath('data.0.enrollment.id', $session->enrollment_id);
    }

    public function test_invalid_api_key_returns_401(): void
    {
        $response = $this->withHeader('X-API-KEY', 'wrong')
            ->getJson('/api/v1/admin/mock-exam-sessions');

        $response->assertStatus(401);
        $response->assertJsonPath('error_code', 'INVALID_API_KEY');
    }
}
