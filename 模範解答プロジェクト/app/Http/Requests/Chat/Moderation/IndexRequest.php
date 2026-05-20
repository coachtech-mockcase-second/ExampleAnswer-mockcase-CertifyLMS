<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat\Moderation;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 管理者向け ChatRoom 一覧検索の入力検証。
 *
 * 受講生名 / コーチ名 / 資格 ID で横断検索可能。
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
            'certification_id' => ['nullable', 'ulid', 'exists:certifications,id'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{certification_id: ?string, keyword: ?string}
     */
    public function filters(): array
    {
        return [
            'certification_id' => $this->input('certification_id'),
            'keyword' => $this->input('keyword'),
        ];
    }
}
