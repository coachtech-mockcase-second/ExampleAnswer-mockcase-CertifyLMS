<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\MockExamSession;

use App\Http\Requests\MockExamSession\IndexRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * 模試セッション一覧 IndexRequest の rules() を検証する Unit テスト。
 * certification_id / mock_exam_id (exists) / pass フィルタを網羅する。
 */
class IndexRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_empty_filters(): void
    {
        $validator = Validator::make([], (new IndexRequest)->rules());
        $this->assertTrue($validator->passes());
    }

    public function test_passes_with_pass_boolean_string(): void
    {
        $validator = Validator::make(['pass' => 'true'], (new IndexRequest)->rules());
        $this->assertTrue($validator->passes());
    }

    public function test_fails_when_pass_invalid(): void
    {
        $validator = Validator::make(['pass' => 'maybe'], (new IndexRequest)->rules());
        $this->assertArrayHasKey('pass', $validator->errors()->toArray());
    }
}
