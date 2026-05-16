<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserStatusLog;

class UserStatusChangeService
{
    /**
     * ステータス変更ログを INSERT する。状態更新は呼出側の責務（本 Service は INSERT のみ）。
     *
     * @param User $user ステータスが変更された対象 User（呼出側が既に status 更新済の前提）
     * @param UserStatus $newStatus 遷移後のステータス
     * @param ?User $changedBy 操作者。null はシステム自動変更（Schedule Command 等）
     * @param ?string $reason 変更理由（200 文字以内）
     */
    public function record(
        User $user,
        UserStatus $newStatus,
        ?User $changedBy,
        ?string $reason = null,
    ): UserStatusLog {
        return $user->statusLogs()->create([
            'changed_by_user_id' => $changedBy?->id,
            'status' => $newStatus->value,
            'changed_at' => now(),
            'changed_reason' => $reason,
        ]);
    }
}
