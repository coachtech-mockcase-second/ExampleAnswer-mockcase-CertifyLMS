<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Availability;

use App\Http\Requests\Availability\StoreRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * CoachAvailability 新規作成 StoreRequest の rules() を検証する Unit テスト。
 * day_of_week (0-6) / start_time, end_time (H:i + after:start_time) / is_active を網羅する。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_valid_payload(): void
    {
        $validator = Validator::make([
            'day_of_week' => 1,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'is_active' => true,
        ], (new StoreRequest)->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_fails_when_day_of_week_out_of_range(): void
    {
        $validator = Validator::make([
            'day_of_week' => 7,
            'start_time' => '10:00',
            'end_time' => '12:00',
        ], (new StoreRequest)->rules());

        $this->assertArrayHasKey('day_of_week', $validator->errors()->toArray());
    }

    public function test_fails_when_end_time_before_start_time(): void
    {
        $validator = Validator::make([
            'day_of_week' => 1,
            'start_time' => '15:00',
            'end_time' => '12:00',
        ], (new StoreRequest)->rules());

        $this->assertArrayHasKey('end_time', $validator->errors()->toArray());
    }

    public function test_fails_when_time_format_invalid(): void
    {
        $validator = Validator::make([
            'day_of_week' => 1,
            'start_time' => '25:99',
            'end_time' => '12:00',
        ], (new StoreRequest)->rules());

        $this->assertArrayHasKey('start_time', $validator->errors()->toArray());
    }
}
