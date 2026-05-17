<?php

declare(strict_types=1);

namespace App\Http\Requests\Enrollment;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin による Enrollment.exam_date 変更リクエスト。
 * status / current_term / passed_at は本リクエスト経路では更新しない(各々の専用 Action 経由のみとする)。
 */
class UpdateExamDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateExamDate', $this->route('enrollment')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'exam_date' => ['nullable', 'date', 'after:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'exam_date' => '目標受験日',
        ];
    }
}
