<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\MeetingQuota;

use App\Http\Requests\MeetingQuota\CheckoutRequest;
use App\Models\MeetingPack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 追加面談 Checkout CheckoutRequest の rules() を検証する Unit テスト。
 * meeting_pack_id の exists where (status=published) を網羅する。
 */
class CheckoutRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_published_meeting_pack(): void
    {
        $pack = MeetingPack::factory()->published()->create();
        $validator = Validator::make(['meeting_pack_id' => $pack->id], (new CheckoutRequest)->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_fails_when_meeting_pack_draft(): void
    {
        $pack = MeetingPack::factory()->draft()->create();
        $validator = Validator::make(['meeting_pack_id' => $pack->id], (new CheckoutRequest)->rules());

        $this->assertArrayHasKey('meeting_pack_id', $validator->errors()->toArray());
    }

    public function test_fails_when_meeting_pack_nonexistent(): void
    {
        $validator = Validator::make([
            'meeting_pack_id' => (string) Str::ulid(),
        ], (new CheckoutRequest)->rules());

        $this->assertArrayHasKey('meeting_pack_id', $validator->errors()->toArray());
    }
}
