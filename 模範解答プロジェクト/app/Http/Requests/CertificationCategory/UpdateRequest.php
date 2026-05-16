<?php

declare(strict_types=1);

namespace App\Http\Requests\CertificationCategory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('category')) ?? false;
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')?->id;

        return [
            'name' => ['required', 'string', 'max:50'],
            'slug' => [
                'required',
                'string',
                'max:60',
                Rule::unique('certification_categories', 'slug')->ignore($categoryId),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => '分類名',
            'slug' => 'スラッグ',
            'sort_order' => '表示順',
        ];
    }
}
