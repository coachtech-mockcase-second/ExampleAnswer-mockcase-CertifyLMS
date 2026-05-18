<?php

declare(strict_types=1);

namespace App\Http\Requests\SectionQuestionAnswer;

use App\Enums\AnswerSource;
use App\Models\SectionQuestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Section 経路 / 苦手分野ドリル経路の解答送信リクエスト。
 *
 * source 値で必須項目が分岐する:
 *   - section_quiz: section_id が必要(結果画面リダイレクトのキー)
 *   - weak_drill: enrollment_id + question_category_id が必要
 *
 * 認可は `quiz.answer.create` Gate に委譲(本人 / Student / InProgress / Enrollment learning|passed / cascade visibility)。
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $question = $this->route('question');
        if (! $question instanceof SectionQuestion) {
            return false;
        }

        return $this->user()?->can('quiz.answer.create', $question) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $question = $this->route('question');
        $questionId = $question instanceof SectionQuestion ? $question->id : null;

        return [
            'selected_option_id' => [
                'required',
                'ulid',
                Rule::exists('section_question_options', 'id')
                    ->where('section_question_id', $questionId),
            ],
            'source' => ['required', Rule::enum(AnswerSource::class)],
            'section_id' => [
                Rule::requiredIf(fn () => $this->input('source') === AnswerSource::SectionQuiz->value),
                'nullable',
                'ulid',
                Rule::exists('sections', 'id')->whereNull('deleted_at'),
            ],
            'enrollment_id' => [
                Rule::requiredIf(fn () => $this->input('source') === AnswerSource::WeakDrill->value),
                'nullable',
                'ulid',
                Rule::exists('enrollments', 'id')
                    ->where('user_id', $this->user()?->id)
                    ->whereNull('deleted_at'),
            ],
            'question_category_id' => [
                Rule::requiredIf(fn () => $this->input('source') === AnswerSource::WeakDrill->value),
                'nullable',
                'ulid',
                Rule::exists('question_categories', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'selected_option_id' => '選択肢',
            'source' => '出題経路',
            'section_id' => 'Section',
            'enrollment_id' => '受講登録',
            'question_category_id' => '出題分野',
        ];
    }
}
