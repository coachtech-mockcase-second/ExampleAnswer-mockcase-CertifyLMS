<?php

declare(strict_types=1);

namespace App\Http\Requests\QuestionCategory;

use App\Models\Certification;
use App\Models\QuestionCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 出題分野マスタ(QuestionCategory) の新規作成リクエスト。
 * slug は当該資格内で UNIQUE であることを確認する(別資格で同じ slug を許容する規約のため where 句で certification_id を絞る)。
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $certification = $this->route('certification');

        return $certification instanceof Certification
            && ($this->user()?->can('create', [QuestionCategory::class, $certification]) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
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
                    ->where(fn ($q) => $q->where('certification_id', $certification?->id)),
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
