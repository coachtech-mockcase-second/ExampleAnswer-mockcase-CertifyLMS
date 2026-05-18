<?php

declare(strict_types=1);

namespace App\Http\Requests\QuizStats;

use App\Models\Enrollment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * SectionQuestion 累計サマリ一覧のフィルタリクエスト。
 *
 * 認可: Enrollment 閲覧 Policy に委譲(本 Controller は student 専用)。
 * sort 候補: recent / accuracy_asc / accuracy_desc / attempts_desc(QuizStats\IndexAction::applySort 参照)。
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
            'last_is_correct' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', Rule::in(['recent', 'accuracy_asc', 'accuracy_desc', 'attempts_desc'])],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return [
            'section_id' => $this->input('section_id'),
            'category_id' => $this->input('category_id'),
            'last_is_correct' => $this->has('last_is_correct') ? $this->boolean('last_is_correct') : null,
            'sort' => $this->input('sort'),
        ];
    }
}
