<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Plan;

use App\Http\Requests\Plan\UpdateRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Plan 更新 UpdateRequest の rules() を検証する Unit テスト。
 * Store と同じ rules を valid + invalid で確認する。
 */
class UpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_valid_payload(): void
    {
        $validator = Validator::make([
            'name' => 'Premium Plan',
            'duration_days' => 180,
            'default_meeting_quota' => 12,
        ], (new UpdateRequest)->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_fails_when_duration_days_zero(): void
    {
        $validator = Validator::make([
            'name' => 'X',
            'duration_days' => 0,
            'default_meeting_quota' => 6,
        ], (new UpdateRequest)->rules());

        $this->assertArrayHasKey('duration_days', $validator->errors()->toArray());
    }
}
