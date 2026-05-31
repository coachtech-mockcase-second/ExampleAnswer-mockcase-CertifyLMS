<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\User;

use App\Http\Requests\User\ExtendCourseRequest;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * User 受講期間延長 ExtendCourseRequest の rules() を検証する Unit テスト。
 * plan_id の exists where (status=Published) を網羅する。
 */
class ExtendCourseRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_published_plan(): void
    {
        $plan = Plan::factory()->published()->create();
        $validator = Validator::make(['plan_id' => $plan->id], (new ExtendCourseRequest)->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_fails_when_plan_draft(): void
    {
        $plan = Plan::factory()->draft()->create();
        $validator = Validator::make(['plan_id' => $plan->id], (new ExtendCourseRequest)->rules());

        $this->assertArrayHasKey('plan_id', $validator->errors()->toArray());
    }

    public function test_fails_when_plan_nonexistent(): void
    {
        $validator = Validator::make(['plan_id' => (string) Str::ulid()], (new ExtendCourseRequest)->rules());

        $this->assertArrayHasKey('plan_id', $validator->errors()->toArray());
    }
}
