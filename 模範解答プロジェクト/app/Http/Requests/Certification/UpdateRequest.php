<?php

namespace App\Http\Requests\Certification;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('certification')) ?? false;
    }

    public function rules(): array
    {
        $certificationId = $this->route('certification')?->id;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('certifications', 'code')->ignore($certificationId),
            ],
            'category_id' => ['required', 'ulid', 'exists:certification_categories,id'],
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required',
                'string',
                'max:120',
                Rule::unique('certifications', 'slug')->ignore($certificationId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'difficulty' => ['required', 'in:beginner,intermediate,advanced,expert'],
            'passing_score' => ['required', 'integer', 'min:1', 'max:100'],
            'total_questions' => ['required', 'integer', 'min:1'],
            'exam_duration_minutes' => ['required', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => '資格コード',
            'category_id' => 'カテゴリ',
            'name' => '資格名',
            'slug' => 'スラッグ',
            'description' => '説明',
            'difficulty' => '難易度',
            'passing_score' => '合格点',
            'total_questions' => '総問題数',
            'exam_duration_minutes' => '試験時間（分）',
        ];
    }
}
