<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\EnrollmentGoal;

use App\Http\Requests\EnrollmentGoal\StoreRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * EnrollmentGoal 新規作成 StoreRequest の rules() を検証する Unit テスト。
 * title (required + max:100) / description (nullable + max:1000) / target_date (nullable + date) を網羅する。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_valid_payload(): void
    {
        $validator = Validator::make([
            'title' => 'Section 5 まで完了する',
            'description' => '今週中に達成',
            'target_date' => '2026-06-30',
        ], (new StoreRequest)->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_fails_when_title_missing(): void
    {
        $validator = Validator::make([], (new StoreRequest)->rules());
        $this->assertArrayHasKey('title', $validator->errors()->toArray());
    }

    public function test_fails_when_target_date_invalid_format(): void
    {
        $validator = Validator::make([
            'title' => 'タイトル',
            'target_date' => 'not-a-date',
        ], (new StoreRequest)->rules());

        $this->assertArrayHasKey('target_date', $validator->errors()->toArray());
    }
}
