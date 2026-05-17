<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserStatusLog;

/**
 * ユーザーステータス遷移の監査ログ(`UserStatusLog`)を INSERT する Service。
 *
 * 呼出側は record() を「`User.status` がまだ遷移前(from) の状態」で呼ぶ契約を守る必要がある。
 * 呼出時の `$user->status` を `from_status`、引数の `$newStatus` を `to_status` として記録する。
 *
 * `User.status` の UPDATE 自体は呼出側 Action の責務。本 Service は INSERT のみで、`DB::transaction()` は
 * 持たない(呼出側で囲んで原子性を担保する)。
 *
 * `final` 不採用: `WithdrawActionTest::test_transaction_rolls_back_on_status_log_failure` で
 * `Mockery::mock(UserStatusChangeService::class)` を使ってトランザクション原子性の rollback 検証を行うため。
 */
class UserStatusChangeService
{
    /**
     * @param  User  $user  ステータス遷移する対象 User(呼出時に `$user->status` が遷移前の値である必要がある)
     * @param  UserStatus  $newStatus  遷移後ステータス(`to_status` として記録される)
     * @param  ?User  $changedBy  操作者。null はシステム自動変更(Schedule Command 等)
     * @param  ?string  $reason  変更理由(任意、UI 表示用)
     */
    public function record(
        User $user,
        UserStatus $newStatus,
        ?User $changedBy,
        ?string $reason = null,
    ): UserStatusLog {
        return $user->statusLogs()->create([
            'from_status' => $user->status->value,
            'to_status' => $newStatus->value,
            'changed_by_user_id' => $changedBy?->id,
            'changed_reason' => $reason,
            'changed_at' => now(),
        ]);
    }
}
