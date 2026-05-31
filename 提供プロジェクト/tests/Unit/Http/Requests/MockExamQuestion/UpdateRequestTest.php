<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\MockExamQuestion;

use App\Http\Requests\MockExamQuestion\UpdateRequest;
use App\Models\QuestionCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * 模試問題マスタ更新 UpdateRequest の rules() を検証する Unit テスト。
 * Store と同じネストルール構造を持つため、body / options の主要 invalid ケースを検証する。
 */
class UpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_valid_payload(): void
    {
        // Arrange
        $category = QuestionCategory::factory()->create();
        $payload = [
            'body' => '更新後の問題文',
            'category_id' => $category->id,
            'options' => [
                ['body' => 'A', 'is_correct' => true, 'order' => 0],
                ['body' => 'B', 'is_correct' => false, 'order' => 1],
            ],
        ];

        // Act
        $validator = Validator::make($payload, (new UpdateRequest)->rules());

        // Assert
        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
    }

    public function test_fails_when_body_exceeds_max_length(): void
    {
        // Arrange
        $category = QuestionCategory::factory()->create();
        $payload = [
            'body' => str_repeat('a', 5001),
            'category_id' => $category->id,
            'options' => [
                ['body' => 'A', 'is_correct' => true, 'order' => 0],
                ['body' => 'B', 'is_correct' => false, 'order' => 1],
            ],
        ];

        // Act
        $validator = Validator::make($payload, (new UpdateRequest)->rules());

        // Assert
        $this->assertArrayHasKey('body', $validator->errors()->toArray());
    }
}
