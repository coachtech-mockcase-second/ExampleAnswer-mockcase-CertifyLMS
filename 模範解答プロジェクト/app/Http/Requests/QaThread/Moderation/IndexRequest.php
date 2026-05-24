<?php

declare(strict_types=1);

namespace App\Http\Requests\QaThread\Moderation;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * admin モデレーション用の質問掲示板一覧フィルタ FormRequest。
 *
 * 公開側 IndexRequest との違い: `authorize()` での admin ロール検証。
 */
class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'certification_id' => ['nullable', 'ulid'],
            'status' => ['nullable', Rule::in(['resolved', 'unresolved'])],
            'keyword' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{certification_id: ?string, status: ?string, keyword: ?string}
     */
    public function filters(): array
    {
        return [
            'certification_id' => $this->input('certification_id'),
            'status' => $this->input('status'),
            'keyword' => $this->input('keyword'),
        ];
    }
}
