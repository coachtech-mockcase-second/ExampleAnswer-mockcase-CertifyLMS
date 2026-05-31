<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Chat;

use App\Http\Requests\Chat\IndexRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Chat 一覧 IndexRequest の rules() を検証する Unit テスト。
 * certification_id / keyword / page の nullable フィルタを網羅する。
 */
class IndexRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_empty_filters(): void
    {
        $validator = Validator::make([], (new IndexRequest)->rules());
        $this->assertTrue($validator->passes());
    }

    public function test_fails_when_certification_id_not_ulid(): void
    {
        $validator = Validator::make(['certification_id' => 'not-ulid'], (new IndexRequest)->rules());
        $this->assertArrayHasKey('certification_id', $validator->errors()->toArray());
    }

    public function test_fails_when_page_zero(): void
    {
        $validator = Validator::make(['page' => 0], (new IndexRequest)->rules());
        $this->assertArrayHasKey('page', $validator->errors()->toArray());
    }
}
