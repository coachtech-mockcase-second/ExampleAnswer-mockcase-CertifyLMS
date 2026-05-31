<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Meeting;

use App\Http\Requests\Meeting\IndexRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * 面談一覧 IndexRequest の rules() を検証する Unit テスト。
 * filter (upcoming / past / all) の nullable in 検証を網羅する。
 */
class IndexRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_empty_filter(): void
    {
        $validator = Validator::make([], (new IndexRequest)->rules());
        $this->assertTrue($validator->passes());
    }

    public function test_passes_with_each_valid_filter(): void
    {
        foreach (['upcoming', 'past', 'all'] as $value) {
            $validator = Validator::make(['filter' => $value], (new IndexRequest)->rules());
            $this->assertTrue($validator->passes(), "filter={$value} は valid のはず");
        }
    }

    public function test_fails_when_filter_invalid_value(): void
    {
        $validator = Validator::make(['filter' => 'invalid'], (new IndexRequest)->rules());
        $this->assertArrayHasKey('filter', $validator->errors()->toArray());
    }
}
