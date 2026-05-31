<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\MeetingPack;

use App\Http\Requests\MeetingPack\StoreRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * MeetingPack 新規作成 StoreRequest の rules() を検証する Unit テスト。
 * meeting_count (1-100) / price (0-1000000) の数値レンジを valid + invalid で網羅する。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_valid_payload(): void
    {
        $validator = Validator::make([
            'name' => '面談 5 回パック',
            'meeting_count' => 5,
            'price' => 15000,
        ], (new StoreRequest)->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_fails_when_meeting_count_zero(): void
    {
        $validator = Validator::make([
            'name' => 'Sample',
            'meeting_count' => 0,
            'price' => 1000,
        ], (new StoreRequest)->rules());

        $this->assertArrayHasKey('meeting_count', $validator->errors()->toArray());
    }

    public function test_fails_when_price_exceeds_max(): void
    {
        $validator = Validator::make([
            'name' => 'Sample',
            'meeting_count' => 5,
            'price' => 1000001,
        ], (new StoreRequest)->rules());

        $this->assertArrayHasKey('price', $validator->errors()->toArray());
    }
}
