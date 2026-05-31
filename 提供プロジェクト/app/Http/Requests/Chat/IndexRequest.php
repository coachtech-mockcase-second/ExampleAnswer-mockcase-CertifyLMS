<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

/**
 * ChatRoom 一覧アクセスの入力検証。受講生 / コーチ / admin 共通で利用される。
 *
 * - 受講生 / コーチ: 参加ルームの最新へ redirect (フィルタなし)
 * - admin: 全 ChatRoom 横断、`certification_id` / `keyword` でフィルタ可
 */
class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->user()?->role;

        return $role === UserRole::Student
            || $role === UserRole::Coach
            || $role === UserRole::Admin;
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
