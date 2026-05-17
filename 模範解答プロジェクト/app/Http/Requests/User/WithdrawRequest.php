<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 管理者操作の強制退会 (`POST /admin/users/{user}/withdraw`) の認可ラッパー。
 *
 * 退会理由は管理者画面ではユーザー入力させず、Action 側で「管理者による退会」を固定記録する設計。
 */
class WithdrawRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('withdraw', $this->route('user'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
