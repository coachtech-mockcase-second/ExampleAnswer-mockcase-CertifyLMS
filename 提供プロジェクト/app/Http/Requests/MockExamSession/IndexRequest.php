<?php

declare(strict_types=1);

namespace App\Http\Requests\MockExamSession;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 受講生の模試受験履歴フィルタ。
 */
class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'certification_id' => ['nullable', 'ulid', 'exists:certifications,id'],
            'mock_exam_id' => ['nullable', 'ulid', 'exists:mock_exams,id'],
            'pass' => ['nullable', 'in:0,1,true,false'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'certification_id' => '資格',
            'mock_exam_id' => '模試',
            'pass' => '合否',
        ];
    }
}
