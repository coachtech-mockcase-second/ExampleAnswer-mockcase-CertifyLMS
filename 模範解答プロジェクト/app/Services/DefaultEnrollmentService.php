<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\User;

/**
 * 受講生のデフォルト資格(users.default_enrollment_id)の自動設定 / 自動振替 / NULL リセットを担う Service。
 *
 * 呼出元:
 * - 受講登録の新規作成 Action → resolveAfterCreate(初回 Enrollment のみ default にセット、2 件目以降は既存 default 保持)
 * - 学習中止 / 受講解除 / 自動失敗 Schedule Command → resolveAfterStatusChange
 *   (default 自身が変化した場合、残存 learning|passed が 1 件なら振替、それ以外は NULL リセット)
 * - ResolveDefaultEnrollment Middleware → clearIfInvalid(default 参照先が SoftDelete / failed なら NULL リセット)
 *
 * 呼出側 Action がトランザクション内で本 Service を呼ぶ前提。本 Service 自体は DB::transaction() を持たない。
 *
 * `final` 不採用: 呼出側 Action のテストで Mockery で mock したいケース(呼出回数・引数検証 /
 * トランザクション原子性 rollback 検証)に備える(UserStatusChangeService / EnrollmentStatusChangeService と同じ判断軸)。
 */
final class DefaultEnrollmentService
{
    /**
     * 新規 Enrollment 作成直後に呼ばれる。default が NULL の場合のみ、その Enrollment を default にセット。
     * 既に default がセット済(2 件目以降) の場合は何もしない。
     */
    public function resolveAfterCreate(User $user, Enrollment $newEnrollment): void
    {
        if ($user->default_enrollment_id !== null) {
            return;
        }

        $user->update(['default_enrollment_id' => $newEnrollment->id]);
    }

    /**
     * Enrollment の status 変化(failed 遷移) or SoftDelete 時に呼ばれる。
     * 当該 Enrollment が現在 default だった場合のみ、他の learning|passed 残存件数で振分:
     *
     * - 残存 ちょうど 1 件 → 自動振替(default を新 ID へ UPDATE)
     * - 残存 2 件以上 or 0 件 → NULL リセット
     *
     * default でなかった場合は何もしない。
     */
    public function resolveAfterStatusChange(User $user, Enrollment $changedEnrollment): void
    {
        if ($user->default_enrollment_id !== $changedEnrollment->id) {
            return;
        }

        $remaining = Enrollment::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [EnrollmentStatus::Learning->value, EnrollmentStatus::Passed->value])
            ->where('id', '!=', $changedEnrollment->id)
            ->get();

        $newDefaultId = $remaining->count() === 1 ? $remaining->first()->id : null;

        $user->update(['default_enrollment_id' => $newDefaultId]);
    }

    /**
     * Middleware が default の有効性検証時に呼ぶ。
     * 参照先 Enrollment が見つからない / SoftDelete 済 / status = failed のいずれかなら NULL リセット。
     */
    public function clearIfInvalid(User $user): void
    {
        if ($user->default_enrollment_id === null) {
            return;
        }

        $default = Enrollment::query()
            ->withTrashed()
            ->find($user->default_enrollment_id);

        $invalid = $default === null
            || $default->trashed()
            || $default->status === EnrollmentStatus::Failed;

        if ($invalid) {
            $user->update(['default_enrollment_id' => null]);
        }
    }
}
