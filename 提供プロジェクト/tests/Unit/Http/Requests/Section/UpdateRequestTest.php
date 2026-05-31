<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Section;

use App\Http\Requests\Section\UpdateRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Section 更新 UpdateRequest の rules() を検証する Unit テスト。
 * title / body の必須 + 文字数上限を網羅する (Store と同じ rules)。
 */
class UpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_valid_payload(): void
    {
        $validator = Validator::make([
            'title' => '更新後のタイトル',
            'body' => '更新後の本文',
        ], (new UpdateRequest)->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_fails_when_title_exceeds_max(): void
    {
        $validator = Validator::make([
            'title' => str_repeat('a', 201),
            'body' => '本文',
        ], (new UpdateRequest)->rules());

        $this->assertArrayHasKey('title', $validator->errors()->toArray());
    }
}
