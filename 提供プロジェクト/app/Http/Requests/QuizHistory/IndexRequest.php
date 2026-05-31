<?php

declare(strict_types=1);

namespace App\Http\Requests\QuizHistory;

use App\Enums\AnswerSource;
use App\Models\Enrollment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 解答履歴一覧のフィルタリクエスト。
 *
 * 認可: Enrollment 閲覧 Policy (本人 / 担当コーチ / admin) に委譲。
 * 本 Controller は student 専用のため、Policy で本人のみ通過する想定。
 */
class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $enrollment = $this->route('enrollment');
        if (! $enrollment instanceof Enrollment) {
            return false;
        }

        return $this->user()?->can('view', $enrollment) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'section_id' => ['nullable', 'ulid', Rule::exists('sections', 'id')],
            'category_id' => ['nullable', 'ulid', Rule::exists('question_categories', 'id')],
            'is_correct' => ['nullable', 'boolean'],
            'source' => ['nullable', Rule::enum(AnswerSource::class)],
        ];
    }

    /**
     * @return array<string, ?bool>
     */
    public function filters(): array
    {
        return [
            'section_id' => $this->input('section_id'),
            'category_id' => $this->input('category_id'),
            'is_correct' => $this->has('is_correct') ? $this->boolean('is_correct') : null,
            'source' => $this->input('source'),
        ];
    }
}
