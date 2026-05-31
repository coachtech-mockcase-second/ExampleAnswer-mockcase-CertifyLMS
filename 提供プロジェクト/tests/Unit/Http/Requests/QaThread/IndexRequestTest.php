<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\QaThread;

use App\Http\Requests\QaThread\IndexRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * 質問掲示板スレッド一覧 IndexRequest の rules() を検証する Unit テスト。
 * filter (certification_id / status / keyword / page) の nullable 検証を網羅する。
 */
class IndexRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_empty_filters(): void
    {
        $validator = Validator::make([], (new IndexRequest)->rules());
        $this->assertTrue($validator->passes());
    }

    public function test_fails_when_status_invalid(): void
    {
        $validator = Validator::make(['status' => 'unknown'], (new IndexRequest)->rules());
        $this->assertArrayHasKey('status', $validator->errors()->toArray());
    }

    public function test_fails_when_page_zero(): void
    {
        $validator = Validator::make(['page' => 0], (new IndexRequest)->rules());
        $this->assertArrayHasKey('page', $validator->errors()->toArray());
    }
}
