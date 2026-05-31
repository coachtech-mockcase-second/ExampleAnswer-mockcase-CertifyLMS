<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\EnrollmentStatusLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Enrollment 状態遷移の監査ログ(`EnrollmentStatusLog`)を INSERT する Service。
 *
 * 呼出側 Action がトランザクション内で recordStatusChange() を呼ぶ前提。本 Service 自体は
 * DB::transaction() を持たない(`backend-services.md` の規約準拠、ステートレス INSERT only)。
 *
 * 受講状態の遷移は管理者ダッシュボードの集計(全体 KPI / 資格別修了率)を変えるため、状態遷移の
 * 記録時に該当集計キャッシュを無効化し、次回ダッシュボード表示で最新値を再計算させる。
 *
 * `final` 不採用: Mockery で recordStatusChange を mock してトランザクション原子性の rollback 検証を
 * Action テストで行う可能性があるため(`UserStatusChangeService` と同じ判断軸)。
 */
final class EnrollmentStatusChangeService
{
    /**
     * @param Enrollment $enrollment 状態遷移する対象 Enrollment
     * @param ?EnrollmentStatus $fromStatus 遷移前ステータス(初回登録時のみ null、それ以降は必須)
     * @param EnrollmentStatus $toStatus 遷移後ステータス
     * @param ?User $changedBy 操作者(null はシステム自動 = Schedule Command 等)
     * @param ?string $reason 変更理由(任意、UI 表示用)
     */
    public function recordStatusChange(
        Enrollment $enrollment,
        ?EnrollmentStatus $fromStatus,
        EnrollmentStatus $toStatus,
        ?User $changedBy,
        ?string $reason = null,
    ): EnrollmentStatusLog {
        // 受講状態の遷移で管理者ダッシュボードの集計値(全体 KPI / 資格別修了率)が変わるため、
        // 集計キャッシュを無効化して次回表示で最新値を再計算させる。
        Cache::forget(config('dashboard.admin_kpi_cache_key'));
        Cache::forget(config('dashboard.admin_completion_rate_cache_key'));

        return $enrollment->statusLogs()->create([
            'from_status' => $fromStatus?->value,
            'to_status' => $toStatus->value,
            'changed_by_user_id' => $changedBy?->id,
            'changed_reason' => $reason,
            'changed_at' => now(),
        ]);
    }
}
