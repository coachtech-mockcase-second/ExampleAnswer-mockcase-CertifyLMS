<?php

declare(strict_types=1);

namespace App\UseCases\CertificationCoachAssignment;

use App\Events\CertificationCoachDetached;
use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 担当コーチを資格から解除するユースケース。
 *
 * 該当 active 割当行に `unassigned_at = now()` を設定する(行は履歴として保持)。
 * `Certification::coaches()` の `wherePivot('unassigned_at', null)` で取得対象外となる。
 *
 * 解除成功時に CertificationCoachDetached イベントを発火する。割当が存在しない場合は no-op。
 */
final class DetachAction
{
    public function __invoke(Certification $certification, User $coach, User $admin): void
    {
        $detached = DB::transaction(function () use ($certification, $coach) {
            $assignment = CertificationCoachAssignment::query()
                ->where('certification_id', $certification->id)
                ->where('user_id', $coach->id)
                ->whereNull('unassigned_at')
                ->first();

            if ($assignment === null) {
                return false;
            }

            $assignment->update(['unassigned_at' => now()]);

            return true;
        });

        if ($detached) {
            CertificationCoachDetached::dispatch($certification, $coach, $admin);
        }
    }
}
