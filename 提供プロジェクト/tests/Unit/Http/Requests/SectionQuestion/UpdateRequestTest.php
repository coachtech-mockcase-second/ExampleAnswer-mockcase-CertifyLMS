<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\SectionQuestion;

use App\Models\QuestionCategory;
use App\Models\SectionQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 問題マスタ更新 UpdateRequest のバリデーション検証。
 * body / category_id / options 配列 (sometimes + min:2 + max:6) のネストルールを valid + invalid で網羅する。
 */
class UpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_without_options(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $question = SectionQuestion::factory()->published()->create();
        $category = QuestionCategory::factory()->create();

        // Act
        $response = $this->actingAs($admin)->patch(route('admin.section-questions.update', $question), [
            'body' => '更新後の問題文',
            'category_id' => $category->id,
        ]);

        // Assert
        $response->assertSessionDoesntHaveErrors();
    }

    public function test_validation_passes_with_two_options(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $question = SectionQuestion::factory()->published()->create();
        $category = QuestionCategory::factory()->create();

        // Act
        $response = $this->actingAs($admin)->patch(route('admin.section-questions.update', $question), [
            'body' => '更新後の問題文',
            'category_id' => $category->id,
            'options' => [
                ['body' => '選択肢 A', 'is_correct' => true, 'order' => 0],
                ['body' => '選択肢 B', 'is_correct' => false, 'order' => 1],
            ],
        ]);

        // Assert
        $response->assertSessionDoesntHaveErrors();
    }

    public function test_body_required_validation(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $question = SectionQuestion::factory()->published()->create();
        $category = QuestionCategory::factory()->create();

        // Act
        $response = $this->actingAs($admin)->patchJson(route('admin.section-questions.update', $question), [
            'body' => '',
            'category_id' => $category->id,
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('body');
    }

    public function test_options_less_than_two_fails_min_rule(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $question = SectionQuestion::factory()->published()->create();
        $category = QuestionCategory::factory()->create();

        // Act
        $response = $this->actingAs($admin)->patchJson(route('admin.section-questions.update', $question), [
            'body' => '問題文',
            'category_id' => $category->id,
            'options' => [
                ['body' => '選択肢 A', 'is_correct' => true, 'order' => 0],
            ],
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('options');
    }

    public function test_options_exceeding_six_fails_max_rule(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $question = SectionQuestion::factory()->published()->create();
        $category = QuestionCategory::factory()->create();
        $options = [];
        for ($i = 0; $i < 7; $i++) {
            $options[] = ['body' => "選択肢 {$i}", 'is_correct' => $i === 0, 'order' => $i];
        }

        // Act
        $response = $this->actingAs($admin)->patchJson(route('admin.section-questions.update', $question), [
            'body' => '問題文',
            'category_id' => $category->id,
            'options' => $options,
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('options');
    }
}
