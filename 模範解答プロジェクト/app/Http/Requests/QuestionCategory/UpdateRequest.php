<?php

declare(strict_types=1);

namespace App\Http\Requests\QuestionCategory;

use App\Models\QuestionCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 出題分野マスタ(QuestionCategory) の更新リクエスト。slug の資格内 UNIQUE 制約は自身を除外して確認する。
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category instanceof QuestionCategory
            && ($this->user()?->can('update', $category) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
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
                    ->where(fn ($q) => $q->where('certification_id', $category?->certification_id)),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '出題分野名',
            'slug' => 'スラッグ',
            'sort_order' => '表示順',
            'description' => '説明',
        ];
    }
}
