<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\MeetingPack;

use App\Http\Requests\MeetingPack\UpdateRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * MeetingPack 更新 UpdateRequest の rules() を検証する Unit テスト。
 * Store と同じ rules を valid + invalid で確認する。
 */
class UpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_valid_payload(): void
    {
        $validator = Validator::make([
            'name' => '更新後パック',
            'meeting_count' => 10,
            'price' => 30000,
        ], (new UpdateRequest)->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_fails_when_meeting_count_negative(): void
    {
        $validator = Validator::make([
            'name' => 'Sample',
            'meeting_count' => -1,
            'price' => 1000,
        ], (new UpdateRequest)->rules());

        $this->assertArrayHasKey('meeting_count', $validator->errors()->toArray());
    }
}
