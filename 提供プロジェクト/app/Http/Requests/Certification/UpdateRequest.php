<?php

declare(strict_types=1);

namespace App\Http\Requests\Certification;

use App\Enums\CertificationDifficulty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 資格マスタ更新リクエスト。admin が資格名・カテゴリ・難易度・説明の 4 項目のみ更新できる。
 * `status` は公開状態遷移用エンドポイント（publish / unpublish / archive）から別途行う。
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('certification')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'category_id' => ['required', 'ulid', 'exists:certification_categories,id'],
            'difficulty' => ['required', Rule::enum(CertificationDifficulty::class)],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '資格名',
            'category_id' => 'カテゴリ',
            'difficulty' => '難易度',
            'description' => '説明',
        ];
    }
}
