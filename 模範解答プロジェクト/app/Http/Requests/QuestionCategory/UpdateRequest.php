<?php

namespace App\Http\Requests\QuestionCategory;

use App\Models\QuestionCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category instanceof QuestionCategory
            && ($this->user()?->can('update', $category) ?? false);
    }

    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:50'],
            'slug' => [
                'required',
                'string',
                'max:60',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('question_categories', 'slug')
                    ->ignore($category?->id)
                    ->where(fn ($q) => $q->where('certification_id', $category?->certification_id)->whereNull('deleted_at')),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
