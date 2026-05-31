<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\SectionQuestion;

use App\Http\Requests\SectionQuestion\StoreRequest;
use App\Models\QuestionCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * 問題マスタ新規作成 StoreRequest の rules() を検証する Unit テスト。
 * body + options 配列 (required + min:2 + max:6) ネストルールを Validator::make で網羅する。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_valid_payload(): void
    {
        $category = QuestionCategory::factory()->create();
        $validator = Validator::make([
            'body' => '問題文',
            'category_id' => $category->id,
            'options' => [
                ['body' => 'A', 'is_correct' => true, 'order' => 0],
                ['body' => 'B', 'is_correct' => false, 'order' => 1],
            ],
        ], (new StoreRequest)->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_fails_when_options_missing(): void
    {
        $category = QuestionCategory::factory()->create();
        $validator = Validator::make([
            'body' => '問題文',
            'category_id' => $category->id,
        ], (new StoreRequest)->rules());

        $this->assertArrayHasKey('options', $validator->errors()->toArray());
    }
}
