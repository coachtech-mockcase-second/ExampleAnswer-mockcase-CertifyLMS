<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

/**
 * プロフィール設定画面で本人が自分自身を更新するリクエスト。
 *
 * `meeting_url` フィールドはフォーム上 coach のみ表示されるが、Controller / Action 側で
 * `role !== coach` の場合に silently drop するため、本 FormRequest では送信されていても
 * バリデーションだけ通す(エラーにしない)。
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('updateSelf', $user);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:50'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'meeting_url' => ['nullable', 'string', 'url', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '氏名',
            'bio' => '自己紹介',
            'meeting_url' => '固定面談 URL',
        ];
    }
}
