<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\User;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Api\UserController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 運用エクスポート API `/api/v1/admin/users` の入力検証。
 *
 * ロール / ステータス (`withdrawn` 不可、`invited` / `in_progress` / `graduated` の 3 値のみ受け付け) /
 * ページネーション (1〜500) を受ける。認可は ApiKeyMiddleware で完結するため `authorize()` は常に true。
 *
 * @see UserController::index()
 */
class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'status' => ['nullable', Rule::in([
                UserStatus::Invited->value,
                UserStatus::InProgress->value,
                UserStatus::Graduated->value,
            ])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => '指定したステータスは取得対象外です。',
            'per_page.max' => '1 回のリクエストで取得できる件数は 500 件までです。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'role' => 'ロール',
            'status' => 'ステータス',
            'per_page' => '1 ページあたり件数',
            'page' => 'ページ番号',
        ];
    }
}
