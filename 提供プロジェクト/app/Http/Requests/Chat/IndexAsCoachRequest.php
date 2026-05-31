<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

/**
 * コーチ専用の ChatRoom 一覧フィルタ入力検証。
 *
 * filter=unread をデフォルトとし、資格 / 受講生キーワードで絞り込めるようにする。
 */
class IndexAsCoachRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Coach;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'string', 'in:unread,all'],
            'certification_id' => ['nullable', 'ulid', 'exists:certifications,id'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{filter: string, certification_id: ?string, keyword: ?string}
     */
    public function filters(): array
    {
        return [
            'filter' => $this->string('filter', 'all')->toString(),
            'certification_id' => $this->input('certification_id'),
            'keyword' => $this->input('keyword'),
        ];
    }
}
