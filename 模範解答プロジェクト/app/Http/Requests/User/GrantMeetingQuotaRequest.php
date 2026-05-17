<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 管理者操作の面談回数手動付与 (`POST /admin/users/{user}/grant-meeting-quota`) の入力検証。
 *
 * 1 回の操作で付与できる面談回数は 1〜100 の整数。理由はトラブル補填 / キャンペーン付与等のメモ用途で任意。
 */
class GrantMeetingQuotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('grantMeetingQuota', $this->route('user'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1', 'max:100'],
            'reason' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'amount' => '付与する面談回数',
            'reason' => '付与理由',
        ];
    }
}
