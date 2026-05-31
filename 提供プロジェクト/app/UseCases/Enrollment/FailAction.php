<?php

declare(strict_types=1);

namespace App\UseCases\Enrollment;

use App\Enums\EnrollmentStatus;
use App\Exceptions\Enrollment\EnrollmentAlreadyPassedException;
use App\Exceptions\Enrollment\EnrollmentInvalidTransitionException;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\DefaultEnrollmentService;
use App\Services\EnrollmentStatusChangeService;
use Illuminate\Support\Facades\DB;

/**
 * admin が Enrollment を学習中止(failed) に手動更新する Action。
 *
 * - status=passed の Enrollment は 409 で拒否
 * - status=learning 以外は 409(allowed: learning → failed のみ)
 * - 状態更新と EnrollmentStatusLog(from=learning / to=failed / changed_by=admin / reason=admin 入力値) を原子的に
 * - 当該 Enrollment が受講生のデフォルト資格だった場合は、他の learning|passed 残存件数で自動振替 / NULL リセット
 */
final class FailAction
{
    public function __construct(
        private readonly EnrollmentStatusChangeService $statusChanger,
        private readonly DefaultEnrollmentService $defaultEnrollmentService,
    ) {}

    /**
     * @throws EnrollmentAlreadyPassedException
     * @throws EnrollmentInvalidTransitionException
     */
    public function __invoke(Enrollment $enrollment, User $admin, ?string $reason): Enrollment
    {
        if ($enrollment->status === EnrollmentStatus::Passed) {
            throw new EnrollmentAlreadyPassedException;
        }

        if ($enrollment->status !== EnrollmentStatus::Learning) {
            throw EnrollmentInvalidTransitionException::forFail();
        }

        return DB::transaction(function () use ($enrollment, $admin, $reason) {
            $enrollment->update(['status' => EnrollmentStatus::Failed->value]);

            $this->statusChanger->recordStatusChange(
                $enrollment,
                fromStatus: EnrollmentStatus::Learning,
                toStatus: EnrollmentStatus::Failed,
                changedBy: $admin,
                reason: $reason ?? 'admin による学習中止',
            );

            $this->defaultEnrollmentService->resolveAfterStatusChange(
                $enrollment->user,
                $enrollment,
            );

            return $enrollment->refresh();
        });
    }
}
