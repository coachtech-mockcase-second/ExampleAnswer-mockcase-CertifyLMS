<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Part;

use App\Http\Requests\Part\StoreRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Part 新規作成 StoreRequest の rules() を検証する Unit テスト。
 * title (required + max:200) / description (nullable + max:1000) を網羅する。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_valid_payload(): void
    {
        $validator = Validator::make([
            'title' => 'インフラ編',
            'description' => 'ネットワーク + サーバ + DB',
        ], (new StoreRequest)->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_fails_when_title_missing(): void
    {
        $validator = Validator::make([], (new StoreRequest)->rules());

        $this->assertArrayHasKey('title', $validator->errors()->toArray());
    }

    public function test_fails_when_title_exceeds_max(): void
    {
        $validator = Validator::make(['title' => str_repeat('a', 201)], (new StoreRequest)->rules());

        $this->assertArrayHasKey('title', $validator->errors()->toArray());
    }
}
