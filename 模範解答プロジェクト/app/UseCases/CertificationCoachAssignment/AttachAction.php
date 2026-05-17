<?php

declare(strict_types=1);

namespace App\UseCases\CertificationCoachAssignment;

use App\Enums\UserRole;
use App\Events\CertificationCoachAttached;
use App\Exceptions\Certification\NotCoachUserException;
use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 担当コーチを資格に割当するユースケース。
 *
 * 業務分岐:
 * - 指定ユーザーがコーチロール以外: NotCoachUserException（422）
 * - 既に active な担当行が存在: 何もしない（冪等）+ イベント発火なし
 * - 過去に解除されて soft delete されている: restore + `unassigned_at` をリセット + イベント発火
 * - 新規割当: INSERT + イベント発火
 *
 * 同時呼出による UNIQUE 違反は冪等扱いとして catch し、イベントを発火しない。
 */
final class AttachAction
{
    /**
     * @throws NotCoachUserException 指定ユーザーがコーチロールではない
     */
    public function __invoke(Certification $certification, User $coach, User $admin): void
    {
        if ($coach->role !== UserRole::Coach) {
            throw new NotCoachUserException;
        }

        $changed = DB::transaction(function () use ($certification, $coach, $admin) {
            $existing = CertificationCoachAssignment::withTrashed()
                ->where('certification_id', $certification->id)
                ->where('user_id', $coach->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->trashed() || $existing->unassigned_at !== null) {
                    $existing->restore();
                    $existing->update([
                        'assigned_by_user_id' => $admin->id,
                        'assigned_at' => now(),
                        'unassigned_at' => null,
                    ]);

                    return true;
                }

                return false;
            }

            try {
                CertificationCoachAssignment::create([
                    'id' => (string) Str::ulid(),
                    'certification_id' => $certification->id,
                    'user_id' => $coach->id,
                    'assigned_by_user_id' => $admin->id,
                    'assigned_at' => now(),
                ]);
            } catch (QueryException $e) {
                // 同時 attach での UNIQUE 衝突は冪等として吸収。500 を返さず通常終了に倒す
                return false;
            }

            return true;
        });

        if ($changed) {
            CertificationCoachAttached::dispatch($certification, $coach, $admin);
        }
    }
}
