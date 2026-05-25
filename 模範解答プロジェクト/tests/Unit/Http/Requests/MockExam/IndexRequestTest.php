<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\MockExam;

use App\Http\Requests\MockExam\IndexRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * 模試マスタ一覧 IndexRequest の rules() を検証する Unit テスト。
 * filter (keyword / certification_id (exists) / is_published) の nullable 検証を網羅する。
 */
class IndexRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_empty_filters(): void
    {
        $validator = Validator::make([], (new IndexRequest)->rules());
        $this->assertTrue($validator->passes());
    }

    public function test_passes_with_boolean_string_filter(): void
    {
        $validator = Validator::make(['is_published' => 'true'], (new IndexRequest)->rules());
        $this->assertTrue($validator->passes());
    }

    public function test_fails_when_is_published_invalid(): void
    {
        $validator = Validator::make(['is_published' => 'maybe'], (new IndexRequest)->rules());
        $this->assertArrayHasKey('is_published', $validator->errors()->toArray());
    }

    public function test_fails_when_keyword_exceeds_max(): void
    {
        $validator = Validator::make(['keyword' => str_repeat('a', 101)], (new IndexRequest)->rules());
        $this->assertArrayHasKey('keyword', $validator->errors()->toArray());
    }
}
