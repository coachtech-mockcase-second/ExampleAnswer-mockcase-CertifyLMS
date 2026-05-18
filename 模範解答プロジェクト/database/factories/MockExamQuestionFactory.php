<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Certification;
use App\Models\MockExam;
use App\Models\MockExamQuestion;
use App\Models\QuestionCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MockExamQuestion>
 */
class MockExamQuestionFactory extends Factory
{
    protected $model = MockExamQuestion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mockExamId = MockExam::factory();

        return [
            'mock_exam_id' => $mockExamId,
            'category_id' => function (array $attributes) {
                $mockExam = MockExam::find($attributes['mock_exam_id']);
                $certificationId = $mockExam?->certification_id
                    ?? Certification::factory()->create()->id;

                return QuestionCategory::factory()
                    ->state(['certification_id' => $certificationId])
                    ->create()
                    ->id;
            },
            'body' => fake()->sentence().' 何か?',
            'explanation' => fake()->paragraph(),
            'order' => 0,
        ];
    }

    public function forMockExam(MockExam $mockExam): static
    {
        return $this->state(fn () => ['mock_exam_id' => $mockExam->id]);
    }

    public function forCategory(QuestionCategory $category): static
    {
        return $this->state(fn () => ['category_id' => $category->id]);
    }

    /**
     * 問題作成後に指定数の選択肢を生成する。`$correctIndex` 位置の選択肢のみ is_correct=true。
     */
    public function withOptions(int $count = 4, int $correctIndex = 0): static
    {
        return $this->afterCreating(function (MockExamQuestion $question) use ($count, $correctIndex) {
            for ($i = 0; $i < $count; $i++) {
                $question->options()->create([
                    'body' => '選択肢 '.($i + 1),
                    'is_correct' => $i === $correctIndex,
                    'order' => $i,
                ]);
            }
        });
    }
}
