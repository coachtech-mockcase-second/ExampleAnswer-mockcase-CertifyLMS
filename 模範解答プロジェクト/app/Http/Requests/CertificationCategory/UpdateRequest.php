<?php

declare(strict_types=1);

namespace App\Http\Requests\CertificationCategory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 資格分類マスタ更新リクエスト。`slug` の UNIQUE は自身を除外する。
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('category')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
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

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '分類名',
            'slug' => 'スラッグ',
            'sort_order' => '表示順',
        ];
    }
}
