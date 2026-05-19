<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 運用エクスポート API `/api/v1/admin/users` のレスポンス整形。
 *
 * 認証 / プロフィール / メッセージング系のセンシティブ列 (`password` / `remember_token` / `bio` /
 * `avatar_url` / `profile_setup_completed` / `email_verified_at` / `meeting_url` / `default_enrollment_id`)
 * は絶対に含めない。`max_meetings` を含む Plan 関連カラムは GAS 側で稼働状況可視化に使う。
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'status' => $this->status->value,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'plan_id' => $this->plan_id,
            'plan_started_at' => $this->plan_started_at?->toIso8601String(),
            'plan_expires_at' => $this->plan_expires_at?->toIso8601String(),
            'max_meetings' => $this->max_meetings,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
