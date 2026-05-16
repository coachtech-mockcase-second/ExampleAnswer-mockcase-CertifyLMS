<?php

declare(strict_types=1);

namespace App\Http\Requests\QuestionCategory;

use App\Models\Certification;
use App\Models\QuestionCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $certification = $this->route('certification');

        return $certification instanceof Certification
            && ($this->user()?->can('create', [QuestionCategory::class, $certification]) ?? false);
    }

    public function rules(): array
    {
        $certification = $this->route('certification');

        return [
            'name' => ['required', 'string', 'max:50'],
            'slug' => [
                'required',
                'string',
                'max:60',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('question_categories', 'slug')
                    ->where(fn ($q) => $q->where('certification_id', $certification?->id)->whereNull('deleted_at')),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'カテゴリ名',
            'slug' => 'スラッグ',
            'sort_order' => '表示順',
            'description' => '説明',
        ];
    }
}
