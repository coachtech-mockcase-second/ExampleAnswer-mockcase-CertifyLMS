<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Enums\UserRole;
use App\Models\Invitation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * オンボーディングフォーム(初回パスワード設定 + プロフィール入力)のリクエスト。
 *
 * 認可は署名付き URL に委ねるため `authorize()` は常に true。
 * コーチ宛て招待の場合は固定面談 URL(`meeting_url`)の入力を必須化し、受講生宛てでは表示しない。
 */
class OnboardingRequest extends FormRequest
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
        $rules = [
            'name' => ['required', 'string', 'max:50'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'password' => ['required', 'string', 'min:8'],
        ];

        if ($this->invitedRole() === UserRole::Coach) {
            $rules['meeting_url'] = ['required', 'string', 'url', 'max:500'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'お名前',
            'bio' => '自己紹介',
            'password' => 'パスワード',
            'password_confirmation' => 'パスワード（確認）',
            'meeting_url' => 'ミーティング URL',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'meeting_url.required' => 'ミーティング URL を入力してください。',
            'meeting_url.url' => 'ミーティング URL を正しい形式で入力してください。',
        ];
    }

    /**
     * 招待トークンに紐付くロールを取り出す。Route Model Binding 経由で Invitation を解決する。
     */
    private function invitedRole(): ?UserRole
    {
        $invitation = $this->route('invitation');

        return $invitation instanceof Invitation ? $invitation->role : null;
    }
}
