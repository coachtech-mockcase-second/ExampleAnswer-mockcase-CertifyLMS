<?php

declare(strict_types=1);

namespace App\UseCases\Enrollment;

use App\Enums\EnrollmentStatus;
use App\Exceptions\Enrollment\EnrollmentInvalidTransitionException;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\EnrollmentStatusChangeService;
use Illuminate\Support\Facades\DB;

/**
 * 学習中止(failed) → 学習中(learning) への再挑戦遷移 Action。受講生本人 or admin が実行する。
 *
 * allowed: failed → learning のみ。それ以外は 409。
 */
final class ResumeAction
{
    public function __construct(
        private readonly EnrollmentStatusChangeService $statusChanger,
    ) {}

    /**
     * @throws EnrollmentInvalidTransitionException
     */
    public function __invoke(Enrollment $enrollment, User $actor): Enrollment
    {
        if ($enrollment->status !== EnrollmentStatus::Failed) {
            throw EnrollmentInvalidTransitionException::forResume();
        }

        return DB::transaction(function () use ($enrollment, $actor) {
            $enrollment->update(['status' => EnrollmentStatus::Learning->value]);

            $this->statusChanger->recordStatusChange(
                $enrollment,
                fromStatus: EnrollmentStatus::Failed,
                toStatus: EnrollmentStatus::Learning,
                changedBy: $actor,
                reason: '再挑戦',
            );

            return $enrollment->refresh();
        });
    }
}
