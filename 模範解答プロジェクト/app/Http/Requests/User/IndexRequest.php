<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 管理者ユーザー一覧 (`GET /admin/users`) の検索条件を受け取る FormRequest。
 *
 * ロール / ステータス(4 値) / キーワード(name / email 部分一致) / ページネーション。
 */
class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', User::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'in:admin,coach,student'],
            'status' => ['nullable', Rule::enum(UserStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'keyword' => '検索キーワード',
            'role' => 'ロール',
            'status' => 'ステータス',
        ];
    }
}
