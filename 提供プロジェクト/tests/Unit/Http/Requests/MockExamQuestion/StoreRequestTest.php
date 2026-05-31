<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\MockExamQuestion;

use App\Http\Requests\MockExamQuestion\StoreRequest;
use App\Models\QuestionCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * 模試問題マスタ新規作成 StoreRequest の rules() を検証する Unit テスト。
 * options 配列 (min:2 / max:6) + ネストルール (body / is_correct / order) を Validator::make で網羅する。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_valid_payload(): void
    {
        // Arrange
        $category = QuestionCategory::factory()->create();
        $payload = [
            'body' => 'A サブネット内で IPv4 アドレスとして使用できないものは?',
            'category_id' => $category->id,
            'options' => [
                ['body' => 'ホストアドレス', 'is_correct' => false, 'order' => 0],
                ['body' => 'ブロードキャストアドレス', 'is_correct' => true, 'order' => 1],
            ],
        ];

        // Act
        $validator = Validator::make($payload, (new StoreRequest)->rules());

        // Assert
        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
    }

    public function test_fails_when_body_missing(): void
    {
        // Arrange
        $category = QuestionCategory::factory()->create();
        $payload = ['category_id' => $category->id, 'options' => [
            ['body' => 'A', 'is_correct' => true, 'order' => 0],
            ['body' => 'B', 'is_correct' => false, 'order' => 1],
        ]];

        // Act
        $validator = Validator::make($payload, (new StoreRequest)->rules());

        // Assert
        $this->assertArrayHasKey('body', $validator->errors()->toArray());
    }

    public function test_fails_when_options_less_than_two(): void
    {
        // Arrange
        $category = QuestionCategory::factory()->create();
        $payload = [
            'body' => '問題文',
            'category_id' => $category->id,
            'options' => [['body' => '唯一', 'is_correct' => true, 'order' => 0]],
        ];

        // Act
        $validator = Validator::make($payload, (new StoreRequest)->rules());

        // Assert
        $this->assertArrayHasKey('options', $validator->errors()->toArray());
    }

    public function test_fails_when_options_exceed_six(): void
    {
        // Arrange
        $category = QuestionCategory::factory()->create();
        $options = [];
        for ($i = 0; $i < 7; $i++) {
            $options[] = ['body' => "選択肢 {$i}", 'is_correct' => $i === 0, 'order' => $i];
        }
        $payload = ['body' => '問題文', 'category_id' => $category->id, 'options' => $options];

        // Act
        $validator = Validator::make($payload, (new StoreRequest)->rules());

        // Assert
        $this->assertArrayHasKey('options', $validator->errors()->toArray());
    }
}
